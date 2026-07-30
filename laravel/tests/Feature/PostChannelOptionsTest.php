<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostChannelOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_exposes_meta_for_all_five_channels_even_when_not_enabled(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true, 'reply_on' => true]],
        ]);

        $this->actingAs($user)
            ->get(route('posts.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('channelsMeta.facebook.available', true)
                ->where('channelsMeta.facebook.replyOn', true)
                ->where('channelsMeta.instagram.available', false)
                ->where('channelsMeta.wordpress.available', false)
                ->where('channelsMeta.newsletter.available', false)
            );
    }

    public function test_storing_a_post_saves_per_channel_comment_options_and_aggregates_them(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => [
                'facebook' => ['on' => true, 'reply_on' => true],
                'linkedin' => ['on' => true, 'reply_on' => true],
            ],
        ]);

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Post multi-canale',
            'channels' => [
                'facebook' => ['comments_enabled' => true, 'auto_reply_enabled' => true],
                'linkedin' => ['comments_enabled' => true, 'auto_reply_enabled' => false],
            ],
            'ai_prompt_comment' => 'Rispondi gentilmente',
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::where('title', 'Post multi-canale')->firstOrFail();

        $this->assertTrue($post->channels['facebook']['on']);
        $this->assertTrue($post->channels['facebook']['comments_enabled']);
        $this->assertTrue($post->channels['facebook']['auto_reply_enabled']);
        $this->assertTrue($post->channels['linkedin']['comments_enabled']);
        $this->assertFalse($post->channels['linkedin']['auto_reply_enabled']);
        $this->assertFalse($post->channels['instagram']['on']);

        // Aggregati per Posts/Show.vue: vero se ALMENO un canale ce l'ha attivo.
        $this->assertSame('1', $post->comments_enabled);
        $this->assertSame('1', $post->auto_reply_enabled);
    }

    public function test_storing_a_post_saves_wordpress_categories_and_newsletter_list(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => [
                'wordpress' => ['on' => true],
                'newsletter' => ['on' => true],
            ],
        ]);

        $this->actingAs($user)->post(route('posts.store'), [
            'channels' => [
                'wordpress' => ['categories' => [
                    ['id' => '8', 'name' => 'Novità', 'on' => true],
                    ['id' => '12', 'name' => 'Ricette', 'on' => false],
                ]],
                'newsletter' => ['list' => ['provider' => 'mailchimp', 'id' => 'abc123', 'name' => 'Clienti VIP']],
            ],
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::latest('id')->firstOrFail();

        $this->assertSame([
            ['id' => '8', 'name' => 'Novità', 'on' => true],
            ['id' => '12', 'name' => 'Ricette', 'on' => false],
        ], $post->channels['wordpress']['categories']);
        $this->assertSame(['provider' => 'mailchimp', 'id' => 'abc123', 'name' => 'Clienti VIP'], $post->channels['newsletter']['list']);

        // Nessun canale social selezionato: aggregati restano spenti.
        $this->assertSame('0', $post->comments_enabled);
        $this->assertSame('0', $post->auto_reply_enabled);
    }

    public function test_storing_a_post_freezes_the_account_reply_cap_into_the_post_channels(): void
    {
        // reply_n vive nell'account (Account/Form.vue) ma va congelato nel post
        // al salvataggio: il worker di pubblicazione legge solo il post, non risale
        // all'account per ogni controllo.
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true, 'reply_on' => true, 'reply_n' => 7]],
        ]);

        $this->actingAs($user)->post(route('posts.store'), [
            'channels' => ['facebook' => ['comments_enabled' => true, 'auto_reply_enabled' => true]],
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::latest('id')->firstOrFail();

        $this->assertSame(7, $post->channels['facebook']['reply_n']);
    }

    public function test_editing_a_post_refreshes_the_reply_cap_from_the_current_account_setting(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true, 'reply_on' => true, 'reply_n' => 3]],
        ]);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '0',
            'published_at' => null,
            'channels' => ['facebook' => ['on' => true, 'comments_enabled' => true, 'auto_reply_enabled' => true, 'reply_n' => 3]],
        ]);

        // L'account alza il tetto DOPO la creazione del post ('channels' non e'
        // mass-assignable su User, si scrive per property diretta come fa AccountController).
        $user->channels = ['facebook' => ['on' => true, 'reply_on' => true, 'reply_n' => 10]];
        $user->save();

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => ['comments_enabled' => true, 'auto_reply_enabled' => true]],
        ])->assertRedirect(route('posts'));

        $this->assertSame(10, $post->fresh()->channels['facebook']['reply_n']);
    }

    public function test_storing_a_post_for_an_smtp_custom_account_writes_provider_without_a_list(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['newsletter' => ['on' => true]],
        ]);
        \App\Models\Settings::factory()->create(['user_id' => $user->id, 'nl_smtp_host' => 'smtp.test.it']);

        $this->actingAs($user)->post(route('posts.store'), [
            'channels' => ['newsletter' => []],
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::latest('id')->firstOrFail();

        $this->assertSame(['on' => true, 'provider' => 'smtp_custom'], $post->channels['newsletter']);
    }

    public function test_unknown_channel_key_is_rejected(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $this->actingAs($user)->post(route('posts.store'), [
            'channels' => ['tiktok' => []],
            'action' => 'save',
        ])->assertStatus(422);
    }

    public function test_editing_a_post_reconstructs_selected_channels_with_their_options(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id, 'channels' => ['facebook' => ['on' => true, 'reply_on' => true]]]);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '0',
            'published_at' => null,
            'channels' => [
                'facebook' => ['on' => true, 'comments_enabled' => true, 'auto_reply_enabled' => false],
                'instagram' => ['on' => false],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('posts.edit', $post))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.channels.facebook.on', true)
                ->where('post.channels.facebook.comments_enabled', true)
                ->where('channelsMeta.facebook.replyOn', true)
            );
    }
}
