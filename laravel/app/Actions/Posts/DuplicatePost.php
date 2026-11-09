<?php

namespace App\Actions\Posts;

use App\Jobs\GeneratePostContentJob;
use App\Models\ContactTag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copia di un post (qualunque stato, anche già pubblicato) come nuova bozza
 * dello stesso account: testi, immagini e canali, senza data di invio. Come
 * in v1, la data si sceglie dopo dal form. Lista post e tool MCP; variante
 * nextInSeries() per "Salva e aggiungi".
 */
class DuplicatePost
{
    public function __construct(private PostChannels $channels) {}

    public function handle(User $actor, Post $post): Post
    {
        return $this->copy($actor, $post, [
            'title' => Str::limit((string) $post->title, 255 - 8, '').' (copia)',
            'ai_content' => $post->ai_content,
            // Senza data resta una bozza: il worker non la prende finché
            // l'utente non sceglie quando inviarla.
            'published_at' => null,
        ]);
    }

    /**
     * "Salva e aggiungi" dal form post (come v1): copia programmata 1 minuto
     * dopo l'originale, stesso titolo, testo AI azzerato perché venga
     * riscritto. Serve a creare una serie di post sullo stesso tema su più
     * canali con orari sfalsati. Originale senza data → copia senza data.
     */
    public function nextInSeries(User $actor, Post $post): Post
    {
        $copy = $this->copy($actor, $post, [
            'title' => (string) $post->title,
            'ai_content' => null,
            'published_at' => $post->published_at?->copy()->addMinute(),
        ]);

        if ($copy->needsGeneratedText()) {
            GeneratePostContentJob::dispatch($copy->id);
        }

        return $copy;
    }

    /**
     * @param  array{title: string, ai_content: ?string, published_at: mixed}  $overrides
     */
    private function copy(User $actor, Post $post, array $overrides): Post
    {
        $account = $post->user;

        $channels = $this->channels->build($this->channelOptions($post), $account);
        [$commentsEnabled, $autoReplyEnabled] = $this->channels->commentsAggregate($channels);

        $copy = Post::create([
            'user_id' => $post->user_id,
            'created_by_user_id' => $actor->id,
            'title' => $overrides['title'],
            'ai_prompt_post' => $post->ai_prompt_post,
            'ai_content' => $overrides['ai_content'],
            'ai_prompt_comment' => $post->ai_prompt_comment,
            'img' => null,
            'img_ai_check_on' => $post->img_ai_check_on == '1' ? '1' : '0',
            'comments_enabled' => $commentsEnabled ? '1' : '0',
            'auto_reply_enabled' => $autoReplyEnabled ? '1' : '0',
            'channels' => $channels,
            'published_at' => $overrides['published_at'],
            'published' => '0',
        ]);

        $images = $this->copyImages($post, $copy);
        if ($images !== []) {
            $copy->update(['img' => $images]);
        }

        return $copy;
    }

    /**
     * Canali accesi dell'originale come payload del form: build() tiene solo
     * i campi di configurazione, quindi id/url/stats/errori/testo per canale
     * scritti dal worker non passano alla copia. Un tag newsletter cancellato
     * nel frattempo diventa "tutti i contatti" invece di un 422.
     */
    private function channelOptions(Post $post): array
    {
        $selected = collect($post->channels ?? [])
            ->filter(fn ($c) => is_array($c) && !empty($c['on']))
            ->all();

        $tagId = $selected['newsletter']['tag_id'] ?? null;
        if ($tagId !== null && !ContactTag::where('id', (int) $tagId)->where('user_id', $post->user_id)->exists()) {
            $selected['newsletter']['tag_id'] = null;
        }

        return $selected;
    }

    /**
     * Copia in posts/{copia}/ le immagini del post con lo stesso filename;
     * quelle sparite dal disco si saltano.
     *
     * @return array<int, string>
     */
    private function copyImages(Post $from, Post $to): array
    {
        $disk = Storage::disk('public');

        return collect($from->img ?? [])
            ->filter(fn ($filename) => is_string($filename) && $disk->exists("posts/{$from->id}/{$filename}"))
            ->each(fn ($filename) => $disk->copy("posts/{$from->id}/{$filename}", "posts/{$to->id}/{$filename}"))
            ->values()
            ->all();
    }
}
