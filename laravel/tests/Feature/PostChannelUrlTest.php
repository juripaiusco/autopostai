<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function channelUrlPost(User $account, array $channels): Post
{
    return Post::factory()->create([
        'user_id' => $account->id,
        'created_by_user_id' => $account->id,
        'channels' => $channels,
        'published' => '1',
        'published_at' => now()->subHour(),
    ]);
}

test('channelUrls keeps only published channels with an http(s) url', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = channelUrlPost($account, [
        'facebook' => ['on' => true, 'id' => '1_2', 'url' => 'https://www.facebook.com/1/posts/2'],
        'instagram' => ['on' => true, 'id' => '99', 'url' => 'javascript:alert(1)'],
        'linkedin' => ['on' => true, 'url' => 'https://www.linkedin.com/feed/update/x'], // non ancora uscito
        'wordpress' => ['on' => false, 'id' => '5', 'url' => 'https://blog.test/?p=5'],    // canale spento
        'newsletter' => ['on' => true, 'id' => 'smtp-1', 'provider' => 'smtp_custom'],    // SMTP: nessun url
    ]);

    expect($post->channelUrls())->toBe(['facebook' => 'https://www.facebook.com/1/posts/2']);
});

test('post list exposes channel urls', function () {
    $account = User::factory()->create(['parent_id' => null]);
    channelUrlPost($account, [
        'facebook' => ['on' => true, 'id' => '1_2', 'url' => 'https://www.facebook.com/1/posts/2'],
        'instagram' => ['on' => true],
    ]);

    $this->actingAs($account)->get(route('posts'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('posts.data.0.channelUrls', ['facebook' => 'https://www.facebook.com/1/posts/2'])
        );
});

test('post page exposes channel urls', function () {
    $account = User::factory()->create(['parent_id' => null]);
    $post = channelUrlPost($account, [
        'wordpress' => ['on' => true, 'id' => '5', 'url' => 'https://blog.test/?p=5'],
    ]);

    $this->actingAs($account)->get(route('posts.show', $post))
        ->assertInertia(fn (Assert $page) => $page
            ->where('post.channelUrls', ['wordpress' => 'https://blog.test/?p=5'])
        );
});

test('dashboard recent posts expose channel urls', function () {
    $account = User::factory()->create(['parent_id' => null]);
    channelUrlPost($account, [
        'linkedin' => ['on' => true, 'id' => 'urn:li:share:1', 'url' => 'https://www.linkedin.com/feed/update/urn:li:share:1'],
    ]);

    $this->actingAs($account)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentPosts.0.channelUrls', ['linkedin' => 'https://www.linkedin.com/feed/update/urn:li:share:1'])
        );
});
