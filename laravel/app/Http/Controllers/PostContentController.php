<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PostContentController extends Controller
{
    /**
     * Genera il testo del post ("Genera anteprima"): chiamata sincrona al
     * servizio Python (nessun job/queue, coerente con v1 e con il worker
     * `queue` tenuto spento in dev). Non tocca il modello Post: la preview
     * avviene prima del salvataggio, come la generazione immagine.
     */
    public function generateText(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        $validator = Validator::make($request->all(), [
            'ai_prompt_post' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'in:'.implode(',', array_keys(User::CHANNELS))],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if (empty($user->settings?->openai_api_key)) {
            return response()->json(['message' => 'Nessuna chiave OpenAI collegata per questo account. Collegala in Impostazioni AI.'], 422);
        }

        $response = Http::timeout(60)->post(config('services.python.url').'/generate-text', [
            'ai_prompt_post' => $data['ai_prompt_post'],
            'channel' => $data['channel'] ?? null,
            'api_key' => $user->settings->openai_api_key,
            'ai_personality' => $user->settings->ai_personality,
            'ai_prompt_prefix' => $user->settings->ai_prompt_prefix,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => $response->json('detail') ?? 'Errore generazione testo'], 422);
        }

        return response()->json(['content' => $response->json('content')]);
    }
}
