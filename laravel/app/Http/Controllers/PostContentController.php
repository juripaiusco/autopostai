<?php

namespace App\Http\Controllers;

use App\Actions\Posts\GeneratePostTextPreview;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostContentController extends Controller
{
    /**
     * Genera il testo del post ("Genera anteprima"): chiamata sincrona al
     * servizio Python (nessun job/queue, coerente con v1 e con il worker
     * `queue` tenuto spento in dev). Non tocca il modello Post: la preview
     * avviene prima del salvataggio, come la generazione immagine.
     *
     * Passa da GeneratePostTextPreview (come il tool MCP): controllo del
     * limite mensile e TokenLog del consumo. Il token_log_id torna al form,
     * che lo rimanda al salvataggio per collegare il consumo al post.
     */
    public function generateText(Request $request, User $user, GeneratePostTextPreview $preview): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        $validator = Validator::make($request->all(), [
            'ai_prompt_post' => ['required_without:source_text', 'nullable', 'string'],
            // "Genera versione social": l'articolo da riscrivere per i social.
            'source_text' => ['nullable', 'string', 'max:60000'],
            'channel' => ['nullable', 'string', 'in:'.implode(',', array_keys(User::CHANNELS))],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:'.implode(',', array_keys(User::CHANNELS))],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        try {
            $result = $preview->handle(
                $user,
                (string) ($data['ai_prompt_post'] ?? ''),
                $data['channel'] ?? null,
                $data['channels'] ?? [],
                $data['source_text'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'content' => $result['content'],
            // Con WordPress/Newsletter l'AI scrive Markdown: l'editor ricco lo riceve in HTML.
            'html' => empty($data['source_text']) && in_array($data['channel'] ?? null, ['wordpress', 'newsletter'], true)
                ? Post::markdownToHtml($result['content'])
                : null,
            'token_log_id' => $result['token_log_id'],
        ]);
    }
}
