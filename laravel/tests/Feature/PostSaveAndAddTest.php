<?php

use App\Jobs\GeneratePostContentJob;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->freezeSecond();
});

function saveAndAddOwner(): User
{
    $admin = User::factory()->create(['parent_id' => null]);

    return User::factory()->create([
        'parent_id' => $admin->id,
        'channels' => [
            'facebook' => ['on' => true],
            'linkedin' => ['on' => true],
        ],
    ]);
}

it('saves the post and creates a copy scheduled one minute later', function () {
    $owner = saveAndAddOwner();
    $at = now()->addDay()->startOfMinute();

    $response = $this->actingAs($owner)->post(route('posts.store'), [
        'title' => 'Concerto',
        'channels' => ['facebook' => [], 'linkedin' => []],
        'ai_prompt_post' => 'Scrivi del concerto',
        'ai_prompt_comment' => 'Rispondi gentile',
        'ai_content' => 'Testo per Facebook',
        'published_at' => $at->format('Y-m-d\TH:i'),
        'action' => 'save_and_add',
    ]);

    $original = Post::where('ai_content', 'Testo per Facebook')->sole();
    $copy = Post::whereKeyNot($original->id)->sole();

    $response->assertRedirect(route('posts.edit', $copy))
        ->assertSessionHas('toast')
        ->assertSessionHas('series_copy_id', $copy->id);

    expect($copy->title)->toBe('Concerto')
        ->and($copy->ai_prompt_post)->toBe('Scrivi del concerto')
        ->and($copy->ai_prompt_comment)->toBe('Rispondi gentile')
        ->and($copy->ai_content)->toBeNull()
        ->and($copy->published_at->equalTo($at->copy()->addMinute()))->toBeTrue()
        ->and($copy->channels['facebook']['on'])->toBeTruthy()
        ->and($copy->channels['linkedin']['on'])->toBeTruthy();

    // Testo della copia riscritto dall'AI prima dell'uscita.
    Queue::assertPushed(GeneratePostContentJob::class, 1);
});

it('copies the images of the saved post', function () {
    $owner = saveAndAddOwner();
    $original = mcpPost($owner, ['img' => ['a.jpg'], 'published_at' => now()->addDay()]);
    Storage::disk('public')->put("posts/{$original->id}/a.jpg", 'x');

    $this->actingAs($owner)->put(route('posts.update', $original), [
        'title' => $original->title,
        'channels' => ['facebook' => []],
        'published_at' => $original->published_at->format('Y-m-d\TH:i'),
        'action' => 'save_and_add',
    ])->assertRedirect();

    $copy = Post::whereKeyNot($original->id)->sole();

    expect($copy->img)->toBe(['a.jpg']);
    Storage::disk('public')->assertExists("posts/{$copy->id}/a.jpg");
});

it('works from the edit form using the data just saved', function () {
    $owner = saveAndAddOwner();
    $original = mcpPost($owner, ['title' => 'Vecchio', 'published_at' => now()->addDay()]);
    $newAt = now()->addDays(3)->startOfMinute();

    $this->actingAs($owner)->put(route('posts.update', $original), [
        'title' => 'Nuovo titolo',
        'channels' => ['linkedin' => []],
        'ai_content' => 'Testo LinkedIn',
        'published_at' => $newAt->format('Y-m-d\TH:i'),
        'action' => 'save_and_add',
    ]);

    $copy = Post::whereKeyNot($original->id)->sole();

    expect($original->fresh()->title)->toBe('Nuovo titolo')
        ->and($copy->title)->toBe('Nuovo titolo')
        ->and($copy->published_at->equalTo($newAt->copy()->addMinute()))->toBeTrue()
        ->and($copy->channels['linkedin']['on'])->toBeTruthy()
        ->and(!empty($copy->channels['facebook']['on'] ?? null))->toBeFalse();
});

it('keeps the copy as an undated draft when the original has no date', function () {
    $owner = saveAndAddOwner();

    $this->actingAs($owner)->post(route('posts.store'), [
        'title' => 'Bozza',
        'channels' => ['facebook' => []],
        'ai_prompt_post' => 'Scrivi qualcosa',
        'published_at' => '',
        'action' => 'save_and_add',
    ])->assertRedirect();

    $copy = Post::where('title', 'Bozza')->orderByDesc('id')->first();

    expect(Post::count())->toBe(2)
        ->and($copy->published_at)->toBeNull()
        ->and($copy->status())->toBe('draft');
    Queue::assertNothingPushed();
});

it('flags the copy form for the double publishing warning only right after save and add', function () {
    $owner = saveAndAddOwner();

    $this->actingAs($owner)->post(route('posts.store'), [
        'title' => 'Tra poco',
        'channels' => ['facebook' => []],
        'published_at' => now()->addMinutes(20)->format('Y-m-d\TH:i'),
        'action' => 'save_and_add',
    ]);
    $copy = Post::orderByDesc('id')->first();

    $this->actingAs($owner)->withSession(['series_copy_id' => $copy->id])
        ->get(route('posts.edit', $copy))
        ->assertInertia(fn (Assert $page) => $page->where('seriesCopy', true));

    $this->actingAs($owner)->get(route('posts.edit', $copy))
        ->assertInertia(fn (Assert $page) => $page->where('seriesCopy', false));
});

it('accepts WordPress as the only channel without categories, as the form sends it', function () {
    $owner = saveAndAddOwner();
    $original = mcpPost($owner, ['published_at' => now()->addDay()]);

    // FormData non invia array vuoti: il form manda channels[wordpress][on]=1.
    $this->actingAs($owner)->put(route('posts.update', $original), [
        'title' => $original->title,
        'channels' => ['wordpress' => ['on' => '1']],
        'published_at' => $original->published_at->format('Y-m-d\TH:i'),
        'action' => 'save_and_add',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $copy = Post::whereKeyNot($original->id)->sole();

    expect($original->fresh()->channels['wordpress'])->toBe(['on' => true, 'categories' => []])
        ->and($copy->channels['wordpress']['on'])->toBeTrue()
        ->and($copy->channels['facebook']['on'])->toBeFalse();
});
