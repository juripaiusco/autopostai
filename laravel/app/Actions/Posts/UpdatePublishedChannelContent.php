<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Services\PostHtmlSanitizer;
use Illuminate\Validation\Rule;

/**
 * "Modifica post" su un canale già pubblicato: salva il testo di quel canale
 * (gli altri restano su ai_content) e mette il post in coda per
 * posts_update (updated = 2). Update per percorso JSON come RetryPost.
 *
 * Formato in channels.<c>.content_format: WordPress html (editor ricco della
 * pagina post, sanificato) o markdown (MCP, testi precedenti); Facebook
 * sempre testo semplice.
 */
class UpdatePublishedChannelContent
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:60000'],
            'format' => ['nullable', Rule::in([Post::FORMAT_MARKDOWN, Post::FORMAT_HTML])],
        ];
    }

    /**
     * Separato da handle() perché il form lo controlla prima di validare il testo.
     */
    public function ensureEditable(Post $post, string $channel): void
    {
        $entry = $post->channels[$channel] ?? [];
        abort_unless(in_array($channel, Post::EDITABLE_AFTER_PUBLISH, true), 422, 'Questo canale non permette di modificare un post pubblicato.');
        abort_unless(!empty($entry['on']) && !empty($entry['id']), 422, 'Il post non è ancora pubblicato su questo canale.');
    }

    public function handle(Post $post, string $channel, string $content, ?string $format = null): void
    {
        $this->ensureEditable($post, $channel);

        $format = $channel === 'wordpress' ? ($format ?? Post::FORMAT_MARKDOWN) : Post::FORMAT_TEXT;
        if ($format === Post::FORMAT_HTML) {
            $content = app(PostHtmlSanitizer::class)->clean($content);
        }

        Post::whereKey($post->id)->update([
            "channels->{$channel}->content" => $content,
            "channels->{$channel}->content_format" => $format,
            "channels->{$channel}->update_pending" => true,
            "channels->{$channel}->update_error" => null,
            'updated' => '2',
        ]);
    }
}
