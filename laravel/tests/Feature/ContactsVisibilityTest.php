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

test('plain user can reach contacts when their own smtp custom is active', function () {
    $manager = User::factory()->create(['parent_id' => null]);
    $plain = User::factory()->create(['parent_id' => $manager->id]);
    Settings::factory()->create(['user_id' => $plain->id, 'nl_smtp_host' => 'smtp.test.it']);
    withNewsletterChannel($plain, true);

    $this->actingAs($plain)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('contactsEnabled', true)
    );

    $this->actingAs($plain)->get(route('contacts'))->assertOk();
});

test('plain user cannot reach contacts without their own smtp custom active', function () {
    $manager = User::factory()->create(['parent_id' => null]);
    $plain = User::factory()->create(['parent_id' => $manager->id]);

    $this->actingAs($plain)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('contactsEnabled', false)
    );

    $this->actingAs($plain)->get(route('contacts'))->assertForbidden();
});

test('plain user cannot reach contacts when only mailchimp/brevo is configured for them', function () {
    $manager = User::factory()->create(['parent_id' => null]);
    $plain = User::factory()->create(['parent_id' => $manager->id]);
    Settings::factory()->create(['user_id' => $plain->id, 'nl_brevo_api' => 'brevo-key']);
    withNewsletterChannel($plain, true);

    $this->actingAs($plain)->get(route('contacts'))->assertForbidden();
});

test('admin can always reach contacts, regardless of their own smtp custom setting', function () {
    $admin = User::factory()->create(['parent_id' => null]);

    $this->actingAs($admin)->get(route('contacts'))->assertOk();
});

test('manager can always reach contacts, regardless of their own smtp custom setting', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);

    $this->actingAs($manager)->get(route('contacts'))->assertOk();
});

test('admin scoped to a user with smtp custom active can reach contacts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $target = User::factory()->create(['parent_id' => null]);
    Settings::factory()->create(['user_id' => $target->id, 'nl_smtp_host' => 'smtp.test.it']);
    withNewsletterChannel($target, true);

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $target->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('contactsEnabled', true));

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $target->id])
        ->get(route('contacts'))
        ->assertOk();
});

test('admin scoped to a user using mailchimp/brevo cannot reach contacts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $target = User::factory()->create(['parent_id' => null]);
    Settings::factory()->create(['user_id' => $target->id, 'nl_mailchimp_api' => 'mc-key']);
    withNewsletterChannel($target, true);

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $target->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('contactsEnabled', false));

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $target->id])
        ->get(route('contacts'))
        ->assertForbidden();
});

test('admin scoped to a user with no newsletter provider configured cannot reach contacts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $target = User::factory()->create(['parent_id' => null]);

    $this->actingAs($admin)
        ->withSession(['scoped_user_id' => $target->id])
        ->get(route('contacts'))
        ->assertForbidden();
});
