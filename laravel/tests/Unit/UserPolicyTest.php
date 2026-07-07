<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy();
    }

    public function test_admin_can_view_any_and_manage_anyone(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $someone = User::factory()->create(['parent_id' => null]);

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->view($admin, $someone));
        $this->assertTrue($this->policy->update($admin, $someone));
        $this->assertTrue($this->policy->delete($admin, $someone));
    }

    public function test_manager_can_view_any_but_only_manage_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $ownChild = User::factory()->create(['parent_id' => $manager->id]);
        $strangerChild = User::factory()->create(['parent_id' => $admin->id]);

        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertFalse($this->policy->create($manager));

        $this->assertTrue($this->policy->view($manager, $ownChild));
        $this->assertTrue($this->policy->update($manager, $ownChild));
        $this->assertTrue($this->policy->delete($manager, $ownChild));

        $this->assertFalse($this->policy->view($manager, $strangerChild));
        $this->assertFalse($this->policy->update($manager, $strangerChild));
        $this->assertFalse($this->policy->delete($manager, $strangerChild));
    }

    public function test_simple_user_cannot_view_any_or_manage_anyone(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $other = User::factory()->create(['parent_id' => $admin->id]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->view($user, $other));
        $this->assertFalse($this->policy->update($user, $other));
        $this->assertFalse($this->policy->delete($user, $other));
    }
}
