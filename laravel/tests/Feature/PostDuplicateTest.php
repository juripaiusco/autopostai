<?php

use App\Mcp\Servers\FaPer3Server;
use App\Mcp\Tools\DuplicatePost;
use App\Models\ContactTag;
use App\Models\Post;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function duplicateOwner(): User
{
    $admin = User::factory()->create(['parent_id' => null]);

    return User::factory()->create([
        'parent_id' => $admin->id,
        'channels' => [
            'facebook' => ['on' => true, 'reply_on' => true, 'reply_n' => 5],
            'wordpress' => ['on' => true],
        ],
    ]);
}

it('copies a post as an undated draft and opens its form', function () {
    $owner = duplicateOwner();
    $post = mcpPost($owner, [
        'title' => 'Evento di sabato',
        'ai_prompt_post' => 'Scrivi del concerto',
        'ai_content' => 'Testo già rivisto',
        'ai_prompt_comment' => 'Rispondi gentile',
        'img_ai_check_on' => '1',
        'published_at' => now()->addDay(),
    ]);
    $response = $this->actingAs($owner)->post(route('posts.duplicate', $post));

    $copy = Post::whereKeyNot($post->id)->sole();
    $response->assertRedirect(route('posts.edit', $copy))
        ->assertSessionHas('toast');

    expect($copy->title)->toBe('Evento di sabato (copia)')
        ->and($copy->user_id)->toBe($owner->id)
        ->and($copy->created_by_user_id)->toBe($owner->id)
        ->and($copy->ai_prompt_post)->toBe('Scrivi del concerto')
        ->and($copy->ai_content)->toBe('Testo già rivisto')
        ->and($copy->ai_prompt_comment)->toBe('Rispondi gentile')
        ->and($copy->img_ai_check_on)->toBe('1')
        ->and($copy->published_at)->toBeNull()
        ->and($copy->status())->toBe('draft');
});

it('drops the worker state when duplicating a published post', function () {
    $owner = duplicateOwner();
    $post = mcpPost($owner, [
        'published' => '1',
        'task_complete' => '1',
        'published_at' => now()->subWeek(),
        'channels' => [
            'facebook' => [
                'on' => true, 'id' => 'fb_1', 'url' => 'https://fb.example/1', 'content' => 'Corretto dopo',
                'error' => 'vecchio', 'attempts' => 2, 'comments_enabled' => true, 'auto_reply_enabled' => true,
            ],
            'wordpress' => [
                'on' => true, 'id' => '99', 'categories' => [['id' => '7', 'name' => 'Eventi', 'on' => true]],
            ],
            'instagram' => ['on' => false],
        ],
    ]);

    $this->actingAs($owner)->post(route('posts.duplicate', $post))->assertRedirect();

    $copy = Post::whereKeyNot($post->id)->sole();

    expect($copy->published)->toBe('0')
        ->and($copy->task_complete)->not->toBe('1')
        ->and($copy->status())->toBe('draft')
        ->and($copy->activeChannels())->toBe(['facebook', 'wordpress'])
        ->and($copy->channels['facebook'])->toBe([
            'on' => true, 'comments_enabled' => true, 'auto_reply_enabled' => true, 'reply_n' => 5,
        ])
        ->and($copy->channels['wordpress'])->toBe([
            'on' => true, 'categories' => [['id' => '7', 'name' => 'Eventi', 'on' => true]],
        ])
        ->and($copy->comments_enabled)->toBe('1')
        ->and($copy->auto_reply_enabled)->toBe('1');
});

it('copies the images into the new post folder and skips missing files', function () {
    $owner = duplicateOwner();
    $post = mcpPost($owner, ['img' => ['a.jpg', 'sparita.jpg', 'b.png']]);
    Storage::disk('public')->put("posts/{$post->id}/a.jpg", 'A');
    Storage::disk('public')->put("posts/{$post->id}/b.png", 'B');

    $this->actingAs($owner)->post(route('posts.duplicate', $post))->assertRedirect();

    $copy = Post::whereKeyNot($post->id)->sole();

    expect($copy->img)->toBe(['a.jpg', 'b.png']);
    Storage::disk('public')->assertExists(["posts/{$copy->id}/a.jpg", "posts/{$copy->id}/b.png"]);
    Storage::disk('public')->assertExists("posts/{$post->id}/a.jpg");
    expect(Storage::disk('public')->get("posts/{$copy->id}/b.png"))->toBe('B');
});

it('falls back to all contacts when the newsletter tag no longer exists', function () {
    $owner = duplicateOwner();
    Settings::factory()->create(['user_id' => $owner->id, 'nl_smtp_host' => 'smtp.example']);
    $kept = ContactTag::factory()->create(['user_id' => $owner->id]);

    $withKept = mcpPost($owner, ['channels' => ['newsletter' => ['on' => true, 'provider' => 'smtp_custom', 'tag_id' => $kept->id]]]);
    $withGone = mcpPost($owner, ['channels' => ['newsletter' => ['on' => true, 'provider' => 'smtp_custom', 'tag_id' => 9999]]]);

    $this->actingAs($owner)->post(route('posts.duplicate', $withKept))->assertRedirect();
    $this->actingAs($owner)->post(route('posts.duplicate', $withGone))->assertRedirect();

    $copies = Post::whereKeyNot([$withKept->id, $withGone->id])->orderBy('id')->get();

    expect($copies[0]->channels['newsletter']['tag_id'])->toBe($kept->id)
        ->and($copies[1]->channels['newsletter']['tag_id'])->toBeNull();
});

it('forbids duplicating a post of another account', function () {
    $post = mcpPost(duplicateOwner());
    $stranger = duplicateOwner();

    $this->actingAs($stranger)->post(route('posts.duplicate', $post))->assertForbidden();

    expect(Post::count())->toBe(1);
});

it('duplicate_post copies a post through MCP', function () {
    $owner = duplicateOwner();
    $post = mcpPost($owner, ['title' => 'Promo', 'ai_content' => 'Testo']);

    FaPer3Server::actingAs(withMcp($owner, 'write'))->tool(DuplicatePost::class, ['post_id' => $post->id])
        ->assertOk()->assertSee('Promo (copia)');

    $copy = Post::whereKeyNot($post->id)->sole();
    expect($copy->ai_content)->toBe('Testo')
        ->and($copy->published_at)->toBeNull();

    FaPer3Server::actingAs(withMcp($owner->fresh(), 'read'))->tool(DuplicatePost::class, [])->assertNotRegistered();
});
