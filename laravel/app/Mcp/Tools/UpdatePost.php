<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\UpdatePost as UpdatePostAction;
use App\Mcp\Tools\Concerns\WritesPosts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_post')]
#[Title('Modifica post')]
#[Description('Modifica una bozza o un post programmato non ancora uscito. Aggiornamento parziale: i campi omessi restano come sono. published_at: null lo riporta a bozza, una data futura lo (ri)programma. Per i post già pubblicati usa update_published_post.')]
#[IsIdempotent]
class UpdatePost extends Tool
{
    use WritesPosts;

    protected string $ability = 'write';

    public function handle(Request $request, UpdatePostAction $updatePost): ResponseFactory
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->channelsRules(false),
            'ai_prompt_post' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'ai_content' => ['sometimes', 'nullable', 'string', 'max:60000'],
            'ai_prompt_comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'published_at' => ['sometimes', ...$this->publishedAtRules()],
            'archive_images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'archive_images.*' => ['string'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
            'ai_looks_at_image' => ['sometimes', 'boolean'],
        ], $this->publishedAtMessages());

        $post = $this->findPost($data['post_id'], 'update');
        $this->guard(fn () => $updatePost->ensureEditable($post));
        $account = $post->user;

        $channels = array_key_exists('channels', $data)
            ? $this->toFormChannels($data['channels'], $account)
            : $this->currentFormChannels($post);

        $existing = $post->img ?? [];
        $unknown = array_diff($data['remove_images'] ?? [], $existing);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['remove_images' => 'Immagini non presenti nel post: '.implode(', ', $unknown).' (vedi get_post).']);
        }
        $keep = array_values(array_diff($existing, $data['remove_images'] ?? []));
        $files = $this->archiveFiles($account, $data['archive_images'] ?? []);
        $this->ensureInstagramHasImages($channels, count($keep) + count($files));

        $field = fn (string $key, mixed $current) => array_key_exists($key, $data) ? $data[$key] : $current;

        $textQueued = $this->guard(fn () => $updatePost->handle($post, [
            'title' => $field('title', $post->title) ?? '',
            'channels' => $channels,
            'ai_prompt_post' => $field('ai_prompt_post', $post->ai_prompt_post),
            'ai_content' => $field('ai_content', $post->ai_content),
            'ai_prompt_comment' => $field('ai_prompt_comment', $post->ai_prompt_comment),
            'img_ai_check_on' => $field('ai_looks_at_image', $post->img_ai_check_on == '1'),
            'published_at' => $field('published_at', $post->published_at),
            'action' => 'save',
        ], $files, $keep));

        return Response::structured($this->postSummary($post, $textQueued));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
            'title' => $schema->string(),
            'channels' => $this->channelsSchema($schema)->description('Se presente sostituisce TUTTI i canali del post (stesso formato di create_post).'),
            'ai_prompt_post' => $schema->string(),
            'ai_content' => $schema->string()->description('Testo definitivo. Svuotalo (stringa vuota) per farlo rigenerare dall\'AI dal prompt.'),
            'ai_prompt_comment' => $schema->string(),
            'published_at' => $schema->string()->format('date-time')->nullable()->description('Data futura (ISO 8601), oppure null per tornare a bozza.'),
            'archive_images' => $schema->array()->items($schema->string())->max(self::MAX_IMAGES)->description('Immagini d\'archivio da aggiungere.'),
            'remove_images' => $schema->array()->items($schema->string())->description('Filename (da get_post) da togliere dal post.'),
            'ai_looks_at_image' => $schema->boolean(),
        ];
    }
}
