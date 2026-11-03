<?php

namespace App\Actions\Posts;

use App\Actions\Posts\Concerns\SavesPosts;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Modifica di un post non ancora uscito (titolo, contenuti, canali, data,
 * immagini): form Post e tool MCP. L'autorizzazione (PostPolicy::update) la
 * fa il chiamante.
 */
class UpdatePost
{
    use SavesPosts;

    public function __construct(private PostChannels $channels) {}

    /**
     * Senza keep_images: è il campo JSON del form, il controller lo decodifica.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'ai_prompt_post' => ['nullable', 'string'],
            'ai_prompt_comment' => ['nullable', 'string'],
            'ai_content' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => self::IMAGE_RULES,
            'img_ai_check_on' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'action' => ['nullable', 'string', Rule::in(['save', 'publish_now'])],
        ];
    }

    /**
     * Separato da handle() perché il form lo controlla prima di validare
     * (un post già uscito risponde 403 qualunque cosa arrivi).
     */
    public function ensureEditable(Post $post): void
    {
        abort_if(!$post->isEditable(), 403, 'Un post pubblicato (anche solo su alcuni canali) non può essere modificato.');
    }

    /**
     * $data già validato con rules(). $keepImages: filename esistenti da
     * tenere (null = tutti). Ritorna se il testo è stato messo in coda per
     * la generazione AI.
     *
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, string>|null  $keepImages
     */
    public function handle(Post $post, array $data, array $images = [], ?array $keepImages = null): bool
    {
        $this->ensureEditable($post);

        $channels = $this->channels->preserveWorkerKeys(
            $this->channels->build($data['channels'], $post->user),
            $post->fresh()->channels ?? [],
        );
        [$commentsEnabled, $autoReplyEnabled] = $this->channels->commentsAggregate($channels);

        $existing = $post->img ?? [];
        $kept = array_values(array_filter($keepImages ?? $existing, fn ($f) => in_array($f, $existing, true)));

        foreach (array_diff($existing, $kept) as $removed) {
            Storage::disk('public')->delete("posts/{$post->id}/{$removed}");
        }

        $img = array_merge($kept, $images !== [] ? $this->storeImages($post->id, $images) : []);

        $imgAiCheckOn = array_key_exists('img_ai_check_on', $data)
            ? (!empty($data['img_ai_check_on']) ? '1' : '0')
            : $post->img_ai_check_on;

        $post->update([
            'title' => $data['title'] ?? '',
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            'ai_content' => $data['ai_content'] ?? null,
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img' => $img,
            'img_ai_check_on' => $imgAiCheckOn,
            'comments_enabled' => $commentsEnabled ? '1' : '0',
            'auto_reply_enabled' => $autoReplyEnabled ? '1' : '0',
            'channels' => $channels,
            'published_at' => $this->publishedAt($data),
        ]);

        return $this->queueTextGeneration($post);
    }
}
