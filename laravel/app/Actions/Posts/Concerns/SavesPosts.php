<?php

namespace App\Actions\Posts\Concerns;

use App\Jobs\GeneratePostContentJob;
use App\Models\Post;
use App\Models\TokenLog;
use App\Services\PostHtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Pezzi comuni a CreatePost e UpdatePost.
 */
trait SavesPosts
{
    /**
     * Immagini del post: contenuto verificato (image) e tipo limitato ai
     * formati che i canali accettano. Niente SVG (può contenere script).
     */
    public const IMAGE_RULES = ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'];

    /**
     * Salva i file caricati in posts/{post_id}/ con filename univoco
     * (pattern v1: timestamp-random-nomeoriginale.ext), ritorna solo i
     * filename nudi da accodare a Post::img (nessun path in DB, la cartella
     * è per convenzione posts/{id}/).
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    private function storeImages(int $postId, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            $safeName = Str::of(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
                ->trim('_')
                ->value();
            // Estensione dal tipo reale del contenuto, non dal nome scelto dal
            // client: un'immagine valida chiamata "x.php" finiva salvata come
            // .php sul disco pubblico.
            $filename = date('YmdHis').'-'.Str::random(13).'-'.($safeName ?: 'img').'.'.$file->extension();
            Storage::disk('public')->putFileAs("posts/{$postId}", $file, $filename);
            $stored[] = $filename;
        }

        return $stored;
    }

    /**
     * Data di pubblicazione. Senza data il post resta una bozza: il worker
     * seleziona solo published_at <= adesso, quindi non esce finché non ne
     * riceve una. "Pubblica subito" (action=publish_now) la imposta qui,
     * lato server, senza dipendere dall'orologio del client.
     */
    private function publishedAt(array $data): mixed
    {
        return ($data['action'] ?? null) === 'publish_now'
            ? now()
            : ($data['published_at'] ?? null);
    }

    /**
     * Post programmato salvato senza anteprima: il testo lo genera un job in
     * coda, così si può rivedere prima dell'uscita. Senza coda o se il job
     * fallisce, lo genera comunque il worker alla pubblicazione.
     */
    private function queueTextGeneration(Post $post): bool
    {
        if (!$post->needsGeneratedText()) {
            return false;
        }

        GeneratePostContentJob::dispatch($post->id);

        return true;
    }

    /**
     * Regole del testo del post: formato (vedi Post::CONTENT_FORMATS) e
     * testo social personalizzato.
     *
     * @return array<string, mixed>
     */
    private static function contentRules(): array
    {
        return [
            'ai_content' => ['nullable', 'string'],
            'ai_content_format' => ['nullable', 'string', Rule::in(Post::CONTENT_FORMATS)],
            'ai_content_social' => ['nullable', 'string'],
        ];
    }

    /**
     * Colonne del testo da salvare. Formato e testo social assenti = quelli
     * attuali del post ($current, null in creazione = markdown e nessun
     * testo social). L'HTML passa sempre dal sanitizer; un testo vuoto torna
     * markdown, perché a riempirlo sarà l'AI (job o worker), che scrive
     * Markdown.
     *
     * @return array{ai_content: ?string, ai_content_format: string, ai_content_social: ?string}
     */
    private function contentFields(array $data, ?Post $current = null): array
    {
        $content = $data['ai_content'] ?? null;
        $format = $data['ai_content_format'] ?? $current?->ai_content_format ?? Post::FORMAT_MARKDOWN;

        if (trim((string) $content) === '') {
            $format = Post::FORMAT_MARKDOWN;
        } elseif ($format === Post::FORMAT_HTML) {
            $content = app(PostHtmlSanitizer::class)->clean($content);
        }

        $social = array_key_exists('ai_content_social', $data) ? $data['ai_content_social'] : $current?->ai_content_social;

        return [
            'ai_content' => $content,
            'ai_content_format' => $format,
            'ai_content_social' => trim((string) $social) === '' ? null : $social,
        ];
    }

    /**
     * Regole del campo che collega al post i token delle anteprime
     * ("Genera anteprima" / generate_post_text): ogni rigenerazione è un log.
     *
     * @return array<string, mixed>
     */
    private static function previewTokenRules(): array
    {
        return [
            'preview_token_log_ids' => ['nullable', 'array', 'max:50'],
            'preview_token_log_ids.*' => ['integer'],
        ];
    }

    /**
     * I log delle anteprime nascono senza post (type "preview",
     * reference_id 0): al salvataggio diventano consumo del post. Solo log
     * dello stesso account e non ancora collegati, così un id altrui o già
     * usato non sposta nulla. Le anteprime mai salvate restano "preview" e
     * contano comunque nel mese.
     *
     * @param  array<int, int>  $ids
     */
    private function attachPreviewTokens(Post $post, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        TokenLog::whereIn('id', $ids)
            ->where('user_id', $post->user_id)
            ->where('type', 'preview')
            ->where('reference_id', 0)
            ->update(['type' => 'post', 'reference_id' => $post->id]);
    }
}
