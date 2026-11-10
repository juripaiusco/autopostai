<?php

namespace App\Actions\Posts;

use App\Actions\Posts\Concerns\SavesPosts;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Nuovo post (bozza, programmato o da pubblicare): form Post e tool MCP.
 */
class CreatePost
{
    use SavesPosts;

    public function __construct(private PostChannels $channels) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'channels' => ['required', 'array', 'min:1'],
            'ai_prompt_post' => ['nullable', 'string'],
            'ai_prompt_comment' => ['nullable', 'string'],
            ...self::contentRules(),
            'images' => ['nullable', 'array'],
            'images.*' => self::IMAGE_RULES,
            'img_ai_check_on' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'action' => ['required', 'string', Rule::in(['save', 'save_and_add', 'publish_now'])],
            ...self::previewTokenRules(),
        ];
    }

    /**
     * $data già validato con rules(). Ritorna il post e se il testo è stato
     * messo in coda per la generazione AI.
     *
     * @param  array<int, UploadedFile>  $images
     * @return array{0: Post, 1: bool}
     */
    public function handle(User $actor, array $data, array $images = []): array
    {
        $target = $this->targetAccount($actor, $data['user_id'] ?? null);

        $channels = $this->channels->build($data['channels'], $target);
        [$commentsEnabled, $autoReplyEnabled] = $this->channels->commentsAggregate($channels);

        $post = Post::create([
            'user_id' => $target->id,
            'created_by_user_id' => $actor->id,
            'title' => $data['title'] ?? '',
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            ...$this->contentFields($data),
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img' => null,
            'img_ai_check_on' => !empty($data['img_ai_check_on']) ? '1' : '0',
            'comments_enabled' => $commentsEnabled ? '1' : '0',
            'auto_reply_enabled' => $autoReplyEnabled ? '1' : '0',
            'channels' => $channels,
            'published_at' => $this->publishedAt($data),
            'published' => '0',
        ]);

        if ($images !== []) {
            $post->update(['img' => $this->storeImages($post->id, $images)]);
        }

        $this->attachPreviewTokens($post, $data['preview_token_log_ids'] ?? []);

        return [$post, $this->queueTextGeneration($post)];
    }

    /**
     * Account per cui si scrive il post: l'amministratore sceglie un utente
     * semplice, il manager uno dei propri sotto-utenti, l'utente scrive
     * sempre per sé (user_id ignorato).
     */
    private function targetAccount(User $actor, mixed $userId): User
    {
        if (!$actor->isAdmin() && !$actor->isManager()) {
            return $actor;
        }

        $target = match (true) {
            $actor->isAdmin() => User::whereNotNull('parent_id')->whereNull('child_on')->whereKey($userId)->first(),
            default => User::where('parent_id', $actor->id)->whereKey($userId)->first(),
        };
        abort_if(!$target, 422, 'Account non valido.');

        return $target;
    }
}
