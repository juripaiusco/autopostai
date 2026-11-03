<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PostTextGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PostContentController extends Controller
{
    /**
     * Genera il testo del post ("Genera anteprima"): chiamata sincrona al
     * servizio Python (nessun job/queue, coerente con v1 e con il worker
     * `queue` tenuto spento in dev). Non tocca il modello Post: la preview
     * avviene prima del salvataggio, come la generazione immagine.
     */
    public function generateText(Request $request, User $user, PostTextGenerator $generator): JsonResponse
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

        try {
            $result = $generator->generate($user->settings, $data['ai_prompt_post'], $data['channel'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['content' => $result['content']]);
    }
}
