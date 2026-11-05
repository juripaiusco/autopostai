<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\TokenLog;
use App\Services\PostTextGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera il testo di un post programmato salvato senza anteprima, così il
 * cliente può rivederlo prima dell'uscita. Se qui qualcosa non va, il testo
 * resta vuoto e lo genera il worker Python al momento della pubblicazione.
 */
class GeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $postId)
    {
    }

    public function handle(PostTextGenerator $generator): void
    {
        $post = Post::with('user.settings')->find($this->postId);
        if (! $post || (string) $post->published !== '0' || ! $post->needsGeneratedText()) {
            return;
        }

        $user = $post->user;
        $settings = $user?->settings;
        if (empty($settings?->openai_api_key)) {
            return;
        }

        // Stessa regola del worker (TokenLogRepository.is_over_limit): usati >=
        // limite, limite vuoto = bloccato. Il post lo abbandona poi il worker.
        if ((int) $user->tokensUsed()->sum('tokens_used') >= (int) $user->tokens_limit) {
            return;
        }

        try {
            $result = $generator->generate($settings, $post->ai_prompt_post, $post->textChannel(), $post->activeChannels());
        } catch (\Throwable $e) {
            Log::warning('GeneratePostContentJob failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);

            return;
        }

        TokenLog::create([
            'user_id' => $user->id,
            'type' => 'post',
            'reference_id' => $post->id,
            'tokens_used' => $result['tokens'],
        ]);

        if (trim($result['content']) === '') {
            return;
        }

        // Solo se nel frattempo nessuno ha scritto un testo (utente dal form o
        // worker già partito) e il post non è uscito.
        Post::whereKey($post->id)
            ->where('published', '0')
            ->whereRaw("TRIM(COALESCE(ai_content, '')) = ''")
            ->update(['ai_content' => $result['content']]);
    }
}
