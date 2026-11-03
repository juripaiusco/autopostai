<?php

use App\Models\McpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Livello HTTP di /mcp: i test con FaPer3Server::actingAs() saltano i
 * middleware della rotta, qui si verificano auth, origine e throttle veri.
 */

function mcpHttpUser(): User
{
    $admin = User::factory()->create(['parent_id' => null]);

    return User::factory()->create(['parent_id' => $admin->id]);
}

function mcpCall(?string $token = null, array $headers = [], string $method = 'tools/list', array $params = [])
{
    return test()->withHeaders(array_filter([
        'Accept' => 'application/json, text/event-stream',
        'Authorization' => $token ? "Bearer {$token}" : null,
        ...$headers,
    ]))->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
}

it('rejects requests without a token with WWW-Authenticate', function () {
    mcpCall()->assertStatus(401)->assertHeader('WWW-Authenticate');
});

it('rejects unknown, revoked and expired tokens', function () {
    $user = mcpHttpUser();

    [$revoked, $revokedPlain] = McpToken::issue($user, 'r', ['read'], 30);
    $revoked->update(['revoked_at' => now()]);

    [$expired, $expiredPlain] = McpToken::issue($user, 'e', ['read'], 30);
    $expired->update(['expires_at' => now()->subMinute()]);

    mcpCall('fp3_'.str_repeat('x', 48))->assertStatus(401);
    mcpCall($revokedPlain)->assertStatus(401);
    mcpCall($expiredPlain)->assertStatus(401);
});

it('lists tools with a valid token and records its use', function () {
    $user = mcpHttpUser();
    [$token, $plain] = McpToken::issue($user, 'ok', ['read'], 30);

    mcpCall($plain)
        ->assertOk()
        ->assertJsonPath('result.tools.*.name', fn ($names) => in_array('whoami', $names) && in_array('list_posts', $names));

    expect($token->fresh()->last_used_at)->not->toBeNull();
});

it('calls a tool as the token owner', function () {
    $user = mcpHttpUser();
    [, $plain] = McpToken::issue($user, 'ok', ['read'], 30);

    mcpCall($plain, method: 'tools/call', params: ['name' => 'whoami', 'arguments' => (object) []])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.user.id', $user->id);
});

it('blocks foreign browser origins but accepts the app origin', function () {
    $user = mcpHttpUser();
    [, $plain] = McpToken::issue($user, 'ok', ['read'], 30);

    mcpCall($plain, ['Origin' => 'https://evil.example'])->assertStatus(403);
    mcpCall($plain, ['Origin' => rtrim(config('app.url'), '/')])->assertOk();
});

it('accepts origins listed in MCP_ALLOWED_ORIGINS', function () {
    config(['services.mcp.allowed_origins' => ['https://claude.ai']]);
    $user = mcpHttpUser();
    [, $plain] = McpToken::issue($user, 'ok', ['read'], 30);

    mcpCall($plain, ['Origin' => 'https://claude.ai'])->assertOk();
});

it('throttles a token after 120 requests per minute', function () {
    $user = mcpHttpUser();
    [, $plain] = McpToken::issue($user, 'ok', ['read'], 30);

    for ($i = 0; $i < 120; $i++) {
        mcpCall($plain, method: 'ping')->assertOk();
    }

    mcpCall($plain, method: 'ping')->assertStatus(429);
});

it('lists every tool in a single page for a publish token', function () {
    $user = mcpHttpUser();
    [, $plain] = McpToken::issue($user, 'ok', ['publish'], 30);

    $names = mcpCall($plain)->assertOk()->assertJsonMissingPath('result.nextCursor')->json('result.tools.*.name');

    expect($names)->toHaveCount(count(\App\Mcp\Servers\FaPer3Server::discover('Tools')))
        ->toContain('whoami', 'create_post', 'publish_post_now', 'delete_post');
});
