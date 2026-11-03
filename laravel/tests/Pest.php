<?php

use App\Models\McpToken;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// --- Server MCP (tests/Feature/Mcp) ---

/** Utente con un token MCP del livello indicato, come lo imposta McpTokenAuth. */
function withMcp(User $user, string $ability = 'read'): User
{
    [$token] = McpToken::issue($user, 'test', [$ability], 30);

    return $user->withMcpToken($token);
}

/** Admin → manager → due utenti semplici, più un utente fuori dal ramo del manager. */
function mcpTree(): array
{
    $admin = User::factory()->create(['parent_id' => null]);
    $manager = User::factory()->create(['parent_id' => $admin->id, 'child_on' => 1]);
    $alice = User::factory()->create(['parent_id' => $manager->id, 'channels' => ['facebook' => ['on' => true, 'reply_on' => true]]]);
    $bob = User::factory()->create(['parent_id' => $manager->id]);
    $other = User::factory()->create(['parent_id' => $admin->id]);

    return compact('admin', 'manager', 'alice', 'bob', 'other');
}

function mcpPost(User $owner, array $attrs = []): Post
{
    return Post::factory()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'published' => '0',
        'published_at' => null,
        'channels' => ['facebook' => ['on' => true]],
        ...$attrs,
    ]);
}
