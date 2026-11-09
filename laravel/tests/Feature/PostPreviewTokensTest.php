<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Settings;
use App\Models\TokenLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Genera anteprima" del form: i token vanno contati (limite mensile +
 * TokenLog) e, al salvataggio, attribuiti al post. Prima il form chiamava il
 * servizio senza log: il post usciva col testo già pronto e il consumo non
 * risultava da nessuna parte.
 */
class PostPreviewTokensTest extends TestCase
{
    use RefreshDatabase;

    private function simpleUser(array $attributes = []): User
    {
        $admin = User::factory()->create(['parent_id' => null]);
        $user = User::factory()->create($attributes + [
            'parent_id' => $admin->id,
            'channels' => ['facebook' => ['on' => true]],
            'tokens_limit' => 100000,
        ]);
        Settings::factory()->create(['user_id' => $user->id, 'openai_api_key' => 'sk-test-key-'.$user->id]);

        return $user;
    }

    private function previewLog(User $user, int $tokens = 100): TokenLog
    {
        return TokenLog::create(['user_id' => $user->id, 'type' => 'preview', 'reference_id' => 0, 'tokens_used' => $tokens]);
    }

    public function test_preview_logs_the_tokens_and_returns_the_log_id(): void
    {
        $user = $this->simpleUser();
        Http::fake(['*/generate-text' => Http::response(['content' => 'Pizza!', 'tokens' => 250])]);

        $response = $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi'])
            ->assertOk()
            ->assertJsonPath('content', 'Pizza!');

        $log = TokenLog::findOrFail($response->json('token_log_id'));
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('preview', $log->type);
        $this->assertSame(250, $log->tokens_used);
    }

    public function test_preview_is_blocked_over_the_monthly_limit(): void
    {
        $user = $this->simpleUser(['tokens_limit' => 1000]);
        $this->previewLog($user, 1000);
        Http::fake();

        $this->actingAs($user)
            ->postJson(route('posts.generate-text', $user), ['ai_prompt_post' => 'Scrivi'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Limite mensile di token AI raggiunto per questo account.');

        Http::assertNothingSent();
        $this->assertSame(1, TokenLog::count());
    }

    public function test_store_attaches_the_preview_tokens_to_the_post(): void
    {
        $user = $this->simpleUser();
        $first = $this->previewLog($user, 100);
        $second = $this->previewLog($user, 150);

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Post',
            'channels' => ['facebook' => []],
            'ai_prompt_post' => 'Scrivi',
            'ai_content' => 'Pizza!',
            'action' => 'save',
            'preview_token_log_ids' => [$first->id, $second->id],
        ])->assertRedirect();

        $post = Post::firstOrFail();
        foreach ([$first, $second] as $log) {
            $log->refresh();
            $this->assertSame('post', $log->type);
            $this->assertSame($post->id, $log->reference_id);
        }
        $this->assertSame(250, (int) $post->tokens()->sum('tokens_used'));
    }

    public function test_update_attaches_the_preview_tokens_to_the_post(): void
    {
        $user = $this->simpleUser();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'channels' => ['facebook' => ['on' => true]],
            'published' => '0',
        ]);
        $log = $this->previewLog($user);

        $this->actingAs($user)->put(route('posts.update', $post), [
            'title' => 'Post',
            'channels' => ['facebook' => []],
            'ai_content' => 'Pizza!',
            'action' => 'save',
            'preview_token_log_ids' => [$log->id],
        ])->assertRedirect();

        $this->assertSame($post->id, $log->refresh()->reference_id);
        $this->assertSame('post', $log->type);
    }

    public function test_logs_of_other_accounts_or_already_attached_are_not_moved(): void
    {
        $user = $this->simpleUser();
        $other = $this->simpleUser();
        $foreign = $this->previewLog($other);
        $attached = TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => 999, 'tokens_used' => 50]);

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Post',
            'channels' => ['facebook' => []],
            'ai_content' => 'Pizza!',
            'action' => 'save',
            'preview_token_log_ids' => [$foreign->id, $attached->id],
        ])->assertRedirect();

        $this->assertSame('preview', $foreign->refresh()->type);
        $this->assertSame(0, $foreign->reference_id);
        $this->assertSame(999, $attached->refresh()->reference_id);
    }

    public function test_account_list_counts_distinct_posts(): void
    {
        $user = $this->simpleUser();
        $admin = User::findOrFail($user->parent_id);
        TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => 10, 'tokens_used' => 100]);
        TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => 10, 'tokens_used' => 40]);
        TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => 11, 'tokens_used' => 10]);

        $this->actingAs($admin)->get(route('account'))
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.post', 2)
                ->where('users.data.0.tokenUsed', 150));
    }

    public function test_show_sums_every_post_log(): void
    {
        $user = $this->simpleUser();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'channels' => ['facebook' => ['on' => true]],
        ]);
        TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => $post->id, 'tokens_used' => 100]);
        TokenLog::create(['user_id' => $user->id, 'type' => 'post', 'reference_id' => $post->id, 'tokens_used' => 40]);

        $this->actingAs($user)->get(route('posts.show', $post))
            ->assertInertia(fn ($page) => $page
                ->where('post.postTokens', 140)
                ->where('post.totalTokens', 140));
    }
}
