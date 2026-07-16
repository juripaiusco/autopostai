<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateImageJob;
use App\Models\ImageJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImageArchiveController extends Controller
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * Modelli AI supportati -> cartella di storage in cui finiscono i file
     * generati con quel modello. Un solo provider (OpenAI) per ora; gli
     * altri (Stability, Grok, Hugging Face) aggiungeranno una entry qui e
     * il relativo provider Python, senza altre modifiche a questo controller.
     */
    private const FOLDERS = [
        'gpt-image-1' => 'openai',
    ];

    public static function folderForModel(string $model): string
    {
        return self::FOLDERS[$model] ?? 'openai';
    }

    /**
     * Archivio immagini AI visibile a $request->user() nel contesto del post
     * dell'account $user: unione di storage/app/public/{folder}/{id}/* per
     * $user e per l'attore loggato (coincidono per un utente semplice, che
     * può agire solo per sé stesso — vede quindi solo le proprie), incrociata
     * con image_jobs per recuperare il prompt quando disponibile (alcuni file
     * possono non avere una riga corrispondente — restano comunque
     * selezionabili, senza prompt).
     */
    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        $folderUserIds = collect([$user->id, $request->user()->id])->unique()->values();

        $jobsByFilename = ImageJob::where(function ($query) use ($folderUserIds) {
                $query->whereIn('user_id', $folderUserIds)
                    ->orWhereIn('created_by_user_id', $folderUserIds);
            })
            ->get()
            ->keyBy(fn (ImageJob $job) => basename((string) $job->image_url));

        $images = collect(array_unique(array_values(self::FOLDERS)))
            ->crossJoin($folderUserIds)
            ->flatMap(function (array $pair) use ($jobsByFilename) {
                [$folder, $folderUserId] = $pair;

                return collect(Storage::disk('public')->files("{$folder}/{$folderUserId}"))
                    ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true))
                    ->map(function ($path) use ($jobsByFilename) {
                        $filename = basename($path);
                        $job = $jobsByFilename->get($filename);

                        return [
                            'filename' => $filename,
                            'url' => Storage::disk('public')->url($path),
                            'prompt' => $job?->prompt,
                            'model' => $job?->model,
                            'createdAt' => $job?->created_at?->toIso8601String(),
                            'sortKey' => Storage::disk('public')->lastModified($path),
                        ];
                    });
            })
            ->unique('filename')
            ->sortByDesc('sortKey')
            ->map(fn ($img) => collect($img)->except('sortKey')->all())
            ->values();

        return response()->json(['images' => $images]);
    }

    /**
     * Avvia la generazione di un'immagine AI per l'account $user: crea la
     * riga image_jobs (status=pending) e dispatcha il job che chiama il
     * servizio Python. Risponde subito con l'id del job; il frontend fa
     * polling su status() per sapere quando è pronta.
     */
    public function startJob(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        // $request->validate() lancia una ValidationException che, su rotte
        // fuori da api/* (vedi bootstrap/app.php shouldRenderJsonWhen),
        // verrebbe renderizzata come redirect HTML invece che JSON. Per
        // questo, come nel resto del controller, si valida a mano.
        $validator = Validator::make($request->all(), [
            'prompt' => ['required', 'string', 'max:2000'],
            'model' => ['required', 'string', 'in:'.implode(',', array_keys(self::FOLDERS))],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if ($user->imagesUsed()->count() >= $user->image_model_limit) {
            return response()->json(['message' => 'Limite giornaliero di immagini generate raggiunto.'], 422);
        }

        if (empty($user->settings?->openai_api_key)) {
            return response()->json(['message' => 'Nessuna chiave OpenAI collegata per questo account. Collegala in Impostazioni AI.'], 422);
        }

        $job = ImageJob::create([
            'user_id' => $user->id,
            'created_by_user_id' => $request->user()->id,
            'status' => 'pending',
            'prompt' => $data['prompt'],
            'model' => $data['model'],
        ]);

        GenerateImageJob::dispatch($job->id);

        return response()->json(['job_id' => $job->id]);
    }

    /**
     * Stato di un job di generazione (per il polling del frontend).
     */
    public function status(Request $request, User $user, ImageJob $job): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);
        abort_unless($job->user_id === $user->id, 403);

        if ($job->status !== 'completed') {
            return response()->json(['status' => $job->status]);
        }

        $folder = self::folderForModel($job->model);

        return response()->json([
            'status' => 'completed',
            'filename' => $job->image_url,
            'url' => Storage::disk('public')->url("{$folder}/{$user->id}/{$job->image_url}"),
        ]);
    }

    /**
     * Elimina un'immagine dall'archivio visibile a $request->user() nel
     * contesto del post dell'account $user: cerca il file in qualunque
     * cartella provider di $user o dell'attore (stessa coppia di id usata da
     * index()) e lo cancella ovunque lo trovi — se l'immagine è una copia
     * duplicata presente in entrambe le cartelle, vengono rimosse entrambe,
     * dato che l'azione avviene nella stessa vista unificata. Rimuove anche
     * la riga image_jobs corrispondente, se presente (le orfane non ne hanno
     * una).
     */
    public function destroy(Request $request, User $user, string $filename): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);
        abort_if(str_contains($filename, '/') || str_contains($filename, '..'), 422);

        $folderUserIds = collect([$user->id, $request->user()->id])->unique()->values();

        $deleted = false;
        foreach (array_unique(array_values(self::FOLDERS)) as $folder) {
            foreach ($folderUserIds as $folderUserId) {
                $path = "{$folder}/{$folderUserId}/{$filename}";
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $deleted = true;
                }
            }
        }
        abort_unless($deleted, 404);

        ImageJob::whereIn('user_id', $folderUserIds)->get()
            ->filter(fn (ImageJob $job) => basename((string) $job->image_url) === $filename)
            ->each(fn (ImageJob $job) => $job->delete());

        return response()->json(['deleted' => true]);
    }
}
