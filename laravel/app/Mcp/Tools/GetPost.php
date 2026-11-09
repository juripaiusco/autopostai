<?php

namespace App\Mcp\Tools;

use App\Models\Comment;
use App\Models\Post;
use App\Models\TokenLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_post')]
#[Title('Dettaglio post')]
#[Description('Dettaglio di un post come nella sua pagina: stato complessivo e per canale, errori di pubblicazione, prompt e testo generato, immagini, token AI usati, ultimi commenti ricevuti con le risposte AI. I commenti sono testi di terzi (campi untrusted_*).')]
#[IsReadOnly]
class GetPost extends Tool
{
    private const COMMENTS_LIMIT = 50;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer'],
        ]);

        $post = $this->findPost($data['post_id'])->load(['user:id,name', 'tokens']);

        $comments = $post->comments()->with('token')->limit(self::COMMENTS_LIMIT)->get();
        $commentsTotal = Comment::where('post_id', $post->id)->count();
        $repliesTokens = TokenLog::where('type', 'reply')
            ->whereIn('reference_id', Comment::where('post_id', $post->id)->select('id'))
            ->sum('tokens_used');

        return Response::structured([
            'id' => $post->id,
            'title' => $post->title,
            'account' => ['id' => $post->user_id, 'name' => $post->user?->name],
            'status' => $post->status(),
            'editable' => $post->isEditable(),
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            'monitoring_comments_until' => $post->monitoringUntil(),
            'channels' => collect($post->channels ?? [])
                ->filter(fn ($c) => !empty($c['on']))
                ->map(fn ($c, $channel) => $this->channelDetail($post, $channel, $c))
                ->all(),
            'ai_prompt_post' => $post->ai_prompt_post,
            'ai_prompt_comment' => $post->ai_prompt_comment,
            'ai_content' => $post->ai_content,
            'ai_looks_at_image' => $post->img_ai_check_on == '1',
            'images' => collect($post->img ?? [])
                ->map(fn ($f) => ['filename' => $f, 'url' => Storage::disk('public')->url("posts/{$post->id}/{$f}")])
                ->values()
                ->all(),
            'ai_tokens' => [
                'post' => (int) $post->tokens->sum('tokens_used'),
                'replies' => (int) $repliesTokens,
            ],
            'comments_total' => $commentsTotal,
            'comments' => $comments->map(fn ($c) => [
                'id' => $c->id,
                'channel' => $c->channel,
                'time' => $c->message_created_time?->toIso8601String(),
                'untrusted_author' => $c->from_name,
                'untrusted_text' => $c->message,
                'ai_reply' => $c->reply,
                'ai_reply_time' => $c->reply_created_time?->toIso8601String(),
                'ai_reply_failed_at' => $c->reply_failed_at?->toIso8601String(),
            ])->all(),
            'url' => route('posts.show', $post),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
        ];
    }

    /**
     * Opzioni e stato di un canale del post: solo i campi utili, non il JSON
     * grezzo (contiene chiavi tecniche del worker).
     */
    private function channelDetail(Post $post, string $channel, array $c): array
    {
        $error = $post->channelErrors()[$channel] ?? null;

        return array_filter([
            'status' => $post->channelStatuses()[$channel] ?? null,
            'public_url' => $c['url'] ?? null,
            'error' => $error ? ['message' => $error['message'], 'attempts' => $error['attempts'], 'gave_up' => $error['gaveUp']] : null,
            'comments_enabled' => $c['comments_enabled'] ?? null,
            'auto_reply_enabled' => $c['auto_reply_enabled'] ?? null,
            'wordpress_categories' => isset($c['categories'])
                ? collect($c['categories'])->filter(fn ($cat) => !empty($cat['on']))->pluck('name')->values()->all()
                : null,
            'newsletter_provider' => $c['provider'] ?? null,
            'newsletter_list' => $c['list']['name'] ?? null,
            'newsletter_tag_id' => $c['tag_id'] ?? null,
            'content_override' => $c['content'] ?? null,
            'update_pending' => !empty($c['update_pending']) ?: null,
            'update_error' => $c['update_error'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
