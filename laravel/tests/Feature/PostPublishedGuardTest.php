<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
