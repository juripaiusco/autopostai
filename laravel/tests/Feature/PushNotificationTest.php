<?php

namespace Tests\Feature;

use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_user_cannot_manage_notifications(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create(['parent_id' => $admin->id]);

        $this->actingAs($user)->get(route('notifications'))->assertForbidden();
        $this->actingAs($user)->get(route('notifications.create'))->assertForbidden();
    }

    public function test_manager_only_sees_notifications_they_created(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);

        PushNotification::factory()->create(['created_by_user_id' => $managerA->id, 'title' => 'Da A']);
        PushNotification::factory()->create(['created_by_user_id' => $managerB->id, 'title' => 'Da B']);

        $this->actingAs($managerA)
            ->get(route('notifications'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Da A')
            );
    }

    public function test_admin_sees_all_notifications(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        PushNotification::factory()->create(['created_by_user_id' => $manager->id]);

        $this->actingAs($admin)
            ->get(route('notifications'))
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1));
    }

    public function test_manager_cannot_broadcast_to_all_only_to_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);

        $this->actingAs($manager)->post(route('notifications.store'), [
            'title' => 'Ciao',
            'body' => 'Corpo',
            'recipient_type' => 'all',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('notifications.store'), [
            'title' => 'Ciao',
            'body' => 'Corpo',
            'recipient_type' => 'children',
        ])->assertRedirect(route('notifications'));

        $n = PushNotification::latest('id')->first();
        $this->assertSame('children', $n->audience);
        $this->assertSame($manager->id, $n->created_by_user_id);
        $this->assertNull($n->sent_at);
    }

    public function test_admin_can_broadcast_to_all(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);

        $this->actingAs($admin)->post(route('notifications.store'), [
            'title' => 'Manutenzione',
            'body' => 'Il sistema sarà offline stanotte',
            'recipient_type' => 'all',
        ])->assertRedirect(route('notifications'));

        $this->assertSame('all', PushNotification::latest('id')->first()->audience);
    }

    public function test_manager_cannot_target_a_user_outside_their_own_children(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childOfB = User::factory()->create(['parent_id' => $managerB->id]);

        $this->actingAs($managerA)->post(route('notifications.store'), [
            'title' => 'Ciao',
            'body' => 'Corpo',
            'recipient_type' => 'user',
            'user_id' => $childOfB->id,
        ])->assertStatus(422);
    }

    public function test_targeting_a_specific_own_child_works(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $child = User::factory()->create(['parent_id' => $manager->id]);

        $this->actingAs($manager)->post(route('notifications.store'), [
            'title' => 'Ciao',
            'body' => 'Corpo',
            'recipient_type' => 'user',
            'user_id' => $child->id,
        ])->assertRedirect(route('notifications'));

        $n = PushNotification::latest('id')->first();
        $this->assertSame($child->id, $n->user_id);
        $this->assertNull($n->audience);
    }

    public function test_sent_notification_cannot_be_edited(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $n = PushNotification::factory()->create(['created_by_user_id' => $admin->id, 'sent_at' => now()]);

        $this->actingAs($admin)
            ->get(route('notifications.edit', $n))
            ->assertRedirect(route('notifications'));

        $this->actingAs($admin)->put(route('notifications.update', $n), [
            'title' => 'x', 'body' => 'y', 'recipient_type' => 'all',
        ])->assertForbidden();
    }

    public function test_manager_cannot_delete_another_managers_notification(): void
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $n = PushNotification::factory()->create(['created_by_user_id' => $managerB->id]);

        $this->actingAs($managerA)->delete(route('notifications.destroy', $n))->assertForbidden();
    }
}
