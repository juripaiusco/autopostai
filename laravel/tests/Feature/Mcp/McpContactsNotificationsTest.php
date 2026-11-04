<?php

use App\Mcp\Servers\FaPer3Server;
use App\Mcp\Tools\CreateContact;
use App\Mcp\Tools\CreatePushNotification;
use App\Mcp\Tools\DeleteContact;
use App\Mcp\Tools\DeletePushNotification;
use App\Mcp\Tools\ListContacts;
use App\Mcp\Tools\ListPushNotifications;
use App\Mcp\Tools\UpdateContact;
use App\Mcp\Tools\UpdatePushNotification;
use App\Models\Contact;
use App\Models\PushNotification;
use App\Models\Settings;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Utente semplice con newsletter SMTP attiva (modulo Contatti visibile). */
function mcpSmtpAccount(User $parent): User
{
    $account = User::factory()->create(['parent_id' => $parent->id, 'channels' => ['newsletter' => ['on' => true]]]);
    Settings::factory()->create(['user_id' => $account->id, 'nl_smtp_host' => 'smtp.test.it']);

    return $account->fresh();
}

// --- Contatti ---

it('hides contact tools from users without the SMTP newsletter', function () {
    ['alice' => $alice] = mcpTree();

    FaPer3Server::actingAs(withMcp($alice, 'write'))->tool(CreateContact::class, [])->assertNotRegistered();
    FaPer3Server::actingAs(withMcp($alice->fresh(), 'write'))->tool(ListContacts::class, [])->assertNotRegistered();
});

it('create_contact adds a contact with mcp as consent source and tags', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = mcpSmtpAccount($admin);

    FaPer3Server::actingAs(withMcp($account, 'write'))->tool(CreateContact::class, [
        'email' => 'mario@example.com', 'status' => 'active', 'tags' => ['clienti', 'vip'],
    ])->assertOk()->assertSee('"consent_source":"mcp"');

    $contact = Contact::firstOrFail();
    expect($contact->user_id)->toBe($account->id)
        ->and($contact->tags->pluck('name')->sort()->values()->all())->toBe(['clienti', 'vip']);
});

it('create_contact makes admins pick an eligible account and refuses suppressed addresses', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = mcpSmtpAccount($admin);
    SuppressionList::create(['user_id' => $account->id, 'email' => 'via@example.com', 'reason' => 'unsubscribe']);

    FaPer3Server::actingAs(withMcp($admin, 'write'))->tool(CreateContact::class, ['email' => 'x@example.com', 'status' => 'active'])
        ->assertHasErrors(['Account non valido.']);

    FaPer3Server::actingAs(withMcp($admin->fresh(), 'write'))->tool(CreateContact::class, [
        'account_id' => $account->id, 'email' => 'via@example.com', 'status' => 'active',
    ])->assertHasErrors(['Questo indirizzo non può essere riattivato: si è disiscritto.']);

    expect(Contact::count())->toBe(0);
});

it('update_contact changes only the given fields', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = mcpSmtpAccount($admin);
    $contact = Contact::factory()->create(['user_id' => $account->id, 'email' => 'a@example.com', 'status' => 'active']);

    FaPer3Server::actingAs(withMcp($account, 'write'))->tool(UpdateContact::class, ['contact_id' => $contact->id, 'status' => 'unsubscribed'])
        ->assertOk();

    expect($contact->fresh())->status->toBe('unsubscribed')->email->toBe('a@example.com');
});

it('create_contact and update_contact handle first and last name', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = mcpSmtpAccount($admin);

    FaPer3Server::actingAs(withMcp($account, 'write'))->tool(CreateContact::class, [
        'email' => 'anna@example.com', 'first_name' => 'Anna', 'last_name' => 'Neri', 'status' => 'active',
    ])->assertOk()->assertSee('"first_name":"Anna"');

    $contact = Contact::firstOrFail();

    // Nome omesso: resta quello di prima; cognome esplicitamente null: cancellato.
    FaPer3Server::actingAs(withMcp($account->fresh(), 'write'))->tool(UpdateContact::class, [
        'contact_id' => $contact->id, 'last_name' => null,
    ])->assertOk();

    expect($contact->fresh())->first_name->toBe('Anna')->last_name->toBeNull();
});

it('contact tools cannot touch contacts of other accounts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $mine = mcpSmtpAccount($admin);
    $theirs = mcpSmtpAccount($admin);
    $contact = Contact::factory()->create(['user_id' => $theirs->id]);

    FaPer3Server::actingAs(withMcp($mine, 'write'))->tool(DeleteContact::class, ['contact_id' => $contact->id])
        ->assertHasErrors(['Contatto non trovato.']);

    expect(Contact::find($contact->id))->not->toBeNull();
});

