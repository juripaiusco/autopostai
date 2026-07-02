<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountIndexScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_scoped_to_a_manager_sees_that_managers_sub_users(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $subUser = User::factory()->create(['parent_id' => $manager->id]);
        User::factory()->create(['parent_id' => $admin->id]); // unrelated sibling, not under $manager

        $response = $this->actingAs($admin)
            ->withSession(['scoped_user_id' => $manager->id])
            ->get(route('account'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/List')
            ->has('users.data', 1)
            ->where('users.data.0.id', $subUser->id)
        );
    }

    public function test_manager_cannot_scope_to_a_user_outside_their_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $child = User::factory()->create(['parent_id' => $manager->id]);
        $stranger = User::factory()->create(['parent_id' => $admin->id]);

        $response = $this->actingAs($manager)
            ->withSession(['scoped_user_id' => $stranger->id])
            ->get(route('account'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $child->id)
        );
    }

    public function test_without_scope_admin_sees_all_accounts_as_before(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        User::factory()->create(['parent_id' => $manager->id]);

        $response = $this->actingAs($admin)->get(route('account'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
        );
    }
}
