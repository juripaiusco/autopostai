<?php

use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function runBackfillHardBounces(): void
{
    (require database_path('migrations/2026_11_05_100000_backfill_hard_bounces_into_suppression_list.php'))->up();
}

test('backfill puts already bounced contacts into the suppression list (A7)', function () {
    $account = User::factory()->create();
    $bounced = Contact::factory()->create(['user_id' => $account->id, 'email' => 'dead@example.com', 'status' => 'bounced']);
    $trashed = Contact::factory()->create(['user_id' => $account->id, 'email' => 'gone@example.com', 'status' => 'bounced']);
    $trashed->delete();
    Contact::factory()->create(['user_id' => $account->id, 'email' => 'ok@example.com', 'status' => 'active']);

    runBackfillHardBounces();

    expect(SuppressionList::where('user_id', $account->id)->orderBy('email')->pluck('reason', 'email')->all())
        ->toBe(['dead@example.com' => 'hard_bounce', 'gone@example.com' => 'hard_bounce']);
});

test('backfill keeps existing suppression rows and can run twice', function () {
    $account = User::factory()->create();
    Contact::factory()->create(['user_id' => $account->id, 'email' => 'spam@example.com', 'status' => 'bounced']);
    SuppressionList::create(['user_id' => $account->id, 'email' => 'spam@example.com', 'reason' => 'complaint']);

    runBackfillHardBounces();
    runBackfillHardBounces();

    expect(SuppressionList::where('user_id', $account->id)->pluck('reason')->all())->toBe(['complaint']);
});
