<?php

namespace App\Actions\Images;

use App\Jobs\GenerateImageJob;
use App\Models\ImageJob;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Avvia la generazione di un'immagine AI per l'account $account: crea la
 * riga image_jobs (status=pending) e dispatcha il job che chiama il servizio
 * Python. Il chiamante fa poi polling sullo stato del job. Form post e tool MCP.
 */
class StartImageGeneration
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
            'model' => ['required', 'string', Rule::in(array_keys(config('ai.image_models')))],
        ];
    }

    /**
     * @throws ValidationException limite giornaliero raggiunto o chiave OpenAI mancante
     */
    public function handle(User $account, User $actor, string $prompt, string $model): ImageJob
    {
        if ($account->imagesUsed()->count() >= $account->image_model_limit) {
            throw ValidationException::withMessages(['prompt' => 'Limite giornaliero di immagini generate raggiunto.']);
        }

        if (empty($account->settings?->openai_api_key)) {
            throw ValidationException::withMessages(['prompt' => 'Nessuna chiave OpenAI collegata per questo account. Collegala in Impostazioni AI.']);
        }

        $job = ImageJob::create([
            'user_id' => $account->id,
            'created_by_user_id' => $actor->id,
            'status' => 'pending',
            'prompt' => $prompt,
            'model' => $model,
        ]);

        GenerateImageJob::dispatch($job->id);

        return $job;
    }
}
