<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScopeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_scope_persists_into_session_and_shared_props(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $target = User::factory()->create(['parent_id' => $admin->id]);

        $this->actingAs($admin)->post(route('scope.update'), ['user' => $target->id])
            ->assertRedirect();

        $this->assertSame($target->id, session('scoped_user_id'));

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeUserId', $target->id)
                ->where('activeUser.id', $target->id)
            );
    }

    public function test_scope_outside_viewers_filterable_users_is_rejected(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $otherAdmin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $otherAdmin->id, 'child_on' => 1]);
        $strangerChild = User::factory()->create(['parent_id' => $manager->id]);

        // $strangerChild is not in $manager-of-a-different-admin's own filterable list
        // from a plain manager's perspective who isn't their parent.
        $anotherManager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);

        $this->actingAs($anotherManager)->post(route('scope.update'), ['user' => $strangerChild->id])
            ->assertRedirect();

        $this->assertNull(session('scoped_user_id'));
    }

    public function test_posting_null_clears_existing_scope(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $target = User::factory()->create(['parent_id' => $admin->id]);

        $this->actingAs($admin)
            ->withSession(['scoped_user_id' => $target->id])
            ->post(route('scope.update'), [])
            ->assertRedirect();

        $this->assertNull(session('scoped_user_id'));
    }
}
