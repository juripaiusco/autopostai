<?php

namespace Tests\Feature;

use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\PostPublishedAlert;
use App\Notifications\PushNotificationAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendPendingPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_to_a_specific_user_and_marks_as_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['parent_id' => null]);
        $target = User::factory()->create(['parent_id' => $admin->id]);
        $n = PushNotification::factory()->create([
            'created_by_user_id' => $admin->id,
            'user_id' => $target->id,
            'audience' => null,
        ]);

        $this->artisan('notifications:send-pending')->assertExitCode(0);

        Notification::assertSentTo($target, PushNotificationAlert::class);
        $this->assertNotNull($n->fresh()->sent_at);
        $this->assertSame(1, $n->fresh()->recipients_count);
    }

    public function test_broadcasts_all_sends_to_everyone_including_the_creator(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['parent_id' => null]);
        $u1 = User::factory()->create(['parent_id' => $admin->id]);
        $u2 = User::factory()->create(['parent_id' => $admin->id]);
        $n = PushNotification::factory()->create(['created_by_user_id' => $admin->id, 'audience' => 'all']);

        $this->artisan('notifications:send-pending');

        Notification::assertSentTo($u1, PushNotificationAlert::class);
        Notification::assertSentTo($u2, PushNotificationAlert::class);
        Notification::assertSentTo($admin, PushNotificationAlert::class);
        $this->assertSame(3, $n->fresh()->recipients_count);
    }

    public function test_children_audience_only_reaches_creators_own_children(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['parent_id' => null]);
        $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
        $childA = User::factory()->create(['parent_id' => $managerA->id]);
        $childB = User::factory()->create(['parent_id' => $managerB->id]);

        PushNotification::factory()->create(['created_by_user_id' => $managerA->id, 'audience' => 'children']);

        $this->artisan('notifications:send-pending');

        Notification::assertSentTo($childA, PushNotificationAlert::class);
        Notification::assertNotSentTo($childB, PushNotificationAlert::class);
    }

    public function test_already_sent_notifications_are_not_resent(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['parent_id' => null]);
        User::factory()->create(['parent_id' => $admin->id]);
        PushNotification::factory()->create(['created_by_user_id' => $admin->id, 'audience' => 'all', 'sent_at' => now()->subHour()]);

        $this->artisan('notifications:send-pending');

        Notification::assertNothingSent();
    }

    public function test_post_published_kind_uses_dedicated_notification_class(): void
    {
        Notification::fake();

        $creator = User::factory()->create(['parent_id' => null]);
        $n = PushNotification::factory()->create([
            'created_by_user_id' => $creator->id,
            'user_id' => $creator->id,
            'audience' => null,
            'kind' => 'post_published',
        ]);

        $this->artisan('notifications:send-pending')->assertExitCode(0);

        Notification::assertSentTo($creator, PostPublishedAlert::class);
        Notification::assertNotSentTo($creator, PushNotificationAlert::class);
        $this->assertNotNull($n->fresh()->sent_at);
    }

    public function test_post_published_alert_is_not_bell_visible(): void
    {
        // Il canale 'database' e' cio' che rende una notifica visibile in campanella.
        // PostPublishedAlert deve usare SOLO WebPush, quindi via() non contiene 'database'.
        $creator = User::factory()->create(['parent_id' => null]);
        $n = PushNotification::factory()->create([
            'created_by_user_id' => $creator->id,
            'user_id' => $creator->id,
            'kind' => 'post_published',
        ]);

        $channels = (new PostPublishedAlert($n))->via($creator);

        $this->assertNotContains('database', $channels);
    }

    public function test_default_kind_stays_bell_visible(): void
    {
        // Regressione: una riga senza kind esplicito resta 'alert' e usa il canale database.
        $creator = User::factory()->create(['parent_id' => null]);
        $n = PushNotification::factory()->create([
            'created_by_user_id' => $creator->id,
            'user_id' => $creator->id,
        ]);

        $this->assertSame('alert', $n->fresh()->kind);
        $this->assertContains('database', (new PushNotificationAlert($n))->via($creator));
    }
}