it('delete_contact soft-deletes', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $account = mcpSmtpAccount($admin);
    $contact = Contact::factory()->create(['user_id' => $account->id]);

    FaPer3Server::actingAs(withMcp($account, 'write'))->tool(DeleteContact::class, ['contact_id' => $contact->id])->assertOk();

    expect(Contact::find($contact->id))->toBeNull()
        ->and(Contact::withTrashed()->find($contact->id))->not->toBeNull();
});

// --- Notifiche push ---

it('hides notification tools from simple users', function () {
    ['alice' => $alice] = mcpTree();

    FaPer3Server::actingAs(withMcp($alice, 'publish'))->tool(ListPushNotifications::class, [])->assertNotRegistered();
    FaPer3Server::actingAs(withMcp($alice->fresh(), 'publish'))->tool(CreatePushNotification::class, [])->assertNotRegistered();
});

it('create_push_notification queues a notification for the manager\'s users', function () {
    ['manager' => $manager] = mcpTree();

    FaPer3Server::actingAs(withMcp($manager, 'publish'))->tool(CreatePushNotification::class, [
        'title' => 'Novità', 'body' => 'Nuove funzioni', 'url' => '/posts', 'recipient_type' => 'children',
    ])->assertOk()->assertSee('"status":"in coda"');

    expect(PushNotification::firstOrFail())
        ->audience->toBe('children')
        ->created_by_user_id->toBe($manager->id)
        ->sent_at->toBeNull();
});

it('create_push_notification accepts only links inside FaPer3', function () {
    ['manager' => $manager] = mcpTree();

    foreach (['https://phishing.example/login', '//phishing.example', 'javascript:alert(1)'] as $url) {
        FaPer3Server::actingAs(withMcp($manager->fresh(), 'publish'))->tool(CreatePushNotification::class, [
            'title' => 'x', 'body' => 'y', 'url' => $url, 'recipient_type' => 'children',
        ])->assertHasErrors();
    }

    FaPer3Server::actingAs(withMcp($manager->fresh(), 'publish'))->tool(CreatePushNotification::class, [
        'title' => 'x', 'body' => 'y', 'url' => rtrim(config('app.url'), '/').'/calendarizza', 'recipient_type' => 'children',
    ])->assertOk();

    expect(PushNotification::count())->toBe(1);
});

it('create_push_notification keeps the UI recipient rules', function () {
    ['manager' => $manager, 'other' => $other] = mcpTree();

    FaPer3Server::actingAs(withMcp($manager, 'publish'))->tool(CreatePushNotification::class, [
        'title' => 'x', 'body' => 'y', 'recipient_type' => 'all',
    ])->assertHasErrors();

    FaPer3Server::actingAs(withMcp($manager->fresh(), 'publish'))->tool(CreatePushNotification::class, [
        'title' => 'x', 'body' => 'y', 'recipient_type' => 'user', 'user_id' => $other->id,
    ])->assertHasErrors(['Destinatario non valido.']);

    expect(PushNotification::count())->toBe(0);
});

it('update_push_notification edits queued notifications only', function () {
    ['manager' => $manager] = mcpTree();
    $queued = PushNotification::create(['created_by_user_id' => $manager->id, 'audience' => 'children', 'title' => 'Prima', 'body' => 'b']);
    $sent = PushNotification::create(['created_by_user_id' => $manager->id, 'audience' => 'children', 'title' => 'Inviata', 'body' => 'b', 'sent_at' => now()]);

    FaPer3Server::actingAs(withMcp($manager, 'publish'))->tool(UpdatePushNotification::class, ['notification_id' => $queued->id, 'title' => 'Dopo'])
        ->assertOk();
    expect($queued->fresh())->title->toBe('Dopo')->body->toBe('b')->audience->toBe('children');

    FaPer3Server::actingAs(withMcp($manager->fresh(), 'publish'))->tool(UpdatePushNotification::class, ['notification_id' => $sent->id, 'title' => 'x'])
        ->assertHasErrors(['Questa notifica è già stata inviata.']);
});

it('notification tools only see the manager\'s own notifications', function () {
    ['admin' => $admin, 'manager' => $manager] = mcpTree();
    $adminOnes = PushNotification::create(['created_by_user_id' => $admin->id, 'audience' => 'all', 'title' => 'Dell\'admin', 'body' => 'b']);

    FaPer3Server::actingAs(withMcp($manager, 'read'))->tool(ListPushNotifications::class)
        ->assertOk()->assertDontSee('Dell\'admin');

    FaPer3Server::actingAs(withMcp($manager->fresh(), 'publish'))->tool(DeletePushNotification::class, ['notification_id' => $adminOnes->id])
        ->assertHasErrors(['Notifica non trovata.']);

    expect(PushNotification::find($adminOnes->id))->not->toBeNull();
});
