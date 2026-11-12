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
     * $channels: canali accesi sul post, per il limite di caratteri (LinkedIn)
     * che il servizio Python aggiunge al prompt. $sourceText: testo
     * dell'articolo da riscrivere come post social ("Genera versione
     * social"); con questo $channel non conta e $prompt fa da istruzioni extra.
     *
     * `warnings`: avvisi per l'utente dal servizio Python (link del prompt non
     * leggibili, immagini inventate tolte dal testo), vuoto se nulla da segnalare.
     *
     * @param  array<int, string>  $channels
     * @return array{content: string, tokens: int, warnings: array<int, string>}
     *
     * @throws RuntimeException messaggio leggibile per l'utente
     */
    public function generate(Settings $settings, string $prompt, ?string $channel, array $channels = [], ?string $sourceText = null): array
    {
        try {
            $response = Http::timeout(60)->post(config('services.python.url').'/generate-text', [
                'ai_prompt_post' => $prompt,
                'channel' => $channel,
                'channels' => array_values($channels),
                'source_text' => $sourceText,
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
            'warnings' => array_values(array_map('strval', (array) $response->json('warnings', []))),
        ];
    }
}
