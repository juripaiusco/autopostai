<?php

use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('a valid signed link unsubscribes the contact and adds it to the suppression list', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $contact = Contact::factory()->create(['user_id' => $account->id, 'status' => 'active']);

    $url = URL::signedRoute('newsletter.unsubscribe', ['contact' => $contact->id]);

    $this->get($url)->assertOk();

    $contact->refresh();
    expect($contact->status)->toBe('unsubscribed');
    expect($contact->unsubscribed_at)->not->toBeNull();

    $suppressed = SuppressionList::where('user_id', $account->id)->where('email', $contact->email)->first();
    expect($suppressed)->not->toBeNull();
    expect($suppressed->reason)->toBe('unsubscribe');
});

test('an unsigned or tampered link is rejected', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $contact = Contact::factory()->create(['user_id' => $account->id, 'status' => 'active']);

    $this->get(route('newsletter.unsubscribe', ['contact' => $contact->id]))->assertForbidden();

    $url = URL::signedRoute('newsletter.unsubscribe', ['contact' => $contact->id]);
    $this->get($url.'tampered')->assertForbidden();

    expect($contact->fresh()->status)->toBe('active');
});

test('clicking the link twice is idempotent', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $contact = Contact::factory()->create(['user_id' => $account->id, 'status' => 'active']);
    $url = URL::signedRoute('newsletter.unsubscribe', ['contact' => $contact->id]);

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    expect(SuppressionList::where('user_id', $account->id)->where('email', $contact->email)->count())->toBe(1);
});

test('the signature Laravel generates matches the python replication algorithm', function () {
    // Verifica di regressione: publisher/integrations/unsubscribe.py (Python)
    // replica hash_hmac('sha256', url_assoluto_senza_query, APP_KEY grezza) —
    // se mai cambiasse la config URL signing di Laravel, questo test lo becca.
    $contact = Contact::factory()->create(['user_id' => User::factory()->create(['parent_id' => null])->id]);
    $url = URL::signedRoute('newsletter.unsubscribe', ['contact' => $contact->id]);

    $base = route('newsletter.unsubscribe', ['contact' => $contact->id]);
    $expected = hash_hmac('sha256', $base, config('app.key'));

    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query['signature'])->toBe($expected);
});
