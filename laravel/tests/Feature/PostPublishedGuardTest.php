<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostPublishedGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $owner, array $overrides = []): Post
    {
        return Post::factory()->create(array_merge([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => ['facebook' => ['on' => true]],
        ], $overrides));
    }

    public function test_edit_redirects_to_show_for_published_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePost($owner, ['published' => '1']);

        $this->actingAs($owner)->get(route('posts.edit', $post))
            ->assertRedirect(route('posts.show', $post));
    }

    public function test_edit_still_works_for_draft_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePost($owner, ['published' => '0', 'published_at' => null]);

        $this->actingAs($owner)->get(route('posts.edit', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Posts/Form'));
    }

    public function test_update_is_forbidden_for_published_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePost($owner, ['published' => '1']);

        $this->actingAs($owner)->put(route('posts.update', $post), [
            'title' => 'Nuovo titolo',
            'channels' => ['facebook' => []],
        ])->assertForbidden();
    }

    public function test_update_still_works_for_scheduled_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePost($owner, ['published' => '0', 'published_at' => now()->addDay()]);

        $this->actingAs($owner)->put(route('posts.update', $post), [
            'title' => 'Nuovo titolo',
            'channels' => ['facebook' => []],
        ])->assertRedirect(route('posts'));

        $this->assertSame('Nuovo titolo', $post->fresh()->title);
    }

    /** Uscito su Facebook, fallito su LinkedIn: published resta 0. */
    private function makePartialPost(User $owner): Post
    {
        return $this->makePost($owner, [
            'published' => '0',
            'published_at' => now()->subHour(),
            'channels' => [
                'facebook' => ['on' => true, 'id' => '123_456', 'url' => 'https://facebook.com/123_456'],
                'linkedin' => ['on' => true],
            ],
        ]);
    }

    public function test_partially_published_post_has_partial_status(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);

        $this->assertSame('partial', $this->makePartialPost($owner)->status());
    }

    public function test_edit_redirects_to_show_for_partially_published_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePartialPost($owner);

        $this->actingAs($owner)->get(route('posts.edit', $post))
            ->assertRedirect(route('posts.show', $post));
    }

    public function test_update_is_forbidden_for_partially_published_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePartialPost($owner);

        $this->actingAs($owner)->put(route('posts.update', $post), [
            'title' => 'Nuovo titolo',
            'channels' => ['facebook' => [], 'linkedin' => []],
        ])->assertForbidden();

        $this->assertSame('123_456', $post->fresh()->channels['facebook']['id']);
    }

    public function test_show_reports_status_per_channel_for_partial_post(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePartialPost($owner);

        $this->actingAs($owner)->get(route('posts.show', $post))
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.status', 'partial')
                ->where('post.channelStatus.facebook', 'published')
                ->where('post.channelStatus.linkedin', 'error')
            );
    }

    public function test_update_keeps_ids_written_by_worker_while_form_was_open(): void
    {
        $owner = User::factory()->create(['parent_id' => null]);
        $post = $this->makePost($owner, [
            'published' => '0',
            'published_at' => now()->subMinute(),
            'channels' => ['facebook' => ['on' => true], 'linkedin' => ['on' => true]],
        ]);

        // Il worker pubblica su Facebook subito dopo che la richiesta ha
        // caricato il post (route binding): il model in memoria non ha l'id.
        $published = false;
        Post::retrieved(function (Post $retrieved) use (&$published, $post) {
            if (!$published && $retrieved->id === $post->id) {
                $published = true;
                DB::table('posts')->where('id', $post->id)->update(['channels' => json_encode([
                    'facebook' => ['on' => true, 'id' => '123_456', 'url' => 'https://facebook.com/123_456'],
                    'linkedin' => ['on' => true],
                ])]);
            }
        });

        $this->actingAs($owner)->put(route('posts.update', $post), [
            'title' => 'Nuovo titolo',
            'channels' => ['facebook' => [], 'linkedin' => []],
        ])->assertRedirect(route('posts'));

        $channels = $post->fresh()->channels;
        $this->assertSame('123_456', $channels['facebook']['id']);
        $this->assertSame('https://facebook.com/123_456', $channels['facebook']['url']);
        $this->assertArrayNotHasKey('id', $channels['linkedin']);
    }
}
