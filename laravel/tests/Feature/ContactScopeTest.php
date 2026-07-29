<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeContact(User $owner, array $overrides = []): Contact
{
    return Contact::factory()->create(array_merge(['user_id' => $owner->id], $overrides));
}

test('admin with no scope sees contacts of every user', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $userA = User::factory()->create(['parent_id' => null]);
    $userB = User::factory()->create(['parent_id' => null]);
    makeContact($userA);
    makeContact($userB);

    expect(Contact::query()->visibleTo($admin)->count())->toBe(2);
});

test('admin scoped to a user sees only that user\'s contacts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $target = User::factory()->create(['parent_id' => null]);
    $other = User::factory()->create(['parent_id' => null]);
    $targetContact = makeContact($target);
    makeContact($other);

    $visible = Contact::query()->visibleTo($admin, $target->id)->get();

    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($targetContact->id);
});

test('manager with no scope sees only their children\'s contacts', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $child = User::factory()->create(['parent_id' => $manager->id]);
    $stranger = User::factory()->create(['parent_id' => $admin->id]);
    $childContact = makeContact($child);
    makeContact($stranger);

    $visible = Contact::query()->visibleTo($manager)->get();

    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($childContact->id);
});

test('plain user with no scope sees only their own contacts', function () {
    $userA = User::factory()->create(['parent_id' => null, 'child_on' => 1]);
    $plain = User::factory()->create(['parent_id' => $userA->id]);
    $sibling = User::factory()->create(['parent_id' => $userA->id]);
    $ownContact = makeContact($plain);
    makeContact($sibling);

    $visible = Contact::query()->visibleTo($plain)->get();

    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($ownContact->id);
});
