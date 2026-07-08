<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PushNotificationAlert;
use App\Models\PushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_subscribe_and_unsubscribe(): void
    {
        $user = User::factory()->create(['parent_id' => null]);

        $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'key-p256dh', 'auth' => 'key-auth'],
        ])->assertOk();

        $this->assertCount(1, $user->pushSubscriptions);

        $this->actingAs($user)->postJson(route('push.unsubscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ])->assertOk();

        $this->assertCount(0, $user->fresh()->pushSubscriptions);
    }

    public function test_unread_check_reflects_unread_database_notifications(): void
    {
        $user = User::factory()->create(['parent_id' => null]);

        $this->actingAs($user)->getJson(route('push.unread'))->assertJson(['unread' => false]);

        $n = PushNotification::factory()->create(['created_by_user_id' => $user->id, 'user_id' => $user->id]);
        $user->notify(new PushNotificationAlert($n));

        $this->actingAs($user)->getJson(route('push.unread'))->assertJson(['unread' => true]);
    }

    public function test_mark_read_clears_unread_and_returns_recent_notifications(): void
    {
        $user = User::factory()->create(['parent_id' => null]);
        $n = PushNotification::factory()->create(['created_by_user_id' => $user->id, 'user_id' => $user->id, 'title' => 'Ciao']);
        $user->notify(new PushNotificationAlert($n));

        $this->actingAs($user)
            ->postJson(route('push.mark-read'))
            ->assertOk()
            ->assertJsonPath('notifications.0.title', 'Ciao');

        $this->actingAs($user)->getJson(route('push.unread'))->assertJson(['unread' => false]);
    }
}
