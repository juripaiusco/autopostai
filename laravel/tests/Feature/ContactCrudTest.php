<?php

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeSmtpCustomAccount(User $parent): User
{
    // Deve avere un parent: un utente con parent_id null è admin, e
    // eligibleAccounts() esclude sempre l'admin stesso dai target possibili
    // (stessa regola di Post::create) — qui vogliamo un account "foglia".
    $account = User::factory()->create(['parent_id' => $parent->id]);
    Settings::factory()->create(['user_id' => $account->id, 'nl_smtp_host' => 'smtp.test.it']);
    $account->channels = ['newsletter' => ['on' => true]];
    $account->save();

    return $account->fresh();
}

test('create shows only accounts with smtp custom active as eligible', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $eligible = makeSmtpCustomAccount($admin);
    $ineligible = User::factory()->create(['parent_id' => $admin->id]);

    $this->actingAs($admin)->get(route('contacts.create'))->assertInertia(fn (Assert $page) => $page
        ->component('Contacts/Form')
        ->has('accounts', 1)
        ->where('accounts.0.id', $eligible->id)
    );
});

test('store creates a contact and syncs tags, creating new tags on the fly', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = makeSmtpCustomAccount($admin);

    $this->actingAs($account)->post(route('contacts.store'), [
        'user_id' => $account->id,
        'email' => 'nuovo@example.com',
        'status' => 'active',
        'tags' => ['clienti', 'lead'],
    ])->assertRedirect(route('contacts'));

    $contact = Contact::where('email', 'nuovo@example.com')->firstOrFail();
    expect($contact->status)->toBe('active');
    expect($contact->tags->pluck('name')->sort()->values()->all())->toBe(['clienti', 'lead']);
    expect(ContactTag::where('user_id', $account->id)->count())->toBe(2);
});

test('store rejects a target account without smtp custom active', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $ineligible = User::factory()->create(['parent_id' => $admin->id]);

    $this->actingAs($admin)->post(route('contacts.store'), [
        'user_id' => $ineligible->id,
        'email' => 'x@example.com',
        'status' => 'active',
    ])->assertStatus(422);
});

test('store rejects a duplicate active email for the same account', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = makeSmtpCustomAccount($admin);
    Contact::factory()->create(['user_id' => $account->id, 'email' => 'dup@example.com']);

    $this->actingAs($account)->post(route('contacts.store'), [
        'user_id' => $account->id,
        'email' => 'dup@example.com',
        'status' => 'active',
    ])->assertInvalid(['email']);
});

test('store restores a previously soft-deleted contact instead of failing', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = makeSmtpCustomAccount($admin);
    $deleted = Contact::factory()->create(['user_id' => $account->id, 'email' => 'back@example.com']);
    $deleted->delete();

    $this->actingAs($account)->post(route('contacts.store'), [
        'user_id' => $account->id,
        'email' => 'back@example.com',
        'status' => 'active',
    ])->assertRedirect(route('contacts'));

    expect(Contact::where('email', 'back@example.com')->count())->toBe(1);
    expect(Contact::where('email', 'back@example.com')->first()->trashed())->toBeFalse();
});

test('update edits fields and tags, rejects a duplicate email', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = makeSmtpCustomAccount($admin);
    $contact = Contact::factory()->create(['user_id' => $account->id, 'email' => 'a@example.com', 'status' => 'unverified']);
    $other = Contact::factory()->create(['user_id' => $account->id, 'email' => 'b@example.com']);

    $this->actingAs($account)->put(route('contacts.update', $contact), [
        'email' => 'a-updated@example.com',
        'status' => 'active',
        'tags' => ['vip'],
    ])->assertRedirect(route('contacts'));

    $contact->refresh();
    expect($contact->email)->toBe('a-updated@example.com');
    expect($contact->status)->toBe('active');
    expect($contact->tags->pluck('name')->all())->toBe(['vip']);

    $this->actingAs($account)->put(route('contacts.update', $contact), [
        'email' => 'b@example.com',
        'status' => 'active',
    ])->assertInvalid(['email']);
});

test('destroy soft deletes a contact, blocked for an unrelated manager', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $account = makeSmtpCustomAccount($managerA);
    $contact = Contact::factory()->create(['user_id' => $account->id]);

    $this->actingAs($managerB)->delete(route('contacts.destroy', $contact))->assertForbidden();
    expect($contact->fresh())->not->toBeNull();

    $this->actingAs($managerA)->delete(route('contacts.destroy', $contact))->assertRedirect();
    expect($contact->fresh()->trashed())->toBeTrue();
});

test('index respects the same visibleTo scoping as posts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $accountA = makeSmtpCustomAccount($admin);
    $accountB = makeSmtpCustomAccount($admin);
    Contact::factory()->create(['user_id' => $accountA->id]);
    Contact::factory()->create(['user_id' => $accountB->id]);

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $accountA->id])
        ->get(route('contacts'))
        ->assertInertia(fn (Assert $page) => $page->has('contacts.data', 1));
});
