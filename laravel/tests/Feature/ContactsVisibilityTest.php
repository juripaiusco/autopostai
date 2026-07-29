<?php

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function withNewsletterChannel(User $user, bool $on): User
{
    // 'channels' non è fillable su User (mass-assignment volutamente ristretto
    // altrove): va scritto direttamente sull'attributo, non via update().
    $user->channels = ['newsletter' => ['on' => $on]];
    $user->save();

    return $user->fresh();
}

test('newsletter provider precedence is mailchimp over brevo over smtp custom', function () {
    $settings = Settings::factory()->make([
        'nl_mailchimp_api' => 'mc-key',
        'nl_brevo_api' => 'brevo-key',
        'nl_smtp_host' => 'smtp.test.it',
    ]);
    expect($settings->newsletterProvider())->toBe('mailchimp');

    $settings = Settings::factory()->make([
        'nl_brevo_api' => 'brevo-key',
        'nl_smtp_host' => 'smtp.test.it',
    ]);
    expect($settings->newsletterProvider())->toBe('brevo');

    $settings = Settings::factory()->make(['nl_smtp_host' => 'smtp.test.it']);
    expect($settings->newsletterProvider())->toBe('smtp_custom');

    $settings = Settings::factory()->make();
    expect($settings->newsletterProvider())->toBeNull();
});

test('hasSmtpCustomActive requires both the channel toggle and the smtp provider', function () {
    $user = User::factory()->create(['parent_id' => null]);
    Settings::factory()->create(['user_id' => $user->id, 'nl_smtp_host' => 'smtp.test.it']);

    $user = withNewsletterChannel($user, true);
    expect($user->hasSmtpCustomActive())->toBeTrue();

    $off = withNewsletterChannel($user, false);
    expect($off->hasSmtpCustomActive())->toBeFalse();
});

test('hasSmtpCustomActive is false when a mailchimp/brevo list is configured instead', function () {
    $user = User::factory()->create(['parent_id' => null]);
    Settings::factory()->create([
        'user_id' => $user->id,
        'nl_mailchimp_api' => 'mc-key',
        'nl_smtp_host' => 'smtp.test.it',
    ]);
    $user = withNewsletterChannel($user, true);

    expect($user->hasSmtpCustomActive())->toBeFalse();
});

test('contacts route is blocked and hidden from sidebar when smtp custom is not active', function () {
    $user = User::factory()->create(['parent_id' => null, 'child_on' => 1]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('contactsEnabled', false)
    );

    $this->actingAs($user)->get(route('contacts'))->assertForbidden();
});

test('contacts route is reachable and shared prop is true when smtp custom is active', function () {
    $user = User::factory()->create(['parent_id' => null]);
    Settings::factory()->create(['user_id' => $user->id, 'nl_smtp_host' => 'smtp.test.it']);
    withNewsletterChannel($user, true);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('contactsEnabled', true)
    );

    $this->actingAs($user)->get(route('contacts'))->assertOk();
});

test('admin scoped to a user with smtp custom active can reach contacts, scoped elsewhere cannot', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $enabled = User::factory()->create(['parent_id' => null]);
    $disabled = User::factory()->create(['parent_id' => null]);
    Settings::factory()->create(['user_id' => $enabled->id, 'nl_smtp_host' => 'smtp.test.it']);
    withNewsletterChannel($enabled, true);

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $enabled->id])
        ->get(route('contacts'))
        ->assertOk();

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $disabled->id])
        ->get(route('contacts'))
        ->assertForbidden();
});
