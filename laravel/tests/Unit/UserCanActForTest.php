<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCanActForTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_act_for_themselves(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $simpleUser = User::factory()->create(['parent_id' => $admin->id]);

        $this->assertTrue($simpleUser->canActFor($simpleUser));
    }

    public function test_admin_can_act_for_anyone(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);

        $this->assertTrue($admin->canActFor($stranger));
    }

    public function test_manager_can_act_for_own_children_only(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $ownChild = User::factory()->create(['parent_id' => $manager->id]);
        $strangerChild = User::factory()->create(['parent_id' => $admin->id]);

        $this->assertTrue($manager->canActFor($ownChild));
        $this->assertFalse($manager->canActFor($strangerChild));
    }

    public function test_simple_user_cannot_act_for_someone_else(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);
        $sibling = User::factory()->create(['parent_id' => $admin->id]);

        $this->assertFalse($user->canActFor($sibling));
    }
}
