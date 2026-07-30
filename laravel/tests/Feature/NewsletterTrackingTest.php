<?php

use App\Models\Contact;
use App\Models\EmailSend;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function newsletterPost(User $account, array $overrides = []): Post
{
    return Post::factory()->create(array_merge([
        'user_id' => $account->id,
        'created_by_user_id' => $account->id,
        'channels' => ['newsletter' => ['on' => true, 'provider' => 'smtp_custom', 'id' => 'smtp-1']],
    ], $overrides));
}

test('hitting the pixel marks a sent email as opened exactly once', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = newsletterPost($account);
    $contact = Contact::factory()->create(['user_id' => $account->id]);
    $send = EmailSend::create(['contact_id' => $contact->id, 'post_id' => $post->id, 'status' => 'sent', 'sent_at' => now()]);

    $this->get(route('newsletter.pixel', ['emailSend' => $send->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $send->refresh();
    expect($send->status)->toBe('opened');
    $firstOpenedAt = $send->opened_at;
    expect($firstOpenedAt)->not->toBeNull();

    // Un secondo hit (client email che ricarica l'immagine) non deve
    // sovrascrivere l'orario della prima apertura.
    $this->travel(5)->minutes();
    $this->get(route('newsletter.pixel', ['emailSend' => $send->id]))->assertOk();
    expect($send->fresh()->opened_at->equalTo($firstOpenedAt))->toBeTrue();
});

test('the pixel does not downgrade a bounced email back to opened', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = newsletterPost($account);
    $contact = Contact::factory()->create(['user_id' => $account->id]);
    $send = EmailSend::create(['contact_id' => $contact->id, 'post_id' => $post->id, 'status' => 'bounced', 'bounced_at' => now()]);

    $this->get(route('newsletter.pixel', ['emailSend' => $send->id]))->assertOk();

    expect($send->fresh()->status)->toBe('bounced');
});

test('opening an email refreshes the aggregate stats on the post channel', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = newsletterPost($account);
    $c1 = Contact::factory()->create(['user_id' => $account->id]);
    $c2 = Contact::factory()->create(['user_id' => $account->id]);
    $c3 = Contact::factory()->create(['user_id' => $account->id]);
    $send1 = EmailSend::create(['contact_id' => $c1->id, 'post_id' => $post->id, 'status' => 'sent', 'sent_at' => now()]);
    EmailSend::create(['contact_id' => $c2->id, 'post_id' => $post->id, 'status' => 'sent', 'sent_at' => now()]);
    EmailSend::create(['contact_id' => $c3->id, 'post_id' => $post->id, 'status' => 'bounced', 'bounced_at' => now()]);

    $this->get(route('newsletter.pixel', ['emailSend' => $send1->id]))->assertOk();

    $stats = $post->fresh()->channels['newsletter']['stats'];
    expect($stats)->toBe([
        'queued' => 0, 'sent' => 1, 'delivered' => 0, 'opened' => 1, 'clicked' => 0, 'bounced' => 1,
    ]);
});

test('Post::refreshNewsletterStats aggregates counts per status and preserves other channel keys', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = newsletterPost($account);
    $contact = Contact::factory()->create(['user_id' => $account->id]);
    EmailSend::create(['contact_id' => $contact->id, 'post_id' => $post->id, 'status' => 'sent', 'sent_at' => now()]);

    $post->refreshNewsletterStats();

    $channels = $post->fresh()->channels['newsletter'];
    expect($channels['id'])->toBe('smtp-1');
    expect($channels['provider'])->toBe('smtp_custom');
    expect($channels['stats']['sent'])->toBe(1);
});

test('refreshNewsletterStats is a no-op when the post has no newsletter channel at all', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = newsletterPost($account, ['channels' => ['facebook' => ['on' => true]]]);

    $post->refreshNewsletterStats();

    expect($post->fresh()->channels)->toBe(['facebook' => ['on' => true]]);
});
