<?php

use App\Models\Contact;
use App\Models\Settings;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function accountWithApiKey(): array
{
    $admin = User::factory()->create(['parent_id' => null]);
    $account = User::factory()->create(['parent_id' => $admin->id]);
    $settings = Settings::factory()->create(['user_id' => $account->id, 'nl_smtp_host' => 'smtp.test.it']);
    $account->channels = ['newsletter' => ['on' => true]];
    $account->save();

    $plaintext = $settings->generateContactsApiKey();

    return [$account, $plaintext];
}

test('regenerate requires admin/manager authorization and returns a one-shot plaintext key', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = User::factory()->create(['parent_id' => $admin->id]);

    $response = $this->actingAs($admin)->post(route('contacts.api-key.regenerate', $account));
    $response->assertRedirect();
    $response->assertSessionHas('contacts_api_key_plaintext');

    $settings = $account->settings()->first();
    expect($settings->contacts_api_key_hash)->not->toBeNull();
    expect(strlen($settings->contacts_api_key_last_four))->toBe(4);

    $unrelatedManager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $this->actingAs($unrelatedManager)->post(route('contacts.api-key.regenerate', $account))->assertForbidden();
});

test('the edit page exposes the plaintext key only once, right after regeneration', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = User::factory()->create(['parent_id' => $admin->id]);
    Settings::factory()->create(['user_id' => $account->id, 'nl_smtp_host' => 'smtp.test.it']);
    $account->channels = ['newsletter' => ['on' => true]];
    $account->save();

    $this->actingAs($admin)->post(route('contacts.api-key.regenerate', $account));

    $this->actingAs($admin)->get(route('account.edit', $account))
        ->assertInertia(fn ($page) => $page->where('account.contactsApi.plaintext', fn ($v) => !empty($v)));

    // Seconda visita: la sessione flash è scaduta, il plaintext non c'è più.
    $this->actingAs($admin)->get(route('account.edit', $account))
        ->assertInertia(fn ($page) => $page->where('account.contactsApi.plaintext', null));
});

test('api rejects requests without a valid key', function () {
    $this->postJson('/api/contacts', ['email' => 'x@example.com'])->assertStatus(401);
    $this->postJson('/api/contacts', ['email' => 'x@example.com'], ['X-Api-Key' => 'not-a-real-key'])->assertStatus(401);
});

test('api creates a new active contact for the authenticated account', function () {
    [$account, $key] = accountWithApiKey();

    $this->postJson('/api/contacts', ['email' => 'nuovo@example.com'], ['X-Api-Key' => $key])
        ->assertStatus(201)
        ->assertJson(['status' => 'created', 'contact' => ['email' => 'nuovo@example.com', 'status' => 'active']]);

    $contact = Contact::where('user_id', $account->id)->where('email', 'nuovo@example.com')->firstOrFail();
    expect($contact->consent_source)->toBe('api');
    expect($contact->consent_ip)->not->toBeNull();
});

test('api is idempotent for an existing active contact', function () {
    [$account, $key] = accountWithApiKey();
    Contact::factory()->create(['user_id' => $account->id, 'email' => 'gia@example.com', 'status' => 'active']);

    $this->postJson('/api/contacts', ['email' => 'gia@example.com'], ['X-Api-Key' => $key])
        ->assertStatus(200)
        ->assertJson(['status' => 'exists']);

    expect(Contact::where('user_id', $account->id)->where('email', 'gia@example.com')->count())->toBe(1);
});

test('api restores a soft-deleted contact instead of failing', function () {
    [$account, $key] = accountWithApiKey();
    $deleted = Contact::factory()->create(['user_id' => $account->id, 'email' => 'back@example.com']);
    $deleted->delete();

    $this->postJson('/api/contacts', ['email' => 'back@example.com'], ['X-Api-Key' => $key])
        ->assertStatus(200)
        ->assertJson(['status' => 'restored']);

    expect(Contact::where('email', 'back@example.com')->first()->trashed())->toBeFalse();
});

test('api rejects an email in the suppression list for hard_bounce or complaint', function () {
    [$account, $key] = accountWithApiKey();
    SuppressionList::create(['user_id' => $account->id, 'email' => 'bounced@example.com', 'reason' => 'hard_bounce']);

    $this->postJson('/api/contacts', ['email' => 'bounced@example.com'], ['X-Api-Key' => $key])
        ->assertStatus(422)
        ->assertJson(['reason' => 'hard_bounce']);

    expect(Contact::where('user_id', $account->id)->where('email', 'bounced@example.com')->exists())->toBeFalse();
});

test('api registers a suppressed unsubscribe as an unsubscribed contact instead of rejecting', function () {
    [$account, $key] = accountWithApiKey();
    SuppressionList::create(['user_id' => $account->id, 'email' => 'byebye@example.com', 'reason' => 'unsubscribe']);

    $this->postJson('/api/contacts', ['email' => 'byebye@example.com'], ['X-Api-Key' => $key])
        ->assertStatus(201)
        ->assertJson(['status' => 'suppressed_unsubscribed', 'contact' => ['status' => 'unsubscribed']]);
});

test('api rejects a syntactically invalid email', function () {
    [$account, $key] = accountWithApiKey();

    $this->postJson('/api/contacts', ['email' => 'not-an-email'], ['X-Api-Key' => $key])
        ->assertStatus(422);
});

test('api rejects an email whose domain has no MX record', function () {
    [$account, $key] = accountWithApiKey();

    $this->postJson('/api/contacts', ['email' => 'someone@this-domain-does-not-exist-faper3-test.invalid'], ['X-Api-Key' => $key])
        ->assertStatus(422);
});
