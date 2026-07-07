<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PostPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PostPolicy();
    }

    private function postBy(User $owner): Post
    {
        return Post::factory()->create([
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'channels' => ['facebook' => ['on' => true]],
        ]);
    }

    public function test_admin_can_view_update_and_delete_any_post(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $owner = User::factory()->create(['parent_id' => $admin->id]);
        $post = $this->postBy($owner);

        $this->assertTrue($this->policy->view($admin, $post));
        $this->assertTrue($this->policy->update($admin, $post));
        $this->assertTrue($this->policy->delete($admin, $post));
    }

    public function test_manager_can_only_touch_posts_of_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $ownChild = User::factory()->create(['parent_id' => $manager->id]);
        $strangerChild = User::factory()->create(['parent_id' => $admin->id]);

        $ownPost = $this->postBy($ownChild);
        $strangerPost = $this->postBy($strangerChild);

        $this->assertTrue($this->policy->view($manager, $ownPost));
        $this->assertTrue($this->policy->update($manager, $ownPost));
        $this->assertTrue($this->policy->delete($manager, $ownPost));

        $this->assertFalse($this->policy->view($manager, $strangerPost));
        $this->assertFalse($this->policy->update($manager, $strangerPost));
        $this->assertFalse($this->policy->delete($manager, $strangerPost));
    }

    public function test_simple_user_can_only_touch_their_own_post(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);

        $ownPost = $this->postBy($user);
        $strangerPost = $this->postBy($stranger);

        $this->assertTrue($this->policy->view($user, $ownPost));
        $this->assertTrue($this->policy->update($user, $ownPost));
        $this->assertTrue($this->policy->delete($user, $ownPost));

        $this->assertFalse($this->policy->view($user, $strangerPost));
        $this->assertFalse($this->policy->update($user, $strangerPost));
        $this->assertFalse($this->policy->delete($user, $strangerPost));
    }
}
