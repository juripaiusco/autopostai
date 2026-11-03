<?php

namespace App\Actions\Posts;

use App\Models\Post;

/**
 * "Riprova": azzera tentativi ed errore dei canali falliti, così il worker
 * li ritenta al giro successivo. Serve soprattutto ai post parziali, che non
 * si possono più risalvare dal form.
 */
class RetryPost
{
    /**
     * Update per percorso JSON (JSON_SET), non dell'intera colonna: una
     * riscrittura da una copia vecchia potrebbe cancellare l'id che il worker
     * ha appena scritto su un altro canale.
     */
    public function handle(Post $post): void
    {
        $reset = [];
        foreach (array_keys($post->channelErrors()) as $channel) {
            $reset["channels->{$channel}->attempts"] = 0;
            $reset["channels->{$channel}->error"] = null;
        }
        abort_if($reset === [], 422, 'Nessun canale in errore da riprovare.');

        Post::whereKey($post->id)->update($reset);
    }
}
