<?php

namespace Tests\Feature;

use App\Jobs\GeneratePostContentJob;
use App\Models\Post;
use App\Models\Settings;
use App\Models\TokenLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Post programmato salvato senza "Genera anteprima": un job genera il testo
 * prima dell'uscita, così il cliente può rivederlo. Il worker Python resta il
 * paracadute (genera lui alla pubblicazione se qui il testo manca).
 */
class PostAutoContentTest extends TestCase
{
    use RefreshDatabase;

    private function simpleUser(array $attributes = []): User
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create($attributes + [
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true], 'wordpress' => ['on' => true]],
            'tokens_limit' => 100000,
        ]);
        Settings::factory()->create(['user_id' => $user->id, 'openai_api_key' => 'sk-test-key']);

        return $user;
    }

    private function store(User $user, array $fields): void
    {
        $this->actingAs($user)->post(route('posts.store'), $fields + [
            'title' => 'Post',
            'channels' => ['facebook' => []],
            'ai_prompt_post' => 'Scrivi un post sulla pizza',
            'published_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'action' => 'save',
        ])->assertRedirect();
    }

    private function scheduledPost(User $user, array $attributes = []): Post
    {
        return Post::factory()->create($attributes + [
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'channels' => ['facebook' => ['on' => true]],
            'ai_prompt_post' => 'Scrivi un post sulla pizza',
            'ai_content' => null,
            'published' => '0',
            'published_at' => now()->addDay(),
        ]);
    }

    public function test_scheduled_post_without_text_queues_the_generation(): void
    {
        Queue::fake();
        $user = $this->simpleUser();

        $this->store($user, []);

        Queue::assertPushed(GeneratePostContentJob::class);
        $this->assertStringContainsString('generato dall\'AI', session('toast'));
    }

    public function test_update_of_a_scheduled_post_without_text_queues_the_generation(): void
    {
        Queue::fake();
        $user = $this->simpleUser();
        $post = $this->scheduledPost($user);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => 'Post',
            'channels' => ['facebook' => []],
            'ai_prompt_post' => 'Scrivi un post sulla pizza',
            'ai_content' => '',
            'published_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'action' => 'save',
        ])->assertRedirect();

        Queue::assertPushed(GeneratePostContentJob::class, fn ($job) => $job->postId === $post->id);
    }

    public function test_no_generation_for_drafts_publish_now_or_posts_with_text(): void
    {
        Queue::fake();
        $user = $this->simpleUser();

        $this->store($user, ['published_at' => '']);                      // bozza
        $this->store($user, ['action' => 'publish_now']);                 // genera il worker
        $this->store($user, ['ai_content' => 'Testo scritto a mano']);    // anteprima presente
        $this->store($user, ['ai_prompt_post' => '']);                    // niente da generare

        Queue::assertNothingPushed();
    }

    public function test_job_saves_the_text_and_logs_the_tokens(): void
    {
        $user = $this->simpleUser();
        $post = $this->scheduledPost($user, ['channels' => ['facebook' => ['on' => true], 'wordpress' => ['on' => true]]]);
        Http::fake(['*/generate-text' => Http::response(['content' => 'Pizza per tutti!', 'tokens' => 321])]);

        GeneratePostContentJob::dispatchSync($post->id);

        $this->assertSame('Pizza per tutti!', $post->fresh()->ai_content);
        $this->assertDatabaseHas('token_logs', [
            'user_id' => $user->id, 'type' => 'post', 'reference_id' => $post->id, 'tokens_used' => 321,
        ]);
        // WordPress acceso: prompt Markdown, come "Genera anteprima".
        Http::assertSent(fn ($request) => $request['channel'] === 'wordpress' && $request['api_key'] === 'sk-test-key');
    }

    public function test_job_never_overwrites_a_text_written_in_the_meantime(): void
    {
        $user = $this->simpleUser();
        $post = $this->scheduledPost($user);
        Http::fake(function () use ($post) {
            // L'utente salva la sua anteprima mentre l'AI sta rispondendo.
            Post::whereKey($post->id)->update(['ai_content' => 'Testo dell\'utente']);

            return Http::response(['content' => 'Testo AI', 'tokens' => 10]);
        });

        GeneratePostContentJob::dispatchSync($post->id);

        $this->assertSame('Testo dell\'utente', $post->fresh()->ai_content);
    }

    public function test_job_failure_or_empty_reply_leaves_the_text_to_the_worker(): void
    {
        $user = $this->simpleUser();
        $failed = $this->scheduledPost($user);
        $empty = $this->scheduledPost($user);
        Http::fakeSequence('*/generate-text')
            ->push(['detail' => 'Incorrect API key'], 422)
            ->push(['content' => '  ', 'tokens' => 5]);

        GeneratePostContentJob::dispatchSync($failed->id);
        GeneratePostContentJob::dispatchSync($empty->id);

        $this->assertNull($failed->fresh()->ai_content);
        $this->assertNull($empty->fresh()->ai_content);
        $this->assertSame(0, TokenLog::where('reference_id', $failed->id)->count());
    }

    public function test_job_skips_accounts_over_the_token_limit(): void
    {
        Http::fake();
        $user = $this->simpleUser(['tokens_limit' => 1000]);
        TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => 0, 'tokens_used' => 1000]);
        $post = $this->scheduledPost($user);

        GeneratePostContentJob::dispatchSync($post->id);

        Http::assertNothingSent();
        $this->assertNull($post->fresh()->ai_content);
    }

    public function test_preview_shows_the_service_error_message(): void
    {
        $user = $this->simpleUser();
        Http::fake(['*/generate-text' => Http::response(['detail' => "L'AI ha restituito un testo vuoto, riprova."], 422)]);

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi'])
            ->assertStatus(422)
            ->assertJson(['message' => "L'AI ha restituito un testo vuoto, riprova."]);
    }

    public function test_generation_sends_the_active_channels_for_the_linkedin_limit(): void
    {
        $user = $this->simpleUser();
        $post = $this->scheduledPost($user, ['channels' => [
            'facebook' => ['on' => true], 'linkedin' => ['on' => true], 'instagram' => ['on' => false],
        ]]);
        Http::fake(['*/generate-text' => Http::response(['content' => 'Testo', 'tokens' => 1])]);

        GeneratePostContentJob::dispatchSync($post->id);

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi', 'channels' => ['linkedin']])
            ->assertOk();

        Http::assertSent(fn ($request) => $request['channels'] === ['facebook', 'linkedin']);
        Http::assertSent(fn ($request) => $request['channels'] === ['linkedin']);
    }

    public function test_preview_rejects_unknown_channels(): void
    {
        $user = $this->simpleUser();
        Http::fake();

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi', 'channels' => ['tiktok']])
            ->assertStatus(422);

        Http::assertNothingSent();
    }
}
