<?php

namespace App\Actions\Posts;

use App\Models\Post;

/**
 * "Modifica post" su un canale già pubblicato: salva il testo di quel canale
 * (gli altri restano su ai_content) e mette il post in coda per
 * posts_update (updated = 2). Update per percorso JSON come RetryPost.
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

    public function handle(Post $post, string $channel, string $content): void
    {
        $this->ensureEditable($post, $channel);

        Post::whereKey($post->id)->update([
            "channels->{$channel}->content" => $content,
            "channels->{$channel}->update_pending" => true,
            "channels->{$channel}->update_error" => null,
            'updated' => '2',
        ]);
    }
}
