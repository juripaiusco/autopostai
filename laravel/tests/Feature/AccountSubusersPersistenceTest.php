<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating an account with canSubusers checked persists child_on and the subusers limit', function () {
    $admin = User::factory()->create(['parent_id' => null]);

    $this->actingAs($admin)->post(route('account.store'), [
        'name' => 'Nuovo Manager',
        'email' => 'nuovo-manager@example.com',
        'password' => 'password123',
        'canSubusers' => true,
        'subusersLimit' => '10',
        'tokensMonth' => '50000',
        'imagesDay' => '20',
    ])->assertRedirect(route('account'));

    $created = User::where('email', 'nuovo-manager@example.com')->firstOrFail();

    expect($created->child_on)->toBe(1);
    expect($created->child_max)->toBe(10);
    expect($created->tokens_limit)->toBe(50000);
    expect($created->image_model_limit)->toBe(20);
    expect($created->isManager())->toBeTrue();
});

test('updating an account to check canSubusers persists child_on, unchecking clears it', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $child = User::factory()->create(['parent_id' => $admin->id]);

    $this->actingAs($admin)->put(route('account.update', $child), [
        'name' => $child->name,
        'email' => $child->email,
        'canSubusers' => true,
        'subusersLimit' => '5',
        'tokensMonth' => '30000',
        'imagesDay' => '15',
    ])->assertRedirect();

    $child->refresh();
    expect($child->child_on)->toBe(1);
    expect($child->child_max)->toBe(5);
    expect($child->tokens_limit)->toBe(30000);
    expect($child->image_model_limit)->toBe(15);
    expect($child->isManager())->toBeTrue();

    $this->actingAs($admin)->put(route('account.update', $child), [
        'name' => $child->name,
        'email' => $child->email,
        'canSubusers' => false,
    ])->assertRedirect();

    $child->refresh();
    expect($child->child_on)->toBeNull();
    expect($child->isManager())->toBeFalse();
});

test('admin reassigning the manager dropdown persists the new parent_id', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $plain = User::factory()->create(['parent_id' => $managerA->id]);

    $this->actingAs($admin)->put(route('account.update', $plain), [
        'name' => $plain->name,
        'email' => $plain->email,
        'canSubusers' => false,
        'manager' => (string) $managerB->id,
    ])->assertRedirect();

    expect($plain->fresh()->parent_id)->toBe($managerB->id);
});

test('manager cannot reassign the manager dropdown for their own child', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $managerB = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $plain = User::factory()->create(['parent_id' => $managerA->id]);

    $this->actingAs($managerA)->put(route('account.update', $plain), [
        'name' => $plain->name,
        'email' => $plain->email,
        'canSubusers' => false,
        'manager' => (string) $managerB->id,
    ])->assertRedirect();

    expect($plain->fresh()->parent_id)->toBe($managerA->id);
});

test('an empty manager selection never nulls parent_id (would otherwise promote to admin)', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $managerA = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $plain = User::factory()->create(['parent_id' => $managerA->id]);

    $this->actingAs($admin)->put(route('account.update', $plain), [
        'name' => $plain->name,
        'email' => $plain->email,
        'canSubusers' => false,
        'manager' => '',
    ])->assertRedirect();

    expect($plain->fresh()->parent_id)->toBe($managerA->id);
});

test('an invalid manager id is rejected', function () {
    $admin = User::factory()->create(['parent_id' => null]);
    $plain = User::factory()->create(['parent_id' => $admin->id]);
    $unrelatedPlain = User::factory()->create(['parent_id' => $admin->id]);

    $this->actingAs($admin)->put(route('account.update', $plain), [
        'name' => $plain->name,
        'email' => $plain->email,
        'canSubusers' => false,
        'manager' => (string) $unrelatedPlain->id,
    ])->assertStatus(422);
});
