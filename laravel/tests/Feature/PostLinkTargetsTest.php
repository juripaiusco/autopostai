<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dialog "Un tuo post" degli editor: solo post dell'account, già pubblicati
 * su almeno un canale con indirizzo.
 */
class PostLinkTargetsTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create(['parent_id' => $admin->id]);
    }

    private function publishedPost(User $user, array $attrs): Post
    {
        return Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '1',
            'published_at' => now()->subDay(),
            ...$attrs,
        ]);
    }

    public function test_lists_published_posts_with_channel_urls(): void
    {
        $user = $this->account();
        $wp = $this->publishedPost($user, ['title' => 'Novità di novembre', 'channels' => [
            'wordpress' => ['on' => true, 'id' => 5, 'url' => 'https://blog.test/novita'],
            'facebook' => ['on' => true, 'id' => '1_2', 'url' => 'https://facebook.com/1_2'],
            'instagram' => ['on' => true],
        ]]);
        $this->publishedPost($user, ['title' => 'Bozza', 'published_at' => null, 'channels' => ['wordpress' => ['on' => true]]]);
        $this->publishedPost($user, ['title' => 'Mai uscito', 'channels' => ['facebook' => ['on' => true]]]);
        $this->publishedPost($this->account(), ['title' => 'Altro account', 'channels' => ['wordpress' => ['on' => true, 'url' => 'https://x.test']]]);

        $this->actingAs($user)->getJson(route('posts.link-targets', $user))
            ->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('posts.0.id', $wp->id)
            ->assertJsonPath('posts.0.title', 'Novità di novembre')
            ->assertJsonPath('posts.0.channels', [
                ['key' => 'wordpress', 'label' => 'WordPress', 'url' => 'https://blog.test/novita'],
                ['key' => 'facebook', 'label' => 'Facebook', 'url' => 'https://facebook.com/1_2'],
            ]);
    }

    public function test_searches_by_title_or_number(): void
    {
        $user = $this->account();
        $channels = ['wordpress' => ['on' => true, 'url' => 'https://blog.test/p']];
        $pizza = $this->publishedPost($user, ['title' => 'Pizza del giorno', 'channels' => $channels]);
        $this->publishedPost($user, ['title' => 'Saldi invernali', 'channels' => $channels]);

        $this->actingAs($user)->getJson(route('posts.link-targets', $user).'?q=pizza')
            ->assertJsonCount(1, 'posts')->assertJsonPath('posts.0.id', $pizza->id);
        $this->actingAs($user)->getJson(route('posts.link-targets', $user).'?q='.$pizza->id)
            ->assertJsonPath('posts.0.id', $pizza->id);
    }

    public function test_each_post_has_thumbnail_and_plain_text_excerpt(): void
    {
        $user = $this->account();
        $channels = ['wordpress' => ['on' => true, 'url' => 'https://blog.test/p']];
        $html = $this->publishedPost($user, [
            'title' => 'Editor',
            'channels' => $channels,
            'img' => ['foto.jpg'],
            'ai_content' => '<h1>Titolo articolo</h1><p>Arrivano i <strong>nuovi</strong>, prodotti &amp; offerte.</p><p>Secondo paragrafo.</p>',
            'ai_content_format' => 'html',
        ]);
        $markdown = $this->publishedPost($user, [
            'title' => 'Markdown',
            'channels' => $channels,
            'img' => null,
            'ai_content' => "# Titolo\n\nTesto **AI** ".str_repeat('lungo ', 40),
            'ai_content_format' => 'markdown',
        ]);

        $posts = collect($this->actingAs($user)->getJson(route('posts.link-targets', $user))->json('posts'))->keyBy('id');

        $this->assertSame('Arrivano i nuovi, prodotti & offerte. Secondo paragrafo.', $posts[$html->id]['excerpt']);
        $this->assertStringEndsWith("posts/{$html->id}/foto.jpg", $posts[$html->id]['cover']);
        $this->assertSame($html->thumbGradient(), $posts[$html->id]['thumb']);

        $excerpt = $posts[$markdown->id]['excerpt'];
        $this->assertStringStartsWith('Testo AI lungo', $excerpt);
        $this->assertStringNotContainsString('Titolo', $excerpt);
        $this->assertLessThanOrEqual(113, mb_strlen($excerpt));
        $this->assertNull($posts[$markdown->id]['cover']);
    }

    public function test_other_accounts_are_forbidden(): void
    {
        $user = $this->account();

        $this->actingAs($user)->getJson(route('posts.link-targets', $this->account()))->assertForbidden();
    }
}
