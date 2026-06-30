<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    /**
     * Form di creazione di un nuovo post (Composer).
     */
    public function create(Request $request): Response
    {
        $me = $request->user();

        $userChannels = collect($me->channels ?? [])
            ->filter(fn ($c) => !empty($c['on']))
            ->keys()
            ->values()
            ->all();

        $channelsAvailable = $userChannels ?: array_keys(User::CHANNELS);

        $prefill = $request->session()->pull('post_prefill');

        return Inertia::render('Posts/Form', [
            'channelsAvailable' => $channelsAvailable,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Form di modifica di un post esistente (solo programmati/bozze, vedi destroy() per i permessi).
     */
    public function edit(Request $request, Post $post): Response
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;
        $isManager = $me->child_on == 1;

        $allowed = $isAdmin
            || ($isManager && $post->user->parent_id === $me->id)
            || $post->user_id === $me->id;

        abort_unless($allowed, 403);

        $userChannels = collect($me->channels ?? [])
            ->filter(fn ($c) => !empty($c['on']))
            ->keys()
            ->values()
            ->all();

        $channelsAvailable = $userChannels ?: array_keys(User::CHANNELS);

        $imgSource = $post->img_ai_check_on == '1' ? 'generated' : ($post->img ? 'upload' : null);

        return Inertia::render('Posts/Form', [
            'mode' => 'edit',
            'channelsAvailable' => $channelsAvailable,
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'channels' => collect($post->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'ai_prompt_post' => $post->ai_prompt_post,
                'ai_content' => $post->ai_content,
                'ai_prompt_comment' => $post->ai_prompt_comment,
                'comments_enabled' => $post->comments_enabled === '1',
                'auto_reply_enabled' => $post->auto_reply_enabled === '1',
                'imgUrl' => $post->img ? Storage::disk('public')->url($post->img) : null,
                'img_source' => $imgSource,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    /**
     * Salva un nuovo post (bozza, programmato o da pubblicare).
     */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(array_keys(User::CHANNELS))],
            'ai_prompt_post' => ['nullable', 'string'],
            'comments_enabled' => ['boolean'],
            'auto_reply_enabled' => ['boolean'],
            'ai_prompt_comment' => ['nullable', 'string'],
            'ai_content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:10240'],
            'img_source' => ['nullable', 'string', Rule::in(['upload', 'generated', 'archive'])],
            'published_at' => ['nullable', 'date'],
            'action' => ['required', 'string', Rule::in(['save', 'save_and_add'])],
        ]);

        $img = null;
        if ($request->hasFile('image')) {
            $img = Storage::disk('public')->putFile('posts', $request->file('image'));
        }

        $channels = collect(User::CHANNELS)
            ->keys()
            ->mapWithKeys(fn ($id) => [$id => ['on' => in_array($id, $data['channels'], true)]])
            ->all();

        $post = Post::create([
            'user_id' => $me->id,
            'created_by_user_id' => $me->id,
            'title' => $data['title'] ?? '',
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            'ai_content' => $data['ai_content'] ?? null,
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img' => $img,
            'img_ai_check_on' => ($data['img_source'] ?? null) === 'generated' ? '1' : '0',
            'comments_enabled' => !empty($data['comments_enabled']) ? '1' : '0',
            'auto_reply_enabled' => !empty($data['auto_reply_enabled']) ? '1' : '0',
            'channels' => $channels,
            'published_at' => $data['published_at'] ?? null,
            'published' => '0',
        ]);

        if ($data['action'] === 'save_and_add') {
            $request->session()->flash('post_prefill', [
                'title' => $post->title,
                'channels' => $data['channels'],
                'ai_prompt_post' => $post->ai_prompt_post,
                'comments_enabled' => $post->comments_enabled === '1',
                'auto_reply_enabled' => $post->auto_reply_enabled === '1',
                'ai_prompt_comment' => $post->ai_prompt_comment,
            ]);
            $request->session()->flash('toast', "Post salvato. Ne abbiamo creato una copia: adattala a un altro canale e salva.");

            return redirect()->route('posts.create');
        }

        $request->session()->flash('toast', 'Post salvato.');

        return redirect()->route('posts');
    }

    /**
     * Aggiorna un post esistente (solo titolo/contenuti/canali/data, non lo stato già pubblicato).
     */
    public function update(Request $request, Post $post): RedirectResponse
    {
        $me = $request->user();
        $isAdmin = $me->parent_id === null;
        $isManager = $me->child_on == 1;

        $allowed = $isAdmin
            || ($isManager && $post->user->parent_id === $me->id)
            || $post->user_id === $me->id;

        abort_unless($allowed, 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(array_keys(User::CHANNELS))],
            'ai_prompt_post' => ['nullable', 'string'],
            'comments_enabled' => ['boolean'],
            'auto_reply_enabled' => ['boolean'],
            'ai_prompt_comment' => ['nullable', 'string'],
            'ai_content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:10240'],
            'img_source' => ['nullable', 'string', Rule::in(['upload', 'generated', 'archive'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $channels = collect(User::CHANNELS)
            ->keys()
            ->mapWithKeys(fn ($id) => [$id => ['on' => in_array($id, $data['channels'], true)]])
            ->all();

        $img = $post->img;
        $imgAiCheckOn = $post->img_ai_check_on;
        if ($request->hasFile('image')) {
            $img = Storage::disk('public')->putFile('posts', $request->file('image'));
            $imgAiCheckOn = ($data['img_source'] ?? null) === 'generated' ? '1' : '0';
        }

        $post->update([
            'title' => $data['title'] ?? '',
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            'ai_content' => $data['ai_content'] ?? null,
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img' => $img,
            'img_ai_check_on' => $imgAiCheckOn,
            'comments_enabled' => !empty($data['comments_enabled']) ? '1' : '0',
            'auto_reply_enabled' => !empty($data['auto_reply_enabled']) ? '1' : '0',
            'channels' => $channels,
            'published_at' => $data['published_at'] ?? null,
        ]);

        $request->session()->flash('toast', 'Post aggiornato.');

        return redirect()->route('posts');
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
