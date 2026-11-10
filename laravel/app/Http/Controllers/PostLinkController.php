<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostLinkController extends Controller
{
    /** Post restituiti al massimo per ricerca. */
    private const LIMIT = 20;

    /**
     * Post già pubblicati dell'account, con l'indirizzo per canale: il
     * dialog "Un tuo post" degli editor li usa per inserire lo shortcode
     * `[WordPress url id="123"]` senza scriverlo a mano. Il worker risolve lo
     * shortcode solo su post dello stesso account (posts_send::_url_resolver),
     * qui si propongono solo quelli.
     */
    public function targets(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        $q = trim((string) $request->query('q', ''));

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->whereNotNull('published_at')
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%');
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
            }))
            ->orderByDesc('published_at')
            ->limit(200)
            ->get(['id', 'title', 'published_at', 'channels', 'img', 'ai_content', 'ai_content_format']);

        $targets = $posts
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title ?: "Post #{$post->id}",
                'published_at' => $post->published_at?->toIso8601String(),
                // Per riconoscere il post a colpo d'occhio (come in Lista post).
                'cover' => $post->coverUrl(),
                'thumb' => $post->thumbGradient(),
                'excerpt' => $this->excerpt($post),
                'channels' => collect($post->channels ?? [])
                    ->filter(fn ($c, $key) => isset(User::CHANNELS[$key]) && !empty($c['on']) && !empty($c['url']))
                    ->map(fn ($c, $key) => ['key' => $key, 'label' => User::CHANNELS[$key]['name'], 'url' => $c['url']])
                    ->values()
                    ->all(),
            ])
            ->filter(fn ($target) => $target['channels'] !== [])
            ->take(self::LIMIT)
            ->values();

        return response()->json(['posts' => $targets]);
    }

    /**
     * Inizio del testo in chiaro (senza tag né titolo dell'articolo).
     */
    private function excerpt(Post $post): ?string
    {
        $content = (string) $post->ai_content;
        if (trim($content) === '') {
            return null;
        }

        $html = $post->ai_content_format === Post::FORMAT_HTML ? $content : Post::markdownToHtml($content);
        $html = preg_replace('#<h1\b[^>]*>.*?</h1>#is', '', $html, 1);
        // Spazio tra i blocchi (paragrafi, elenchi), non dentro il grassetto.
        $html = preg_replace('#</(p|h[1-6]|li|blockquote|div)>|<br\s*/?>#i', ' $0', $html);
        $text = (string) Str::of(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5))->squish();

        return $text === '' ? null : Str::limit($text, 110);
    }
}
