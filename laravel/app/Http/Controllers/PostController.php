<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Lista post: l'amministratore vede tutti i post, il manager (child_on=1)
     * vede solo i post dei propri sotto-utenti, l'utente vede solo i propri.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;
        $isManager = $me->child_on == 1;
        $showAuthor = $isAdmin || $isManager;

        $scope = match (true) {
            $isAdmin => fn () => Post::query(),
            $isManager => fn () => Post::whereIn('user_id', $me->children()->pluck('id')),
            default => fn () => Post::where('user_id', $me->id),
        };

        $isPublishedFilter = fn ($q) => $q->where('published', '1');
        $isScheduledFilter = fn ($q) => $q->where('published', '0')->where('published_at', '>', now());
        $isDraftFilter = fn ($q) => $q->where('published', '0')
            ->where(fn ($q2) => $q2->whereNull('published_at')->orWhere('published_at', '<=', now()));

        $counts = [
            'tutti' => $scope()->count(),
            'pubblicati' => $isPublishedFilter($scope())->count(),
            'programmati' => $isScheduledFilter($scope())->count(),
            'bozze' => $isDraftFilter($scope())->count(),
        ];

        $filter = $request->string('filter')->toString() ?: 'tutti';
        $search = trim($request->string('search')->toString());
        $sortKey = $request->string('sort')->toString() ?: 'publishedAt';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'title' => 'title',
            'publishedAt' => 'published_at',
            'comments' => 'comments_count',
        ];
        $sortColumn = $sortable[$sortKey] ?? 'published_at';

        $query = $scope();

        if ($filter === 'pubblicati') {
            $isPublishedFilter($query);
        } elseif ($filter === 'programmati') {
            $isScheduledFilter($query);
        } elseif ($filter === 'bozze') {
            $isDraftFilter($query);
        }

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $posts = $query
            ->with('user:id,name')
            ->withCount('comments')
            ->orderBy($sortColumn, $dir)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'author' => $p->user->name,
                'channels' => collect($p->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'status' => $this->status($p),
                'publishedAt' => $p->published_at?->toIso8601String(),
                'comments' => $p->comments_count,
            ]);

        return Inertia::render('Posts/List', [
            'posts' => $posts,
            'showAuthor' => $showAuthor,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
                'sort' => $sortKey,
                'dir' => $dir,
            ],
            'counts' => $counts,
        ]);
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;
        $isManager = $me->child_on == 1;

        $allowed = $isAdmin
            || ($isManager && $post->user->parent_id === $me->id)
            || $post->user_id === $me->id;

        abort_unless($allowed, 403);

        $post->delete();

        return back();
    }

    private function status(Post $post): string
    {
        if ($post->published == '1') {
            return 'published';
        }

        if ($post->published_at !== null && $post->published_at->isFuture()) {
            return 'scheduled';
        }

        return 'draft';
    }
}
