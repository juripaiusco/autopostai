<?php

use App\Jobs\GenerateImageJob;
use App\Jobs\GeneratePostContentJob;
use App\Mcp\Prompts\PianificaSeriePost;
use App\Mcp\Servers\FaPer3Server;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\DeletePost;
use App\Mcp\Tools\GenerateImage;
use App\Mcp\Tools\GeneratePostText;
use App\Mcp\Tools\GetImageJob;
use App\Mcp\Tools\ListImageArchive;
use App\Mcp\Tools\PublishPostNow;
use App\Mcp\Tools\RefreshChannelOptions;
use App\Mcp\Tools\RetryPost;
use App\Mcp\Tools\UpdatePost;
use App\Mcp\Tools\UpdatePublishedPost;
use App\Models\ImageJob;
use App\Models\Post;
use App\Models\Settings;
use App\Models\TokenLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake([GeneratePostContentJob::class, GenerateImageJob::class]);
    Storage::fake('public');
});

/** Utente semplice con i canali indicati attivi, chiave OpenAI e limiti. */
function mcpWriter(array $channels = ['facebook', 'instagram', 'wordpress'], array $attrs = []): User
{
    $admin = User::factory()->create(['parent_id' => null]);
    $user = User::factory()->create([
        'parent_id' => $admin->id,
        'channels' => collect($channels)->mapWithKeys(fn ($c) => [$c => ['on' => true, 'reply_on' => true]])->all(),
        'tokens_limit' => 10000,
        'image_model_limit' => 5,
        ...$attrs,
    ]);
    Settings::factory()->create([
        'user_id' => $user->id,
        'openai_api_key' => 'sk-test',
        'wordpress_url' => 'https://blog.example',
        'wordpress_options' => ['categories' => [['id' => '7', 'name' => 'Eventi', 'count' => 1]]],
    ]);

    return $user->fresh();
}

function archiveImage(User $owner, string $filename = 'gatto.png'): string
{
    $png = UploadedFile::fake()->image('x.png', 20, 20);
    Storage::disk('public')->put("openai/{$owner->id}/{$filename}", file_get_contents($png->getPathname()));

    return $filename;
}

it('hides write and publish tools from a read-only token', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'read'))->tool(CreatePost::class, [])->assertNotRegistered();
    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(PublishPostNow::class, [])->assertNotRegistered();
    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(DeletePost::class, [])->assertNotRegistered();
});

it('create_post saves a draft for the token owner', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'title' => 'Bozza MCP',
        'channels' => ['facebook' => ['comments_enabled' => true, 'auto_reply_enabled' => true]],
        'ai_prompt_post' => 'Parla del nuovo menu',
    ])->assertOk()->assertSee('"status":"draft"');

    $post = Post::where('title', 'Bozza MCP')->firstOrFail();
    expect($post->user_id)->toBe($user->id)
        ->and($post->created_by_user_id)->toBe($user->id)
        ->and($post->published_at)->toBeNull()
        ->and($post->channels['facebook'])->toMatchArray(['on' => true, 'comments_enabled' => true, 'auto_reply_enabled' => true])
        ->and($post->channels['instagram'])->toBe(['on' => false]);
});

it('create_post schedules a post and queues the AI text', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'channels' => ['facebook' => []],
        'ai_prompt_post' => 'Promo weekend',
        'published_at' => now()->addDays(2)->toIso8601String(),
    ])->assertOk()->assertSee(['"status":"scheduled"', '"text_queued":true']);

    Bus::assertDispatched(GeneratePostContentJob::class);
});

it('create_post refuses a past date: publishing now needs the publish ability', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'channels' => ['facebook' => []],
        'ai_content' => 'Testo',
        'published_at' => now()->subMinute()->toIso8601String(),
    ])->assertHasErrors();

    expect(Post::count())->toBe(0);
});

it('create_post refuses inactive channels and Instagram without images', function () {
    $user = mcpWriter(['facebook', 'instagram']);

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, ['channels' => ['linkedin' => []]])
        ->assertHasErrors(['Il canale linkedin non è attivo su questo account.']);

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(CreatePost::class, ['channels' => ['instagram' => []]])
        ->assertHasErrors();

    expect(Post::count())->toBe(0);
});

it('create_post attaches archive images through the same storage as the form', function () {
    $user = mcpWriter();
    $filename = archiveImage($user);

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'channels' => ['instagram' => []],
        'ai_content' => 'Con foto',
        'archive_images' => [$filename],
    ])->assertOk()->assertSee('"images":1');

    $post = Post::firstOrFail();
    expect($post->img)->toHaveCount(1);
    Storage::disk('public')->assertExists("posts/{$post->id}/{$post->img[0]}");
});

