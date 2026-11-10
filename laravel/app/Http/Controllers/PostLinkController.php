<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->get(['id', 'title', 'published_at', 'channels']);

        $targets = $posts
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title ?: "Post #{$post->id}",
                'published_at' => $post->published_at?->toIso8601String(),
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
}
