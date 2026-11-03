<?php

use App\Models\McpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function mcpSettingsUser(): User
{
    $admin = User::factory()->create(['parent_id' => null]);

    return User::factory()->create(['parent_id' => $admin->id]);
}

it('creates a token shown once and stores only its hash', function () {
    $user = mcpSettingsUser();

    $this->actingAs($user)->post(route('settings.mcp-tokens.store'), [
        'name' => 'Claude Code',
        'ability' => 'write',
        'expiry_days' => 90,
    ])->assertRedirect()->assertSessionHas('mcp_token_plaintext');

    $plain = session('mcp_token_plaintext');
    $token = McpToken::firstOrFail();

    expect($plain)->toStartWith('fp3_')
        ->and($token->token_hash)->toBe(hash('sha256', $plain))
        ->and($token->abilities)->toBe(['read', 'write'])
        ->and($token->expires_at->isSameDay(now()->addDays(90)))->toBeTrue();
});

it('rejects unknown abilities and expiries', function () {
    $user = mcpSettingsUser();

    $this->actingAs($user)->post(route('settings.mcp-tokens.store'), [
        'name' => 'x', 'ability' => 'admin', 'expiry_days' => 9999,
    ])->assertSessionHasErrors(['ability', 'expiry_days']);
});

it('caps active tokens per user', function () {
    $user = mcpSettingsUser();
    foreach (range(1, McpToken::MAX_ACTIVE_PER_USER) as $i) {
        McpToken::issue($user, "t{$i}", ['read'], 30);
    }

    $this->actingAs($user)->post(route('settings.mcp-tokens.store'), [
        'name' => 'uno di troppo', 'ability' => 'read', 'expiry_days' => 30,
    ])->assertSessionHasErrors('name');
});

it('revokes only own tokens', function () {
    $user = mcpSettingsUser();
    $intruder = mcpSettingsUser();
    [$token] = McpToken::issue($user, 'mio', ['read'], 30);

    $this->actingAs($intruder)->delete(route('settings.mcp-tokens.destroy', $token))->assertNotFound();
    expect($token->fresh()->revoked_at)->toBeNull();

    $this->actingAs($user)->delete(route('settings.mcp-tokens.destroy', $token))->assertRedirect();
    expect($token->fresh()->revoked_at)->not->toBeNull();
});

it('lists tokens on the settings page without the hash', function () {
    $user = mcpSettingsUser();
    McpToken::issue($user, 'mio', ['read'], 30);

    $this->actingAs($user)->get(route('settings'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mcp.tokens.0.name', 'mio')
            ->where('mcp.tokens.0.state', 'attivo')
            ->missing('mcp.tokens.0.token_hash'));
});
