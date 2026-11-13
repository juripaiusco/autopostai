<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\EmailHtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Template HTML della newsletter del singolo post (posts.nl_template_custom):
 * salvataggio, pulizia, oggetto obbligatorio e testo AI non richiesto quando
 * il template non contiene [content].
 */
class PostNewsletterTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE = '<!DOCTYPE html><html><head><style>td{color:red}</style></head>'
        .'<body><table><tr><td style="padding:8px">Ciao [image 1]</td></tr></table></body></html>';

    private function makeUser(): User
    {
        $admin = User::factory()->create(['parent_id' => null]);

        return User::factory()->create([
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true], 'newsletter' => ['on' => true]],
        ]);
    }

    private function store(User $user, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('posts.store'), [
            'channels' => ['newsletter' => []],
            'action' => 'save',
            ...$data,
        ]);
    }

    public function test_store_saves_the_post_template_keeping_the_email_layout(): void
    {
        $user = $this->makeUser();

        $this->store($user, ['title' => 'Oggetto mail', 'nl_template_custom' => self::TEMPLATE])
            ->assertRedirect(route('posts'));

        $template = Post::where('title', 'Oggetto mail')->firstOrFail()->nl_template_custom;
        $this->assertStringContainsString('<style>td{color:red}</style>', $template);
        $this->assertStringContainsString('<td style="padding:8px">Ciao [image 1]</td>', $template);
        $this->assertStringStartsWith('<!DOCTYPE html>', $template);
    }

    public function test_template_requires_the_subject(): void
    {
        $this->store($this->makeUser(), ['title' => '', 'nl_template_custom' => self::TEMPLATE])
            ->assertSessionHasErrors('title');
    }

    public function test_template_is_dropped_when_newsletter_is_not_a_channel(): void
    {
        $user = $this->makeUser();

        $this->store($user, ['title' => 'Solo FB', 'channels' => ['facebook' => []], 'nl_template_custom' => self::TEMPLATE]);

        $this->assertNull(Post::where('title', 'Solo FB')->firstOrFail()->nl_template_custom);
    }

    public function test_sanitizer_removes_scripts_events_and_javascript_urls(): void
    {
        $clean = app(EmailHtmlSanitizer::class)->clean(
            '<style>p{margin:0}</style><p onclick="x()">Testo</p><script>alert(1)</script>'
            .'<a href=" java&#9;script:alert(1)">no</a><a href="https://ok.test">sì</a>'
            .'<iframe src="https://evil.test"></iframe><img src="data:image/svg+xml,x"><img src="data:image/png;base64,AA">'
            .'<p>[cta url="https://ok.test" text="Vai"] [content]</p>'
        );

        $this->assertStringContainsString('<style>p{margin:0}</style>', $clean);
        $this->assertStringContainsString('<p>Testo</p>', $clean);
        $this->assertStringContainsString('<a>no</a>', $clean);
        $this->assertStringContainsString('<a href="https://ok.test">sì</a>', $clean);
        $this->assertStringContainsString('<img src="data:image/png;base64,AA">', $clean);
        $this->assertStringContainsString('[cta url="https://ok.test" text="Vai"] [content]', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('svg', $clean);
        $this->assertStringNotContainsString('<html', $clean);
    }

    public function test_newsletter_alone_with_template_without_content_needs_no_ai_text(): void
    {
        $post = new Post([
            'channels' => ['newsletter' => ['on' => true], 'facebook' => ['on' => false]],
            'nl_template_custom' => self::TEMPLATE,
            'ai_prompt_post' => 'scrivi qualcosa',
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse($post->needsCommonText());
        $this->assertFalse($post->needsGeneratedText());
    }

    public function test_ai_text_is_still_needed_with_content_placeholder_or_other_channels(): void
    {
        $withContent = new Post([
            'channels' => ['newsletter' => ['on' => true]],
            'nl_template_custom' => '<div>[content]</div>',
        ]);
        $withFacebook = new Post([
            'channels' => ['newsletter' => ['on' => true], 'facebook' => ['on' => true]],
            'nl_template_custom' => self::TEMPLATE,
        ]);

        $this->assertTrue($withContent->needsCommonText());
        $this->assertTrue($withFacebook->needsCommonText());
    }
}
