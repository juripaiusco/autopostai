<?php

namespace Tests\Feature;

use App\Actions\Posts\DuplicatePost;
use App\Models\Post;
use App\Models\Settings;
use App\Models\User;
use App\Services\PostHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Formato del testo del post (markdown / html / text), testo social
 * personalizzato e sanificazione dell'HTML dell'editor ricco.
 */
class PostContentFormatTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true], 'wordpress' => ['on' => true]],
        ]);
    }

    private function makeDraft(User $user, array $attrs = []): Post
    {
        return Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'published' => '0',
            'published_at' => null,
            'channels' => ['facebook' => ['on' => true], 'wordpress' => ['on' => true, 'categories' => []]],
            ...$attrs,
        ]);
    }

    public function test_sanitizer_keeps_editor_markup_and_drops_the_rest(): void
    {
        $html = '<h1>Titolo</h1>'
            .'<p style="text-align: center; position: fixed">Ciao <strong>tutti</strong> '
            .'<span style="color: #c0392b; background: url(x)">rosso</span></p>'
            .'<p onclick="alert(1)">click<script>alert(2)</script></p>'
            .'<ul><li><em>uno</em></li></ul><blockquote>cit</blockquote>'
            .'<iframe src="https://evil.test"></iframe>'
            .'<p><a href="javascript:alert(3)">js</a> <a href="https://shop.test" target="_blank">shop</a></p>';

        $clean = app(PostHtmlSanitizer::class)->clean($html);

        $this->assertStringContainsString('<h1>Titolo</h1>', $clean);
        $this->assertStringContainsString('text-align:center', $clean);
        $this->assertStringContainsString('color:#c0392b', $clean);
        $this->assertStringContainsString('<strong>tutti</strong>', $clean);
        $this->assertStringContainsString('<ul><li><em>uno</em></li></ul>', $clean);
        $this->assertStringContainsString('<blockquote>cit</blockquote>', $clean);
        $this->assertStringContainsString('href="https://shop.test"', $clean);
        foreach (['position', 'background', 'onclick', 'script', 'alert', 'iframe', 'javascript'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
    }

    public function test_sanitizer_keeps_https_images_and_drops_unsafe_ones(): void
    {
        // Immagini prodotto inserite dall'AI dalle pagine linkate nel prompt.
        $html = '<p>Scarpa</p><img src="https://shop.test/img/x.jpg" alt="Scarpa" onerror="alert(1)" style="position:fixed">'
            .'<img src="javascript:alert(2)" alt="js">'
            .'<img src="data:image/png;base64,AAAA" alt="b64">';

        $clean = app(PostHtmlSanitizer::class)->clean($html);

        $this->assertStringContainsString('<img src="https://shop.test/img/x.jpg" alt="Scarpa"', $clean);
        foreach (['onerror', 'alert', 'position', 'javascript', 'data:image'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
    }

    public function test_sanitizer_keeps_shortcodes_in_links_and_text(): void
    {
        $html = '<p><a href="[wordpress url id=12]">articolo</a></p>'
            .'<p>[cta url="https://shop.test" text="Compra ora"]</p>';

        $clean = app(PostHtmlSanitizer::class)->clean($html);

        $this->assertStringContainsString('<a href="[wordpress url id=12]">articolo</a>', $clean);
        $this->assertStringContainsString('[cta url="https://shop.test" text="Compra ora"]', $clean);
    }

    public function test_sanitizer_keeps_the_quoted_shortcode_format(): void
    {
        // Come lo produce l'editor: virgolette dell'id codificate nell'attributo.
        $html = '<p><a href="[WordPress url id=&quot;123&quot;]">articolo</a></p>'
            .'<p>[cta WordPress id="123" text="clicca qui"]</p>';

        $clean = app(PostHtmlSanitizer::class)->clean($html);

        $this->assertStringContainsString('<a href="[WordPress url id=&quot;123&quot;]">articolo</a>', $clean);
        $this->assertStringContainsString('[cta WordPress id="123" text="clicca qui"]', $clean);
    }

    public function test_store_sanitizes_html_and_saves_format_and_social_text(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Articolo',
            'channels' => ['facebook' => [], 'wordpress' => []],
            'ai_content' => '<h1>Titolo</h1><p>Testo<script>x()</script></p>',
            'ai_content_format' => 'html',
            'ai_content_social' => 'Nuovo articolo sul blog! 🎉',
            'action' => 'save',
        ])->assertRedirect(route('posts'));

        $post = Post::where('title', 'Articolo')->firstOrFail();
        $this->assertSame('html', $post->ai_content_format);
        $this->assertSame('<h1>Titolo</h1><p>Testo</p>', $post->ai_content);
        $this->assertSame('Nuovo articolo sul blog! 🎉', $post->ai_content_social);
    }

    public function test_store_defaults_to_markdown_and_empty_text_is_never_html(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Vecchio form',
            'channels' => ['facebook' => []],
            'ai_content' => '# Titolo **grassetto**',
            'action' => 'save',
        ]);
        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Vuoto',
            'channels' => ['wordpress' => []],
            'ai_content' => '',
            'ai_content_format' => 'html',
            'ai_content_social' => '   ',
            'action' => 'save',
        ]);

        $this->assertSame('markdown', Post::where('title', 'Vecchio form')->value('ai_content_format'));
        $this->assertSame('# Titolo **grassetto**', Post::where('title', 'Vecchio form')->value('ai_content'));
        $this->assertSame('markdown', Post::where('title', 'Vuoto')->value('ai_content_format'));
        $this->assertNull(Post::where('title', 'Vuoto')->value('ai_content_social'));
    }

    public function test_store_rejects_an_unknown_format(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('posts.store'), [
            'channels' => ['facebook' => []],
            'ai_content' => 'x',
            'ai_content_format' => 'docx',
            'action' => 'save',
        ])->assertSessionHasErrors('ai_content_format');
    }

    public function test_update_without_format_keeps_the_current_one(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, [
            'ai_content' => '<p>vecchio</p>',
            'ai_content_format' => 'html',
            'ai_content_social' => 'social',
        ]);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => [], 'wordpress' => []],
            'ai_content' => '<p>nuovo <b onmouseover="x()">testo</b></p>',
        ])->assertRedirect(route('posts'));

        $post->refresh();
        $this->assertSame('html', $post->ai_content_format);
        $this->assertSame('<p>nuovo <b>testo</b></p>', $post->ai_content);
        $this->assertSame('social', $post->ai_content_social);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'channels' => ['facebook' => [], 'wordpress' => []],
            'ai_content' => '<p>nuovo</p>',
            'ai_content_social' => '',
        ]);
        $this->assertNull($post->fresh()->ai_content_social);
    }

    public function test_duplicate_copies_format_and_social_text_save_and_add_resets_them(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, [
            'ai_content' => '<p>articolo</p>',
            'ai_content_format' => 'html',
            'ai_content_social' => 'social',
        ]);

        $copy = app(DuplicatePost::class)->handle($user, $post);
        $next = app(DuplicatePost::class)->nextInSeries($user, $post);

        $this->assertSame('html', $copy->ai_content_format);
        $this->assertSame('social', $copy->ai_content_social);
        $this->assertSame('markdown', $next->ai_content_format);
        $this->assertNull($next->ai_content_social);
    }

    public function test_edit_gives_the_rich_editor_html_converted_from_markdown(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, ['ai_content' => "# Titolo\n\nTesto **forte** <script>x()</script>", 'ai_content_format' => 'markdown']);

        $this->actingAs($user)->get(route('posts.edit', $post))->assertInertia(fn (Assert $page) => $page
            ->where('post.ai_content_format', 'html')
            ->where('post.ai_content', "<h1>Titolo</h1>\n<p>Testo <strong>forte</strong> &lt;script&gt;x()&lt;/script&gt;</p>")
        );
    }

    public function test_edit_leaves_social_only_text_alone(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, [
            'channels' => ['facebook' => ['on' => true]],
            'ai_content' => 'Promo **weekend**',
            'ai_content_format' => 'text',
        ]);

        $this->actingAs($user)->get(route('posts.edit', $post))->assertInertia(fn (Assert $page) => $page
            ->where('post.ai_content_format', 'text')
            ->where('post.ai_content', 'Promo **weekend**')
        );
    }

    public function test_show_renders_the_article_as_safe_html(): void
    {
        $user = $this->makeUser();
        $post = $this->makeDraft($user, ['ai_content' => '<h1>Titolo</h1><p onclick="x()">Testo</p>', 'ai_content_format' => 'html']);
        $social = $this->makeDraft($user, ['channels' => ['facebook' => ['on' => true]], 'ai_content' => 'Solo social']);

        $this->actingAs($user)->get(route('posts.show', $post))->assertInertia(fn (Assert $page) => $page
            ->where('post.aiContentHtml', '<h1>Titolo</h1><p>Testo</p>')
        );
        $this->actingAs($user)->get(route('posts.show', $social))->assertInertia(fn (Assert $page) => $page
            ->where('post.aiContentHtml', null)
            ->where('post.aiContent', 'Solo social')
        );
    }

    public function test_preview_returns_html_for_article_channels_only(): void
    {
        $user = $this->makeUser();
        $user->update(['tokens_limit' => 100000]);
        Settings::factory()->create(['user_id' => $user->id, 'openai_api_key' => 'sk-test']);
        Http::fake(['*/generate-text' => Http::response(['content' => "# Titolo\n\nCorpo", 'tokens' => 10])]);

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi', 'channel' => 'wordpress'])
            ->assertOk()
            ->assertJsonPath('content', "# Titolo\n\nCorpo")
            ->assertJsonPath('html', "<h1>Titolo</h1>\n<p>Corpo</p>");

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi'])
            ->assertOk()
            ->assertJsonPath('html', null);
    }

    public function test_social_version_sends_the_article_and_returns_plain_text(): void
    {
        $user = $this->makeUser();
        $user->update(['tokens_limit' => 100000]);
        Settings::factory()->create(['user_id' => $user->id, 'openai_api_key' => 'sk-test']);
        Http::fake(['*/generate-text' => Http::response(['content' => 'Novità! 🎉', 'tokens' => 10])]);

        $response = $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), [
                'source_text' => "Titolo\n\nCorpo dell'articolo",
                'channel' => 'wordpress',
                'channels' => ['wordpress', 'facebook'],
            ])
            ->assertOk()
            ->assertJsonPath('content', 'Novità! 🎉')
            ->assertJsonPath('html', null);

        $this->assertNotNull($response->json('token_log_id'));
        Http::assertSent(fn ($request) => $request['source_text'] === "Titolo\n\nCorpo dell'articolo"
            && $request['ai_prompt_post'] === '');
    }

    public function test_preview_needs_a_prompt_or_an_article(): void
    {
        $user = $this->makeUser();
        Http::fake();

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['channels' => ['facebook']])
            ->assertStatus(422);

        Http::assertNothingSent();
    }
}
