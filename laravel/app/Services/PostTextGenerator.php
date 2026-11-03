<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Testo del post generato dal servizio Python (/generate-text), con la
 * chiave OpenAI e la personalità AI dell'account. Usato da "Genera anteprima"
 * (PostContentController) e dal job che genera il testo dei post programmati.
 */
class PostTextGenerator
{
    /**
     * @return array{content: string, tokens: int}
     *
     * @throws RuntimeException messaggio leggibile per l'utente
     */
    public function generate(Settings $settings, string $prompt, ?string $channel): array
    {
        try {
            $response = Http::timeout(60)->post(config('services.python.url').'/generate-text', [
                'ai_prompt_post' => $prompt,
                'channel' => $channel,
                'api_key' => $settings->openai_api_key,
                'ai_personality' => $settings->ai_personality,
                'ai_prompt_prefix' => $settings->ai_prompt_prefix,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Servizio AI non raggiungibile, riprova.');
        }

        if ($response->failed()) {
            throw new RuntimeException($response->json('detail') ?? 'Errore generazione testo');
        }

        return [
            'content' => (string) $response->json('content'),
            'tokens' => (int) $response->json('tokens'),
        ];
    }
}
