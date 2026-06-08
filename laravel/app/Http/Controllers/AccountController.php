<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Lista utenti/account: l'amministratore (parent_id null) vede tutti,
     * chi ha un parent vede solo i propri sotto-utenti.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;

        $scope = fn () => $isAdmin ? User::query() : User::where('parent_id', $me->id);

        $isManagerFilter = fn ($q) => $q->where('child_on', 1);
        $isUserFilter = fn ($q) => $q->where(fn ($q2) => $q2->whereNull('child_on')->orWhere('child_on', '!=', 1));

        $counts = [
            'tutti' => $scope()->count(),
            'utenti' => $isUserFilter($scope())->count(),
            'manager' => $isManagerFilter($scope())->count(),
        ];

        $filter = $request->string('filter')->toString() ?: 'tutti';
        $search = trim($request->string('search')->toString());
        $sortKey = $request->string('sort')->toString() ?: 'name';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $sortable = [
            'name' => 'name',
            'post' => 'posts_count',
            'reply' => 'comments_count',
            'immagini' => 'images_used_count',
            'tokenUsed' => 'tokens_used_sum',
        ];
        $sortColumn = $sortable[$sortKey] ?? 'name';

        $query = $scope();

        if ($filter === 'utenti') {
            $isUserFilter($query);
        } elseif ($filter === 'manager') {
            $isManagerFilter($query);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $users = $query
            ->withCount(['posts', 'comments', 'imagesUsed'])
            ->withSum('tokensUsed', 'tokens_used')
            ->orderBy($sortColumn, $dir)
            ->where('parent_id', '!=', null) // Escludi amministratori
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->parent_id === null ? 'amministratore' : ($u->child_on == 1 ? 'manager' : 'utente'),
                'channels' => collect($u->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'post' => $u->posts_count,
                'reply' => $u->comments_count,
                'immagini' => $u->images_used_count,
                'tokenUsed' => (int) ($u->tokens_used_sum ?? 0),
                'tokenTotal' => $u->tokens_limit,
            ]);

        return Inertia::render('Account', [
            'users' => $users,
            'isAdmin' => $isAdmin,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
                'sort' => $sortKey,
                'dir' => $dir,
            ],
            'counts' => $counts,
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;

        abort_unless($isAdmin || $user->parent_id === $me->id, 403);

        $user->delete();

        return back();
    }
}
