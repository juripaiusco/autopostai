<?php

namespace App\Http\Controllers;

use App\Actions\Images\StartImageGeneration;
use App\Models\ImageJob;
use App\Models\User;
use App\Services\ImageArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImageArchiveController extends Controller
{
    public function __construct(private ImageArchive $archive) {}

    /**
     * Archivio immagini AI visibile a $request->user() nel contesto del post
     * dell'account $user (vedi ImageArchive::images).
     */
    public function index(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        return response()->json(['images' => $this->archive->images($user, $request->user())]);
    }

    /**
     * Avvia la generazione di un'immagine AI per l'account $user (vedi
     * StartImageGeneration). Risponde subito con l'id del job; il frontend
     * fa polling su status() per sapere quando è pronta.
     */
    public function startJob(Request $request, User $user, StartImageGeneration $startGeneration): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        // $request->validate() lancia una ValidationException che, su rotte
        // fuori da api/* (vedi bootstrap/app.php shouldRenderJsonWhen),
        // verrebbe renderizzata come redirect HTML invece che JSON. Per
        // questo, come nel resto del controller, si valida a mano.
        $validator = Validator::make($request->all(), StartImageGeneration::rules());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        try {
            $job = $startGeneration->handle($user, $request->user(), $data['prompt'], $data['model']);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

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

        $folder = ImageArchive::folderForModel($job->model);

        return response()->json([
            'status' => 'completed',
            'filename' => $job->image_url,
            'url' => Storage::disk('public')->url("{$folder}/{$user->id}/{$job->image_url}"),
        ]);
    }

    /**
     * Elimina un'immagine dall'archivio visibile a $request->user() nel
     * contesto del post dell'account $user (vedi ImageArchive::delete).
     */
    public function destroy(Request $request, User $user, string $filename): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);
        abort_if(str_contains($filename, '/') || str_contains($filename, '..'), 422);

        abort_unless($this->archive->delete($user, $request->user(), $filename), 404);

        return response()->json(['deleted' => true]);
    }
}
