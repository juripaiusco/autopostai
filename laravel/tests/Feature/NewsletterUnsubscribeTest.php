<?php

use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/** Link firmato come lo genera il worker Python: firma relativa (solo path). */
function unsubscribeUrl(Contact $contact): string
{
    return URL::signedRoute('newsletter.unsubscribe', ['contact' => $contact->id], null, false);
}

function activeContact(): Contact
{
    $account = User::factory()->create(['parent_id' => null]);

    return Contact::factory()->create(['user_id' => $account->id, 'status' => 'active']);
}

test('opening the link only shows the confirmation page', function () {
    // Gli scanner antiphishing aprono i link delle email in automatico: il GET
    // non deve disiscrivere nessuno.
    $contact = activeContact();

    $this->get(unsubscribeUrl($contact))
        ->assertOk()
        ->assertSee('Conferma disiscrizione')
        ->assertSee($contact->email);

    expect($contact->fresh()->status)->toBe('active');
    expect(SuppressionList::where('email', $contact->email)->exists())->toBeFalse();
});

test('confirming unsubscribes the contact and adds it to the suppression list', function () {
    $contact = activeContact();

    $this->post(unsubscribeUrl($contact))->assertOk()->assertSee('Disiscrizione confermata');

    $contact->refresh();
    expect($contact->status)->toBe('unsubscribed');
    expect($contact->unsubscribed_at)->not->toBeNull();

    $suppressed = SuppressionList::where('user_id', $contact->user_id)->where('email', $contact->email)->first();
    expect($suppressed)->not->toBeNull();
    expect($suppressed->reason)->toBe('unsubscribe');
});

test('one-click unsubscribe from the mail client works with the RFC 8058 body', function () {
    $contact = activeContact();

    $this->post(unsubscribeUrl($contact), ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($contact->fresh()->status)->toBe('unsubscribed');
});

test('an unsigned or tampered link is rejected', function () {
    $contact = activeContact();

    $this->get(route('newsletter.unsubscribe', ['contact' => $contact->id]))->assertForbidden();
    $this->post(route('newsletter.unsubscribe.confirm', ['contact' => $contact->id]))->assertForbidden();
    $this->post(unsubscribeUrl($contact).'tampered')->assertForbidden();

    expect($contact->fresh()->status)->toBe('active');
});

test('confirming twice is idempotent and the page then shows the done state', function () {
    $contact = activeContact();
    $url = unsubscribeUrl($contact);

    $this->post($url)->assertOk();
    $this->post($url)->assertOk();
    $this->get($url)->assertOk()->assertSee('Disiscrizione confermata');

    expect(SuppressionList::where('user_id', $contact->user_id)->where('email', $contact->email)->count())->toBe(1);
});

test('the signature Laravel generates matches the python replication algorithm', function () {
    // publisher/integrations/unsubscribe.py replica
    // hash_hmac('sha256', '/disiscrivi/{id}', APP_KEY grezza): se cambiasse
    // la config URL signing di Laravel, questo test lo becca.
    $contact = activeContact();

    parse_str(parse_url(unsubscribeUrl($contact), PHP_URL_QUERY), $query);
    expect($query['signature'])->toBe(hash_hmac('sha256', "/disiscrivi/{$contact->id}", config('app.key')));
});
