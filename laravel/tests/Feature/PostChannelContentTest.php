<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostChannelContentTest extends TestCase
{
    use RefreshDatabase;

    private function simpleUser(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create(['parent_id' => $admin->id]);
    }

    private function publishedPost(User $owner): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'ai_content' => 'Testo comune',
            'published' => '1',
            'published_at' => now()->subHour(),
            'channels' => [
                'facebook' => ['on' => true, 'id' => '123_456', 'url' => 'https://facebook.test/1'],
                'instagram' => ['on' => true, 'id' => '789', 'url' => 'https://instagram.test/1'],
                'wordpress' => ['on' => true, 'attempts' => 3, 'error' => 'HTTPError: 500'],
            ],
        ]);
    }

    public function test_editing_a_published_facebook_post_queues_the_update(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);

        $this->actingAs($user)
            ->put(route('posts.channel-content.update', [$post, 'facebook']), ['content' => 'Testo corretto'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $fresh = $post->fresh();
        $this->assertSame('2', $fresh->updated);
        $this->assertSame('Testo corretto', $fresh->channels['facebook']['content']);
        $this->assertTrue($fresh->channels['facebook']['update_pending']);
        $this->assertNull($fresh->channels['facebook']['update_error'] ?? null);
        // Le chiavi del worker e gli altri canali restano com'erano.
        $this->assertSame('123_456', $fresh->channels['facebook']['id']);
        $this->assertArrayNotHasKey('content', $fresh->channels['instagram']);
        $this->assertSame('Testo comune', $fresh->ai_content);
    }

    public function test_channels_that_cannot_be_edited_after_publishing_are_rejected(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);

        $this->actingAs($user)
            ->put(route('posts.channel-content.update', [$post, 'instagram']), ['content' => 'x'])
            ->assertStatus(422);

        $this->assertSame('0', (string) $post->fresh()->updated);
    }

    public function test_a_channel_not_yet_published_is_rejected(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);

        $this->actingAs($user)
            ->put(route('posts.channel-content.update', [$post, 'wordpress']), ['content' => 'x'])
            ->assertStatus(422);
    }

    public function test_content_is_required(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);

        $this->actingAs($user)
            ->put(route('posts.channel-content.update', [$post, 'facebook']), ['content' => ''])
            ->assertSessionHasErrors('content');
    }

    public function test_editing_is_forbidden_on_someone_elses_post(): void
    {
        $owner = $this->simpleUser();
        $other = User::factory()->create(['parent_id' => $owner->parent_id]);
        $post = $this->publishedPost($owner);

        $this->actingAs($other)
            ->put(route('posts.channel-content.update', [$post, 'facebook']), ['content' => 'x'])
            ->assertForbidden();
    }

    public function test_show_exposes_the_edit_state_of_each_channel(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);
        Post::whereKey($post->id)->update([
            'channels->facebook->content' => 'Testo corretto',
            'channels->facebook->update_error' => 'Token scaduto',
        ]);

        $this->actingAs($user)->get(route('posts.show', $post))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.channelEdit.facebook.editable', true)
                ->where('post.channelEdit.facebook.content', 'Testo corretto')
                ->where('post.channelEdit.facebook.pending', false)
                ->where('post.channelEdit.facebook.error', 'Token scaduto')
                ->where('post.channelEdit.instagram.editable', false)
                // Con WordPress acceso il testo comune è l'articolo: ai social arriva
                // in HTML, la pagina lo mostra ripulito (htmlToText).
                ->where('post.channelEdit.instagram.content', '<p>Testo comune</p>')
                ->where('post.channelEdit.instagram.format', 'html')
                ->where('post.channelEdit.wordpress.editable', false)
            );
    }

    public function test_wordpress_edit_from_the_page_is_sanitized_html(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);
        $post->update(['channels' => [...$post->channels, 'wordpress' => ['on' => true, 'id' => 9, 'url' => 'https://blog.test/p']]]);

        $this->actingAs($user)->put(route('posts.channel-content.update', [$post, 'wordpress']), [
            'content' => '<h1>Titolo</h1><p onclick="x()">Testo <a href="[facebook url id=3]">fb</a></p>',
            'format' => 'html',
        ])->assertRedirect();

        $wp = $post->fresh()->channels['wordpress'];
        $this->assertSame('<h1>Titolo</h1><p>Testo <a href="[facebook url id=3]">fb</a></p>', $wp['content']);
        $this->assertSame('html', $wp['content_format']);
    }

    public function test_facebook_edit_is_always_plain_text(): void
    {
        $user = $this->simpleUser();
        $post = $this->publishedPost($user);

        $this->actingAs($user)->put(route('posts.channel-content.update', [$post, 'facebook']), [
            'content' => 'Ciao <b>mondo</b>',
            'format' => 'html',
        ]);

        $fb = $post->fresh()->channels['facebook'];
        $this->assertSame('Ciao <b>mondo</b>', $fb['content']);
        $this->assertSame('text', $fb['content_format']);
    }

    public function test_show_gives_each_editor_its_format(): void
    {
        $user = $this->simpleUser();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'ai_content' => '<h1>Titolo</h1><p>Articolo</p>',
            'ai_content_format' => 'html',
            'ai_content_social' => 'Post social 🎉',
            'published' => '1',
            'published_at' => now()->subHour(),
            'channels' => [
                'facebook' => ['on' => true, 'id' => '1_2', 'url' => 'https://facebook.test/1'],
                'wordpress' => ['on' => true, 'id' => 9, 'url' => 'https://blog.test/p', 'content' => "# Vecchio\n\nMarkdown"],
            ],
        ]);

        $this->actingAs($user)->get(route('posts.show', $post))->assertInertia(fn (Assert $page) => $page
            ->where('post.channelEdit.facebook.content', 'Post social 🎉')
            ->where('post.channelEdit.facebook.format', 'text')
            ->where('post.channelEdit.wordpress.content', "<h1>Vecchio</h1>\n<p>Markdown</p>")
            ->where('post.channelEdit.wordpress.format', 'html')
            ->where('post.ownerId', $user->id)
        );
    }
}
