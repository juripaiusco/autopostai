<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostIndexScopeTest extends TestCase
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

    public function test_admin_scoped_to_a_user_sees_only_their_posts(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => null, 'child_on' => 1]);
        $target = User::factory()->create(['parent_id' => $manager->id]);
        $other = User::factory()->create(['parent_id' => $manager->id]);
        $targetPost = $this->makePost($target);
        $this->makePost($other);

        $response = $this->actingAs($admin)
            ->withSession(['scoped_user_id' => $target->id])
            ->get(route('posts'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Posts/List')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $targetPost->id)
        );
    }

    public function test_manager_cannot_scope_to_a_user_outside_their_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $child = User::factory()->create(['parent_id' => $manager->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);
        $childPost = $this->makePost($child);
        $this->makePost($stranger);

        $response = $this->actingAs($manager)
            ->withSession(['scoped_user_id' => $stranger->id])
            ->get(route('posts'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $childPost->id)
        );
    }
}
