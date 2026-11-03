<?php

namespace App\Actions\Posts;

use App\Models\TokenLog;
use App\Models\User;
use App\Services\PostTextGenerator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Anteprima del testo di un post (non salva nulla sul post), con la stessa
 * regola di consumo del job e del worker: limite mensile di token
 * dell'account e TokenLog del consumo (type "preview", nessun post di
 * riferimento: reference_id 0). Usata dal tool MCP generate_post_text;
 * "Genera anteprima" del form non la usa ancora (non conta i token).
 */
class GeneratePostTextPreview
{
    public function __construct(private PostTextGenerator $generator) {}

    /**
     * @return array{content: string, tokens: int}
     *
     * @throws ValidationException chiave mancante, limite raggiunto o errore del servizio AI
     */
    public function handle(User $account, string $prompt, ?string $channel): array
    {
        $settings = $account->settings;

        if (empty($settings?->openai_api_key)) {
            throw ValidationException::withMessages(['prompt' => 'Nessuna chiave OpenAI collegata per questo account. Collegala in Impostazioni AI.']);
        }

        // Come GeneratePostContentJob e il worker: usati >= limite, limite vuoto = bloccato.
        if ((int) $account->tokensUsed()->sum('tokens_used') >= (int) $account->tokens_limit) {
            throw ValidationException::withMessages(['prompt' => 'Limite mensile di token AI raggiunto per questo account.']);
        }

        try {
            $result = $this->generator->generate($settings, $prompt, $channel);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['prompt' => $e->getMessage()]);
        }

        TokenLog::create([
            'user_id' => $account->id,
            'type' => 'preview',
            'reference_id' => 0,
            'tokens_used' => $result['tokens'],
        ]);

        return $result;
    }
}
