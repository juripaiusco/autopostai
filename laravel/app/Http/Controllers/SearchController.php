<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Ricerca globale header: post (scope Post::visibleTo, come la lista Post)
     * + sotto-utenti (scope User::filterableUsers, come il selettore Sidebar),
     * quest'ultimi solo se l'utente puo' vederne (UserPolicy::viewAny).
     */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $q = trim($request->string('q')->toString());

        if ($q === '') {
            return response()->json(['posts' => [], 'accounts' => []]);
        }

        $activeUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        $posts = Post::query()
            ->visibleTo($me, $activeUserId)
            ->where('title', 'like', "%{$q}%")
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title'])
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title ?: '(senza titolo)',
                'status' => $p->status(),
                'url' => route('posts.show', $p),
            ]);

        $accounts = [];
        if ($me->can('viewAny', User::class)) {
            $needle = mb_strtolower($q);
            $accounts = $me->filterableUsers()
                ->filter(fn (array $u) => str_contains(mb_strtolower($u['name']), $needle) || str_contains(mb_strtolower($u['email']), $needle))
                ->take(5)
                ->map(fn (array $u) => [
                    'id' => $u['id'],
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'url' => route('account.edit', $u['id']),
                ])
                ->values();
        }

        return response()->json(['posts' => $posts, 'accounts' => $accounts]);
    }
}
