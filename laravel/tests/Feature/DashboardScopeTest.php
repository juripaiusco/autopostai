<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardScopeTest extends TestCase
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

    public function test_admin_sees_all_other_users_and_can_scope_to_any(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => null, 'child_on' => 1]);
        $orphanUser = User::factory()->create(['parent_id' => $manager->id]);
        $post = $this->makePost($orphanUser);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdmin', true)
            ->where('isManager', false)
            ->has('filterableUsers', 2)
        );

        $response = $this->actingAs($admin)
            ->withSession(['scoped_user_id' => $orphanUser->id])
            ->get(route('dashboard'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('activeUserId', $orphanUser->id)
            ->where('postsCount', 1)
            ->has('recentPosts', 1)
            ->where('recentPosts.0.id', $post->id)
        );
    }

    public function test_manager_sees_only_own_children_and_invalid_scope_falls_back(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $child = User::factory()->create(['parent_id' => $manager->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);
        $childPost = $this->makePost($child);
        $this->makePost($stranger);

        $response = $this->actingAs($manager)->get(route('dashboard'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', false)
            ->where('isManager', true)
            ->has('filterableUsers', 1)
            ->where('filterableUsers.0.id', $child->id)
            ->where('postsCount', 1)
        );

        // Scoping to the manager's own child works.
        $response = $this->actingAs($manager)
            ->withSession(['scoped_user_id' => $child->id])
            ->get(route('dashboard'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('activeUserId', $child->id)
            ->where('postsCount', 1)
            ->where('recentPosts.0.id', $childPost->id)
        );

        // Scoping to a user outside the manager's own children is ignored, not leaked.
        $response = $this->actingAs($manager)
            ->withSession(['scoped_user_id' => $stranger->id])
            ->get(route('dashboard'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('activeUserId', null)
            ->where('postsCount', 1)
        );
    }

    public function test_plain_user_has_no_filter_and_only_sees_own_posts(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);
        $ownPost = $this->makePost($user);
        $this->makePost($stranger);

        $response = $this->actingAs($user)
            ->withSession(['scoped_user_id' => $stranger->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', false)
            ->where('isManager', false)
            ->has('filterableUsers', 0)
            ->where('activeUserId', null)
            ->where('postsCount', 1)
            ->where('recentPosts.0.id', $ownPost->id)
        );
    }
}
