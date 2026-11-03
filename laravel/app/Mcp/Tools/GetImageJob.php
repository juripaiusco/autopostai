<?php

namespace App\Mcp\Tools;

use App\Models\ImageJob;
use App\Services\ImageArchive;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_image_job')]
#[Title('Stato generazione immagine')]
#[Description('Stato di una generazione avviata con generate_image: pending, running, completed (con filename da usare in archive_images e url) o failed.')]
#[IsReadOnly]
class GetImageJob extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['job_id' => ['required', 'integer']]);

        $job = ImageJob::with('user')->find($data['job_id']);

        if (!$job || !$job->user || !$this->me()->canActFor($job->user)) {
            throw ValidationException::withMessages(['job_id' => 'Generazione non trovata.']);
        }

        return Response::structured(array_filter([
            'job_id' => $job->id,
            'account_id' => $job->user_id,
            'status' => $job->status,
            'prompt' => $job->prompt,
            'filename' => $job->status === 'completed' ? $job->image_url : null,
            'url' => $job->status === 'completed'
                ? Storage::disk('public')->url(ImageArchive::folderForModel($job->model)."/{$job->user_id}/{$job->image_url}")
                : null,
        ], fn ($v) => $v !== null));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->required(),
        ];
    }
}
