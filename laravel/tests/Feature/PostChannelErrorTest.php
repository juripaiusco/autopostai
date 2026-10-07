<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostChannelErrorTest extends TestCase
{
    use RefreshDatabase;

    private function simpleUser(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true], 'linkedin' => ['on' => true]],
        ]);
    }

    private function makePost(User $owner, array $channels): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => $channels,
            'published' => '0',
            'published_at' => now()->subMinutes(5),
        ]);
    }

    public function test_post_with_only_failed_channels_is_in_error_not_draft(): void
    {
        $user = $this->simpleUser();
        $post = $this->makePost($user, [
            'facebook' => ['on' => true, 'attempts' => 1, 'error' => 'HTTPError: 400'],
        ]);

        $this->assertSame('error', $post->status());
        $this->assertTrue($post->isEditable());
    }

    public function test_show_exposes_the_error_of_each_failed_channel(): void
    {
        $user = $this->simpleUser();
        $post = $this->makePost($user, [
            'facebook' => ['on' => true, 'attempts' => 3, 'error' => 'Token non valido'],
            'linkedin' => ['on' => true, 'id' => 'urn:li:share:1', 'url' => 'https://example.test'],
        ]);

        $this->actingAs($user)->get(route('posts.show', $post))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.status', 'partial')
                ->where('post.channelStatus.facebook', 'error')
                ->where('post.channelStatus.linkedin', 'published')
                ->where('post.maxAttempts', Post::PUBLISH_MAX_ATTEMPTS)
                ->has('post.channelErrors', 1)
                ->where('post.channelErrors.0.channel', 'facebook')
                ->where('post.channelErrors.0.message', 'Token non valido')
                ->where('post.channelErrors.0.attempts', 3)
                ->where('post.channelErrors.0.gaveUp', true)
            );
    }

    public function test_retry_resets_only_the_failed_channels(): void
    {
        $user = $this->simpleUser();
        $post = $this->makePost($user, [
            'facebook' => ['on' => true, 'attempts' => 3, 'error' => 'Token non valido'],
            'linkedin' => ['on' => true, 'id' => 'urn:li:share:1', 'url' => 'https://example.test'],
        ]);

        $this->actingAs($user)->post(route('posts.retry', $post))
            ->assertRedirect()
            ->assertSessionHas('toast');

        $channels = $post->fresh()->channels;

        $this->assertEmpty($channels['facebook']['attempts'] ?? 0);
        $this->assertEmpty($channels['facebook']['error'] ?? null);
        $this->assertTrue($channels['facebook']['on']);
        // Il canale già uscito non viene toccato: il worker non lo ripubblica.
        $this->assertSame('urn:li:share:1', $channels['linkedin']['id']);
        $this->assertSame([], $post->fresh()->channelErrors());
    }

    public function test_retry_without_failed_channels_is_rejected(): void
    {
        $user = $this->simpleUser();
        $post = $this->makePost($user, ['facebook' => ['on' => true]]);

        $this->actingAs($user)->post(route('posts.retry', $post))->assertStatus(422);
    }

    public function test_retry_is_forbidden_on_someone_elses_post(): void
    {
        $owner = $this->simpleUser();
        $other = User::factory()->create(['parent_id' => $owner->parent_id]);
        $post = $this->makePost($owner, [
            'facebook' => ['on' => true, 'attempts' => 3, 'error' => 'Token non valido'],
        ]);

        $this->actingAs($other)->post(route('posts.retry', $post))->assertForbidden();

        $this->assertSame(3, $post->fresh()->channels['facebook']['attempts']);
    }

    public function test_saving_the_form_again_resets_the_failed_attempts(): void
    {
        $user = $this->simpleUser();
        $post = $this->makePost($user, [
            'facebook' => ['on' => true, 'attempts' => 3, 'error' => 'Token non valido'],
        ]);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => 'Corretto',
            'channels' => ['facebook' => []],
            'published_at' => now()->subMinute()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('posts'));

        $channels = $post->fresh()->channels;

        $this->assertArrayNotHasKey('attempts', $channels['facebook']);
        $this->assertArrayNotHasKey('error', $channels['facebook']);
    }
}