it('create_post rejects images outside the archive and path tricks', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'channels' => ['facebook' => []],
        'archive_images' => ['../../.env'],
    ])->assertHasErrors();
});

it('create_post resolves WordPress categories from the cache and rejects unknown ones', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'channels' => ['wordpress' => ['category_ids' => ['99']]],
    ])->assertHasErrors();

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(CreatePost::class, [
        'channels' => ['wordpress' => ['category_ids' => ['7']]],
    ])->assertOk();

    expect(Post::firstOrFail()->channels['wordpress']['categories'])->toBe([['id' => '7', 'name' => 'Eventi', 'on' => true]]);
});

it('create_post makes managers pick one of their accounts', function () {
    ['manager' => $manager, 'alice' => $alice, 'other' => $other] = mcpTree();

    FaPer3Server::actingAs(withMcp($manager, 'write'))->tool(CreatePost::class, ['channels' => ['facebook' => []]])
        ->assertHasErrors();

    FaPer3Server::actingAs(withMcp($manager->fresh(), 'write'))->tool(CreatePost::class, ['account_id' => $other->id, 'channels' => ['facebook' => []]])
        ->assertHasErrors(['Account non trovato o non gestito da te.']);

    FaPer3Server::actingAs(withMcp($manager->fresh(), 'write'))->tool(CreatePost::class, ['account_id' => $alice->id, 'channels' => ['facebook' => []]])
        ->assertOk();

    expect(Post::firstOrFail())->user_id->toBe($alice->id)->created_by_user_id->toBe($manager->id);
});

it('update_post changes only the given fields', function () {
    $user = mcpWriter();
    $post = mcpPost($user, [
        'title' => 'Vecchio',
        'ai_prompt_post' => 'Prompt originale',
        'channels' => ['facebook' => ['on' => true, 'comments_enabled' => true]],
        'published_at' => now()->addDay(),
    ]);

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(UpdatePost::class, ['post_id' => $post->id, 'title' => 'Nuovo'])
        ->assertOk();

    $post->refresh();
    expect($post->title)->toBe('Nuovo')
        ->and($post->ai_prompt_post)->toBe('Prompt originale')
        ->and($post->channels['facebook']['comments_enabled'])->toBeTrue()
        ->and($post->status())->toBe('scheduled');

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(UpdatePost::class, ['post_id' => $post->id, 'published_at' => null])
        ->assertOk();

    expect($post->fresh()->status())->toBe('draft');
});

it('create_post saves the social text; update_post keeps the editor format unless the text changes', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'title' => 'Articolo MCP',
        'channels' => ['facebook' => [], 'wordpress' => []],
        'ai_content' => "# Titolo\n\nCorpo",
        'ai_content_social' => 'Nuovo articolo! 🎉',
    ])->assertOk();

    $created = Post::where('title', 'Articolo MCP')->firstOrFail();
    expect($created->ai_content_format)->toBe('markdown')
        ->and($created->ai_content_social)->toBe('Nuovo articolo! 🎉');

    $post = mcpPost($user, [
        'channels' => ['facebook' => ['on' => true], 'wordpress' => ['on' => true, 'categories' => []]],
        'ai_content' => '<h1>Titolo</h1><p>Editor</p>',
        'ai_content_format' => 'html',
    ]);

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(UpdatePost::class, ['post_id' => $post->id, 'title' => 'Solo titolo'])
        ->assertOk();
    expect($post->fresh()->ai_content_format)->toBe('html');

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(UpdatePost::class, [
        'post_id' => $post->id,
        'ai_content' => "# Nuovo\n\nMarkdown",
        'ai_content_social' => '',
    ])->assertOk();
    expect($post->fresh())->ai_content_format->toBe('markdown')->ai_content_social->toBeNull();
});

it('update_post refuses posts already out and posts of other accounts', function () {
    ['alice' => $alice, 'other' => $other] = mcpTree();
    $published = mcpPost($alice, ['published' => '1', 'published_at' => now()->subDay()]);
    $foreign = mcpPost($other);

    FaPer3Server::actingAs(withMcp($alice, 'write'))->tool(UpdatePost::class, ['post_id' => $published->id, 'title' => 'x'])
        ->assertHasErrors(['Un post pubblicato (anche solo su alcuni canali) non può essere modificato.']);

    FaPer3Server::actingAs(withMcp($alice->fresh(), 'write'))->tool(UpdatePost::class, ['post_id' => $foreign->id, 'title' => 'x'])
        ->assertHasErrors(['Post non trovato.']);
});

