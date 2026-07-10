<?php

namespace App\Http\Controllers;

use App\Models\ImageJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageArchiveController extends Controller
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    private const FOLDERS = [
        'dall-e' => 'dall-e-3',
        'stable-diffusion' => 'stable-diffusion',
    ];

    /**
     * Archivio immagini AI dell'account $user: unione di storage/app/public/
     * {dall-e,stable-diffusion}/{user->id}/*, incrociata con image_jobs per
     * recuperare il prompt quando disponibile (alcuni file possono non avere
     * una riga corrispondente — restano comunque selezionabili, senza prompt).
     */
    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        $jobsByFilename = ImageJob::where('user_id', $user->id)->get()
            ->keyBy(fn (ImageJob $job) => basename((string) $job->image_url));

        $images = collect(self::FOLDERS)
            ->flatMap(function (string $defaultModel, string $folder) use ($user, $jobsByFilename) {
                return collect(Storage::disk('public')->files("{$folder}/{$user->id}"))
                    ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true))
                    ->map(function ($path) use ($folder, $defaultModel, $jobsByFilename) {
                        $filename = basename($path);
                        $job = $jobsByFilename->get($filename);

                        return [
                            'filename' => $filename,
                            'url' => Storage::disk('public')->url($path),
                            'prompt' => $job?->prompt,
                            'model' => $job?->model ?? $defaultModel,
                            'createdAt' => $job?->created_at?->toIso8601String(),
                            'sortKey' => Storage::disk('public')->lastModified($path),
                        ];
                    });
            })
            ->sortByDesc('sortKey')
            ->map(fn ($img) => collect($img)->except('sortKey')->all())
            ->values();

        return response()->json(['images' => $images]);
    }

    /**
     * Persiste una nuova immagine "generata" (oggi il mock canvas-gradient
     * lato client) nell'archivio dell'account $user: file in dall-e/{id}/ +
     * riga image_jobs con il prompt, cosi' resta nello storico anche se non
     * viene mai usata in un post.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $filename = Str::uuid().'.png';
        Storage::disk('public')->putFileAs("dall-e/{$user->id}", $request->file('image'), $filename);

        $job = ImageJob::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'image_url' => $filename,
            'prompt' => $data['prompt'],
            'model' => 'dall-e-3',
        ]);

        return response()->json([
            'filename' => $filename,
            'url' => Storage::disk('public')->url("dall-e/{$user->id}/{$filename}"),
            'prompt' => $job->prompt,
            'model' => $job->model,
            'createdAt' => $job->created_at->toIso8601String(),
        ]);
    }
}
