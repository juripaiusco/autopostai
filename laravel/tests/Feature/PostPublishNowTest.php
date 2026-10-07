<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPublishNowTest extends TestCase
{
    use RefreshDatabase;

    private function simpleUser(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true]],
        ]);
    }

    public function test_storing_without_a_date_keeps_the_post_as_a_draft(): void
    {
        $user = $this->simpleUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Senza data',
            'channels' => ['facebook' => []],
            'published_at' => '',
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::where('title', 'Senza data')->firstOrFail();

        // Il worker seleziona solo published_at <= adesso: senza data non esce.
        $this->assertNull($post->published_at);
        $this->assertSame('draft', $post->status());
    }

    public function test_publish_now_sets_the_date_on_the_server(): void
    {
        $this->freezeSecond();
        $user = $this->simpleUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Subito',
            'channels' => ['facebook' => []],
            'published_at' => '',
            'action' => 'publish_now',
        ])->assertRedirect(route('posts'))
            ->assertSessionHas('toast', 'Post in pubblicazione: esce entro un minuto.');

        $post = Post::where('title', 'Subito')->firstOrFail();

        $this->assertTrue($post->published_at->equalTo(now()));
        $this->assertSame('0', (string) $post->published);
    }

    public function test_publish_now_overrides_a_future_date(): void
    {
        $this->freezeSecond();
        $user = $this->simpleUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Programmato ma subito',
            'channels' => ['facebook' => []],
            'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'action' => 'publish_now',
        ])->assertRedirect(route('posts'));

        $post = Post::where('title', 'Programmato ma subito')->firstOrFail();

        $this->assertTrue($post->published_at->equalTo(now()));
    }

    public function test_publish_now_on_update_turns_a_draft_into_a_due_post(): void
    {
        $this->freezeSecond();
        $user = $this->simpleUser();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'channels' => ['facebook' => ['on' => true]],
            'published' => '0',
            'published_at' => null,
        ]);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => 'Bozza',
            'channels' => ['facebook' => []],
            'action' => 'publish_now',
        ])->assertRedirect(route('posts'));

        $this->assertTrue($post->fresh()->published_at->equalTo(now()));
    }

    public function test_update_without_action_keeps_the_submitted_date(): void
    {
        $user = $this->simpleUser();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'channels' => ['facebook' => ['on' => true]],
            'published' => '0',
            'published_at' => null,
        ]);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => 'Bozza',
            'channels' => ['facebook' => []],
        ])->assertRedirect(route('posts'));

        $this->assertNull($post->fresh()->published_at);
    }

    public function test_unknown_action_is_rejected(): void
    {
        $user = $this->simpleUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Azione strana',
            'channels' => ['facebook' => []],
            'action' => 'publish_yesterday',
        ])->assertSessionHasErrors('action');

        $this->assertDatabaseMissing('posts', ['title' => 'Azione strana']);
    }
}
