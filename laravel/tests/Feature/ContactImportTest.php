<?php

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Settings;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function importAccount(): User
{
    $admin = User::factory()->create(['parent_id' => null]);
    $account = User::factory()->create(['parent_id' => $admin->id, 'channels' => ['newsletter' => ['on' => true]]]);
    Settings::factory()->create(['user_id' => $account->id, 'nl_smtp_host' => 'smtp.test.it']);

    return $account->fresh();
}

/** CSV come quello dell'utente: nome;cognome;tag;email + colonne non pertinenti. */
function importPayload(User $account, string $csv, array $overrides = []): array
{
    return array_replace_recursive([
        'user_id' => $account->id,
        'file' => UploadedFile::fake()->createWithContent('rubrica.csv', $csv),
        'delimiter' => ';',
        'has_header' => 1,
        'status' => 'active',
        'map' => ['email' => 3, 'first_name' => 0, 'last_name' => 1, 'tags' => 2],
    ], $overrides);
}

test('imports rows with names and comma separated tags, ignoring extra columns', function () {
    $account = importAccount();
    $csv = "nome;cognome;tag;email;telefono\n"
        ."Mario;Rossi;clienti, vip;mario@example.com;333\n"
        ."Giulia;Bianchi;;giulia@example.com;444\n";

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, $csv))
        ->assertRedirect(route('contacts'))
        ->assertSessionHas('toast', 'Importati 2 contatti.');

    $mario = Contact::where('email', 'mario@example.com')->firstOrFail();
    expect($mario)->first_name->toBe('Mario')->last_name->toBe('Rossi')->status->toBe('active')->consent_source->toBe('import')
        ->and($mario->tags->pluck('name')->sort()->values()->all())->toBe(['clienti', 'vip'])
        ->and(Contact::where('email', 'giulia@example.com')->first()->tags)->toHaveCount(0)
        ->and(ContactTag::where('user_id', $account->id)->count())->toBe(2);
});

test('existing contacts are not duplicated but get the new tags', function () {
    $account = importAccount();
    $existing = Contact::factory()->create(['user_id' => $account->id, 'email' => 'mario@example.com', 'first_name' => 'Marione', 'status' => 'unverified']);
    $existing->tags()->attach(ContactTag::create(['user_id' => $account->id, 'name' => 'vecchio'])->id);

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, "nome;cognome;tag;email\nMario;Rossi;nuovo;MARIO@example.com\n"))
        ->assertSessionHas('toast', 'Importati 0 contatti · 1 già presenti (tag aggiunti).');

    $existing->refresh();
    expect(Contact::count())->toBe(1)
        ->and($existing)->first_name->toBe('Marione')->status->toBe('unverified')
        ->and($existing->tags->pluck('name')->sort()->values()->all())->toBe(['nuovo', 'vecchio']);
});

test('soft-deleted contacts are restored with the file data', function () {
    $account = importAccount();
    $deleted = Contact::factory()->create(['user_id' => $account->id, 'email' => 'back@example.com', 'status' => 'active']);
    $deleted->delete();

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, "nome;cognome;tag;email\nAnna;Neri;;back@example.com\n", ['status' => 'unverified']));

    expect($deleted->fresh())->trashed()->toBeFalse()->first_name->toBe('Anna')->status->toBe('unverified')
        ->and(Contact::withTrashed()->count())->toBe(1);
});

test('skips suppressed and invalid addresses and in-file duplicates', function () {
    $account = importAccount();
    SuppressionList::create(['user_id' => $account->id, 'email' => 'via@example.com', 'reason' => 'unsubscribe']);
    $csv = "nome;cognome;tag;email\n"
        ."A;;;via@example.com\n"
        ."B;;;non-una-email\n"
        ."C;;;\n"
        .";;;\n"
        ."D;;;doppio@example.com\n"
        ."E;;;Doppio@example.com\n";

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, $csv))
        ->assertSessionHas('toast', 'Importato 1 contatto · 1 saltati (disiscritti o bounce) · 2 scartati (email non valida).');

    expect(Contact::pluck('email')->all())->toBe(['doppio@example.com'])
        ->and(Contact::first()->first_name)->toBe('D');
});

test('handles no header, comma or tab delimiter, BOM and Windows-1252 accents', function () {
    $account = importAccount();
    $csv = "\xEF\xBB\xBFluca@example.com,Luca,Verdi\n";

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, $csv, [
        'delimiter' => ',', 'has_header' => 0, 'map' => ['email' => 0, 'first_name' => 1, 'last_name' => 2, 'tags' => null],
    ]))->assertRedirect(route('contacts'));

    expect(Contact::where('email', 'luca@example.com')->value('last_name'))->toBe('Verdi');

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, "nic@example.com\tNicol\xF2\n", [
        'delimiter' => 'tab', 'has_header' => 0, 'map' => ['email' => 0, 'first_name' => 1, 'last_name' => null, 'tags' => null],
    ]))->assertRedirect(route('contacts'));

    expect(Contact::where('email', 'nic@example.com')->value('first_name'))->toBe('Nicolò');
});

test('rejects an account without smtp custom and a missing email column', function () {
    $account = importAccount();
    $other = User::factory()->create(['parent_id' => $account->parent_id]);

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, "a;b;c;x@example.com\n", ['user_id' => $other->id]))
        ->assertStatus(422);

    $payload = importPayload($account, "a;b;c;x@example.com\n");
    unset($payload['map']['email']);
    $this->actingAs($account)->post(route('contacts.import'), $payload)->assertInvalid(['map.email']);

    $this->actingAs($account)->post(route('contacts.import'), importPayload($account, "x", [
        'file' => UploadedFile::fake()->create('foto.png', 10),
    ]))->assertInvalid(['file']);

    expect(Contact::count())->toBe(0);
});
