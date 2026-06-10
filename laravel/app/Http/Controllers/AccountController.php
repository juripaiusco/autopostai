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
            'post' => 'post_logs_count',
            'reply' => 'reply_logs_count',
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
            ->withCount(['postLogs', 'replyLogs', 'imagesUsed'])
            ->withSum(['tokensUsed as tokens_used_sum'], 'tokens_used')
            ->orderBy($sortColumn, $dir)
            ->whereNotNull('parent_id')
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
                'post' => $u->post_logs_count,
                'reply' => $u->reply_logs_count,
                'immagini' => $u->images_used_count,
                'tokenUsed' => (int) ($u->tokens_used_sum ?? 0),
                'tokenTotal' => $u->tokens_limit,
                'imageTotal' => $u->image_model_limit,
            ]);

        return Inertia::render('Account/List', [
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

    public function create(Request $request): Response
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;

        abort_unless($isAdmin, 403);

        $managers = User::whereNull('parent_id')->orWhere('child_on', 1)
            ->where('id', '!=', $me->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);

        return Inertia::render('Account/Form', [
            'mode' => 'create',
            'account' => null,
            'managers' => $managers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        abort_unless($me->parent_id === null, 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => bcrypt($data['password']),
            'parent_id' => $me->id,
            'channels'  => $request->input('channels', []),
        ]);

        return redirect()->route('account');
    }

    public function edit(Request $request, User $user): Response
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;
        abort_unless($isAdmin || $user->parent_id === $me->id, 403);

        $managers = User::whereNull('parent_id')->orWhere('child_on', 1)
            ->where('id', '!=', $user->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);

        $account = [
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'password' => '',
            'channels' => $user->channels ?? [],
            'manager'  => $user->manager_id ?? '',
            'canSubusers' => (bool) ($user->child_on ?? false),
            'subusersLimit' => $user->child_max ?? '',
            'tokensMonth' => $user->tokens_limit ?? '',
            'imagesDay'  => $user->image_model_limit ?? '',
            'ai'         => $user->ai_profile ?? ['profile' => '', 'knows' => '', 'commentStyle' => ''],
            'openai'     => ['apiKey' => $user->openai_key ?? '', 'connected' => !empty($user->openai_key)],
            'meta'       => ['pageId' => $user->meta_page_id ?? '', 'connected' => !empty($user->meta_page_id)],
            'linkedin'   => ['clientId' => '', 'clientSecret' => '', 'pageId' => '', 'token' => '', 'connected' => false],
            'wordpress'  => ['url' => '', 'username' => '', 'password' => '', 'categoryId' => '', 'connected' => false],
            'newsletter' => [
                'mailchimp' => ['apiKey' => '', 'serverPrefix' => '', 'audienceId' => '', 'connected' => false],
                'brevo'     => ['apiKey' => '', 'listId' => '', 'sender' => '', 'connected' => false],
                'smtp'      => ['host' => '', 'port' => '587', 'username' => '', 'password' => '', 'encryption' => 'tls', 'sender' => '', 'connected' => false],
            ],
            'updatedAt' => $user->updated_at?->diffForHumans() ?? '—',
            'createdBy' => $me->name,
            'usage'     => [
                'tokensUsed' => $user->tokensUsed()->sum('tokens_used'),
                'imagesUsed' => $user->imagesUsed()->count(),
                'subusersActive' => $user->children()->count(),
            ],
        ];

        return Inertia::render('Account/Form', [
            'mode'     => 'edit',
            'account'  => $account,
            'managers' => $managers,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_unless($me->parent_id === null || $user->parent_id === $me->id, 403);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];

        if (!empty($request->input('password'))) {
            $user->password = bcrypt($request->input('password'));
        }

        $user->channels = $request->input('channels', $user->channels);

        $user->save();

        return back();
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