it('publish_post_now sets the date to now', function () {
    $user = mcpWriter();
    $post = mcpPost($user, ['ai_content' => 'Pronto', 'published_at' => now()->addWeek()]);

    FaPer3Server::actingAs(withMcp($user, 'publish'))->tool(PublishPostNow::class, ['post_id' => $post->id])
        ->assertOk();

    expect($post->fresh()->published_at->diffInSeconds(now(), true))->toBeLessThan(5);
});

it('publish_post_now refuses a post without text and prompt', function () {
    $user = mcpWriter();
    $post = mcpPost($user, ['ai_content' => null, 'ai_prompt_post' => null]);

    FaPer3Server::actingAs(withMcp($user, 'publish'))->tool(PublishPostNow::class, ['post_id' => $post->id])
        ->assertHasErrors();

    expect($post->fresh()->published_at)->toBeNull();
});

it('delete_post soft-deletes with the publish ability', function () {
    $user = mcpWriter();
    $post = mcpPost($user);

    FaPer3Server::actingAs(withMcp($user, 'publish'))->tool(DeletePost::class, ['post_id' => $post->id])->assertOk();

    expect(Post::find($post->id))->toBeNull()
        ->and(Post::withTrashed()->find($post->id))->not->toBeNull();
});

it('retry_post requeues failed channels', function () {
    $user = mcpWriter();
    $post = mcpPost($user, ['channels' => ['facebook' => ['on' => true, 'attempts' => 3, 'error' => 'Token scaduto']]]);

    FaPer3Server::actingAs(withMcp($user, 'publish'))->tool(RetryPost::class, ['post_id' => $post->id])
        ->assertOk()->assertSee('facebook');

    $facebook = $post->fresh()->channels['facebook'];
    expect($facebook['attempts'])->toBe(0)
        ->and($facebook['error'] ?? null)->toBeNull();
});

it('update_published_post queues the new text for a live Facebook post', function () {
    $user = mcpWriter();
    $post = mcpPost($user, ['published' => '1', 'published_at' => now()->subDay(), 'channels' => ['facebook' => ['on' => true, 'id' => 'fb_1']]]);

    FaPer3Server::actingAs(withMcp($user, 'publish'))->tool(UpdatePublishedPost::class, [
        'post_id' => $post->id, 'channel' => 'facebook', 'content' => 'Testo corretto',
    ])->assertOk();

    $post->refresh();
    expect($post->updated)->toBe('2')
        ->and($post->channels['facebook']['content'])->toBe('Testo corretto');
});

it('generate_post_text meters tokens and respects the monthly limit', function () {
    Http::fake(['*/generate-text' => Http::response(['content' => 'Testo AI', 'tokens' => 120])]);
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(GeneratePostText::class, ['prompt' => 'Scrivi'])
        ->assertOk()->assertSee('Testo AI');

    expect(TokenLog::where('user_id', $user->id)->where('type', 'preview')->sum('tokens_used'))->toBe(120);

    $user->update(['tokens_limit' => 100]);
    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(GeneratePostText::class, ['prompt' => 'Ancora'])
        ->assertHasErrors(['Limite mensile di token AI raggiunto per questo account.']);
});

it('generate_post_text returns the warnings of the ai service', function () {
    Http::fake(['*/generate-text' => Http::response([
        'content' => 'Testo AI', 'tokens' => 1, 'warnings' => ['Rimossa un\'immagine non presente nelle pagine lette.'],
    ])]);

    FaPer3Server::actingAs(withMcp(mcpWriter(), 'write'))
        ->tool(GeneratePostText::class, ['prompt' => 'Scrivi', 'channel' => 'newsletter'])
        ->assertOk()->assertSee('Rimossa un\'immagine non presente nelle pagine lette.');
});

it('generate_post_text rewrites an article for social with source_text', function () {
    $user = mcpWriter();
    Http::fake(['*/generate-text' => Http::response(['content' => 'Post breve', 'tokens' => 5])]);

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(GeneratePostText::class, [
        'source_text' => 'Articolo lungo',
        'channels' => ['wordpress', 'facebook'],
    ])->assertOk()->assertSee('Post breve');

    Http::assertSent(fn ($request) => $request['source_text'] === 'Articolo lungo');
});

it('generate_post_text forwards the channels for the LinkedIn limit', function () {
    Http::fake(['*/generate-text' => Http::response(['content' => 'Testo AI', 'tokens' => 1])]);

    FaPer3Server::actingAs(withMcp(mcpWriter(), 'write'))
        ->tool(GeneratePostText::class, ['prompt' => 'Scrivi', 'channels' => ['facebook', 'linkedin']])
        ->assertOk();

    Http::assertSent(fn ($request) => $request['channels'] === ['facebook', 'linkedin']);
});

