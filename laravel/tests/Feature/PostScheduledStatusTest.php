<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Un post con data e non ancora uscito è "schedulato" anche se l'orario è
 * già passato: il worker prende ogni published_at <= adesso. Prima tornava
 * "bozza" finché il worker non lo pubblicava.
 */

it('is scheduled with a future or past date until it is published', function () {
    $user = User::factory()->create(['parent_id' => null]);

    expect(mcpPost($user, ['published_at' => now()->addDay()])->status())->toBe('scheduled')
        ->and(mcpPost($user, ['published_at' => now()->subMinute()])->status())->toBe('scheduled')
        ->and(mcpPost($user)->status())->toBe('draft');
});

it('keeps errors ahead of scheduled', function () {
    $user = User::factory()->create(['parent_id' => null]);
    $post = mcpPost($user, [
        'published_at' => now()->subHour(),
        'channels' => ['facebook' => ['on' => true, 'error' => 'boom', 'attempts' => 1]],
    ]);

    expect($post->status())->toBe('error');
});

it('lists past-dated unpublished posts under programmati, not bozze', function () {
    $user = User::factory()->create(['parent_id' => null]);
    $future = mcpPost($user, ['published_at' => now()->addDay()]);
    $overdue = mcpPost($user, ['published_at' => now()->subMinute()]);
    $draft = mcpPost($user);

    expect(Post::listFilter('programmati')->pluck('id')->sort()->values()->all())
        ->toBe(collect([$future->id, $overdue->id])->sort()->values()->all())
        ->and(Post::listFilter('bozze')->pluck('id')->all())->toBe([$draft->id]);
});
