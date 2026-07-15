<?php

namespace App\Jobs;

use App\Http\Controllers\ImageArchiveController;
use App\Models\ImageJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $imageJobId)
    {
    }

    public function handle(): void
    {
        $job = ImageJob::find($this->imageJobId);
        if (! $job) {
            return;
        }

        $job->update(['status' => 'running']);

        try {
            $response = Http::timeout(100)->post(config('services.python.url').'/generate-image', [
                'prompt' => $job->prompt,
                'model' => $job->model,
                'api_key' => $job->user->settings?->openai_api_key ?? '',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->json('detail') ?? 'Errore generazione immagine');
            }

            $bytes = base64_decode($response->json('image_base64'), true);
            if ($bytes === false) {
                throw new \RuntimeException('Risposta immagine non valida');
            }

            $filename = Str::uuid().'.png';
            $folder = ImageArchiveController::folderForModel($job->model);
            Storage::disk('public')->put("{$folder}/{$job->user_id}/{$filename}", $bytes);

            $job->update(['status' => 'completed', 'image_url' => $filename]);
        } catch (\Throwable $e) {
            Log::error('GenerateImageJob failed', ['image_job_id' => $job->id, 'error' => $e->getMessage()]);
            $job->update(['status' => 'failed']);
        }
    }
}