it('generate_image starts a job and get_image_job reports the file', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(GenerateImage::class, ['prompt' => 'Un gatto'])
        ->assertOk()->assertSee('"status":"pending"');
    Bus::assertDispatched(GenerateImageJob::class);

    $job = ImageJob::firstOrFail();
    $job->update(['status' => 'completed', 'image_url' => 'abc.png']);

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(GetImageJob::class, ['job_id' => $job->id])
        ->assertOk()->assertSee(['"filename":"abc.png"', "openai/{$user->id}/abc.png"]);
});

it('get_image_job hides jobs of other accounts', function () {
    ['alice' => $alice, 'other' => $other] = mcpTree();
    $job = ImageJob::create(['user_id' => $other->id, 'created_by_user_id' => $other->id, 'status' => 'pending', 'prompt' => 'x', 'model' => 'gpt-image-2.5-flare']);

    FaPer3Server::actingAs(withMcp($alice))->tool(GetImageJob::class, ['job_id' => $job->id])
        ->assertHasErrors(['Generazione non trovata.']);
});

it('list_image_archive returns the archive filenames', function () {
    $user = mcpWriter();
    archiveImage($user, 'tramonto.png');

    FaPer3Server::actingAs(withMcp($user))->tool(ListImageArchive::class)
        ->assertOk()->assertSee('tramonto.png');
});

it('refresh_channel_options reloads WordPress categories', function () {
    Http::fake(['blog.example/*' => Http::response([['id' => 12, 'name' => 'Ricette &amp; Co', 'count' => 4]])]);
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(RefreshChannelOptions::class, ['source' => 'wordpress'])
        ->assertOk()->assertSee('Ricette & Co');

    expect($user->settings->fresh()->wordpress_options['categories'][0]['id'])->toBe('12');
});

it('offers the planning prompt only to tokens that can write', function () {
    $user = mcpWriter();

    FaPer3Server::actingAs(withMcp($user, 'read'))->prompt(PianificaSeriePost::class, ['tema' => 'x', 'periodo' => 'y'])
        ->assertNotRegistered();

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->prompt(PianificaSeriePost::class, ['tema' => 'Menu autunnale', 'periodo' => '2 settimane'])
        ->assertOk()->assertSee(['Menu autunnale', 'create_post']);
});

it('create_post schedules a newsletter made only of custom html without text or prompt', function () {
    $user = smtpNewsletterWriter();
    $html = '<html><body><p>Solo mio</p><script>x()</script></body></html>';

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'title' => 'Oggetto custom',
        'channels' => ['newsletter' => []],
        'newsletter_html' => $html,
        'published_at' => now()->addDays(2)->toIso8601String(),
    ])->assertOk()->assertSee('"text_queued":false');

    $post = Post::where('title', 'Oggetto custom')->firstOrFail();
    expect($post->nl_template_custom)->toContain('<p>Solo mio</p>')->not->toContain('script');
    Bus::assertNotDispatched(GeneratePostContentJob::class);
});

it('create_post requires the subject for custom html without [content]', function () {
    $user = smtpNewsletterWriter();

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(CreatePost::class, [
        'channels' => ['newsletter' => []],
        'newsletter_html' => '<p>Solo mio</p>',
    ])->assertHasErrors();

    expect(Post::count())->toBe(0);
});

it('update_post sets and clears the newsletter html', function () {
    $user = smtpNewsletterWriter();
    $post = mcpPost($user, ['channels' => ['newsletter' => ['on' => true]], 'ai_content' => '# Titolo']);

    FaPer3Server::actingAs(withMcp($user, 'write'))->tool(UpdatePost::class, [
        'post_id' => $post->id,
        'newsletter_html' => '<div>[content]</div>',
    ])->assertOk();
    expect($post->fresh()->nl_template_custom)->toBe('<div>[content]</div>');

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(UpdatePost::class, [
        'post_id' => $post->id,
        'title' => 'Altro',
    ])->assertOk();
    expect($post->fresh()->nl_template_custom)->toBe('<div>[content]</div>');

    FaPer3Server::actingAs(withMcp($user->fresh(), 'write'))->tool(UpdatePost::class, [
        'post_id' => $post->id,
        'newsletter_html' => null,
    ])->assertOk();
    expect($post->fresh()->nl_template_custom)->toBeNull();
});

/** Utente con la sola newsletter, provider SMTP (nessuna lista esterna da scegliere). */
function smtpNewsletterWriter(): User
{
    $user = mcpWriter(['newsletter']);
    $user->settings->update(['nl_smtp_host' => 'smtp.example.test']);

    return $user->fresh();
}
