<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Lista post: l'amministratore vede tutti i post, il manager (child_on=1)
     * vede solo i post dei propri sotto-utenti, l'utente vede solo i propri.
     * Con uno scope globale attivo (?user=), la lista mostra solo i post di
     * quell'utente, indipendentemente dal ruolo del viewer.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $isAdmin = $me->isAdmin();
        $isManager = $me->isManager();
        $showAuthor = $isAdmin || $isManager;
        $activeUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        $scope = fn () => Post::query()->visibleTo($me, $activeUserId);

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
                'status' => $p->status(),
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
     * Metadati canale per canale per l'account $user: disponibile (attivo
     * sull'account) e replyOn (l'account consente le risposte automatiche
     * su quel canale) — usati dal form per mostrare TUTTI i canali (anche
     * quelli non abilitati, disattivati) e per limitare l'auto-risposta.
     */
    private function channelsMeta(User $user): array
    {
        $userChannels = $user->channels ?? [];

        return collect(User::CHANNELS)->keys()->mapWithKeys(function ($id) use ($userChannels) {
            $c = $userChannels[$id] ?? [];

            return [$id => [
                'available' => !empty($c['on']),
                'replyOn' => !empty($c['reply_on']),
            ]];
        })->all();
    }

    /**
     * Salva i file caricati in posts/{post_id}/ con filename univoco
     * (pattern v1: timestamp-random-nomeoriginale.ext), ritorna solo i
     * filename nudi da accodare a Post::img (nessun path in DB, la cartella
     * è per convenzione posts/{id}/).
     */
    private function storeImages(int $postId, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            $safeName = Str::of(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9_-]+/', '_')
                ->trim('_')
                ->value();
            $filename = date('YmdHis').'-'.Str::random(13).'-'.($safeName ?: 'img').'.'.$file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs("posts/{$postId}", $file, $filename);
            $stored[] = $filename;
        }

        return $stored;
    }

    /**
     * Filename nudi in Post::img -> [{filename, url}] risolti sulla cartella
     * posts/{id}/ per la UI (form multi-immagine e vista Show).
     */
    private function imageUrls(Post $post): array
    {
        return collect($post->img ?? [])
            ->map(fn ($filename) => [
                'filename' => $filename,
                'url' => Storage::disk('public')->url("posts/{$post->id}/{$filename}"),
            ])
            ->values()
            ->all();
    }

    /**
     * Form di creazione di un nuovo post (Composer).
     */
    public function create(Request $request): Response
    {
        $me = $request->user();
        $isAdmin = $me->isAdmin();
        $isManager = $me->isManager();

        $users = null;
        if ($isAdmin || $isManager) {
            $usersQuery = User::query()->orderBy('name');
            if ($isAdmin) {
                $usersQuery->whereNotNull('parent_id')->whereNull('child_on');
            } else {
                $usersQuery->where('parent_id', $me->id);
            }

            $users = $usersQuery->get(['id', 'name', 'email', 'channels'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'channelsMeta' => $this->channelsMeta($u),
                ])
                ->values();
        }

        $prefill = $request->session()->pull('post_prefill');

        return Inertia::render('Posts/Form', [
            'channelsMeta' => $this->channelsMeta($me),
            'users' => $users ?? [],
            'prefill' => $prefill,
        ]);
    }

    /**
     * Dettaglio di un post: contenuto inviato, commenti ricevuti e risposte AI.
     */
    public function show(Request $request, Post $post): Response
    {
        $this->authorize('view', $post);

        $post->load([
            'user:id,name,email',
            'token',
            'comments' => fn ($q) => $q->orderByDesc('message_created_time'),
            'comments.token',
        ]);

        $commentsByChannel = $post->comments->countBy('channel');

        $totalTokens = ($post->token?->tokens_used ?? 0)
            + $post->comments->sum(fn ($c) => $c->token?->tokens_used ?? 0);

        $images = $this->imageUrls($post);

        return Inertia::render('Posts/Show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'status' => $post->status(),
                'owner' => ['name' => $post->user->name, 'email' => $post->user->email],
                'channels' => collect($post->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'prompt' => $post->ai_prompt_post,
                'aiContent' => $post->ai_content,
                'postTokens' => $post->token?->tokens_used ?? 0,
                'totalTokens' => $totalTokens,
                'commentsEnabled' => $post->comments_enabled === '1',
                'autoReplyEnabled' => $post->auto_reply_enabled === '1',
                'commentsByChannel' => $commentsByChannel,
                'commentsTotal' => $post->comments->count(),
                'imgUrl' => $images[0]['url'] ?? null,
                'images' => $images,
                'publishedAt' => $post->published_at?->toIso8601String(),
                'createdAt' => $post->created_at?->toIso8601String(),
            ],
            'comments' => $post->comments->map(fn ($c) => [
                'id' => $c->id,
                'channel' => $c->channel,
                'author' => $c->from_name,
                'time' => $c->message_created_time?->toIso8601String(),
                'text' => $c->message,
                'reply' => $c->reply ? [
                    'text' => $c->reply,
                    'time' => $c->reply_created_time?->toIso8601String(),
                    'tokens' => $c->token?->tokens_used,
                ] : null,
            ])->values(),
        ]);
    }

    /**
     * Form di modifica di un post esistente (solo programmati/bozze, vedi destroy() per i permessi).
     */
    public function edit(Request $request, Post $post): Response|RedirectResponse
    {
        $me = $request->user();
        $this->authorize('update', $post);
        $isAdmin = $me->isAdmin();
        $isManager = $me->isManager();

        if ($post->status() === 'published') {
            return redirect()->route('posts.show', $post);
        }

        $imgSource = $post->img_ai_check_on == '1' ? 'generated' : (!empty($post->img) ? 'upload' : null);

        return Inertia::render('Posts/Form', [
            'mode' => 'edit',
            'channelsMeta' => $this->channelsMeta($post->user),
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'ownerId' => $post->user_id,
                'owner' => $isAdmin || $isManager
                    ? ['name' => $post->user->name, 'email' => $post->user->email]
                    : null,
                'channels' => $post->channels ?? [],
                'ai_prompt_post' => $post->ai_prompt_post,
                'ai_content' => $post->ai_content,
                'ai_prompt_comment' => $post->ai_prompt_comment,
                'images' => $this->imageUrls($post),
                'img_source' => $imgSource,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    /**
     * Ricostruisce i canali selezionati per il post a partire dal payload
     * grezzo del form: whitelist dei campi attesi per tipo di canale, non un
     * pass-through — coerente con come AccountController tratta i canali.
     * I canali non presenti nel payload restano semplicemente 'on' => false.
     *
     * $accountChannels e' lo snapshot di User::channels dell'account target:
     * il tetto di risposte automatiche (reply_n) e' una preferenza account-level
     * (impostata in Account) ma viene CONGELATO nel post al salvataggio, cosi'
     * il worker di pubblicazione applica le regole leggendo solo il post, senza
     * dover risalire all'account (comportamento voluto, coerente con v1 dove
     * tutto lo stato di pubblicazione viveva nel post).
     */
    private function buildChannelsPayload(array $selected, array $accountChannels = []): array
    {
        return collect(User::CHANNELS)->keys()->mapWithKeys(function ($id) use ($selected, $accountChannels) {
            if (!array_key_exists($id, $selected)) {
                return [$id => ['on' => false]];
            }

            $opts = is_array($selected[$id]) ? $selected[$id] : [];

            if (in_array($id, ['facebook', 'instagram', 'linkedin'], true)) {
                return [$id => [
                    'on' => true,
                    'comments_enabled' => !empty($opts['comments_enabled']),
                    'auto_reply_enabled' => !empty($opts['auto_reply_enabled']),
                    'reply_n' => $accountChannels[$id]['reply_n'] ?? null,
                ]];
            }

            if ($id === 'wordpress') {
                $categories = collect($opts['categories'] ?? [])
                    ->filter(fn ($c) => is_array($c) && !empty($c['id']))
                    ->map(fn ($c) => [
                        'id' => (string) $c['id'],
                        'name' => (string) ($c['name'] ?? ''),
                        'on' => !empty($c['on']),
                    ])
                    ->values()
                    ->all();

                return [$id => ['on' => true, 'categories' => $categories]];
            }

            if ($id === 'newsletter') {
                $list = $opts['list'] ?? null;
                $list = (is_array($list) && !empty($list['id'])) ? [
                    'provider' => $list['provider'] ?? null,
                    'id' => (string) $list['id'],
                    'name' => (string) ($list['name'] ?? ''),
                ] : null;

                return [$id => ['on' => true, 'list' => $list]];
            }

            return [$id => ['on' => true]];
        })->all();
    }

    /**
     * Aggregato [commenti abilitati, auto-risposta abilitata] su ALMENO un
     * canale — le due colonne DB restano un riassunto per Posts/Show.vue,
     * la verita' per canale vive nel JSON channels.
     */
    private function commentsAggregate(array $channels): array
    {
        return [
            collect($channels)->contains(fn ($c) => !empty($c['comments_enabled'])),
            collect($channels)->contains(fn ($c) => !empty($c['auto_reply_enabled'])),
        ];
    }

    /**
     * Salva un nuovo post (bozza, programmato o da pubblicare).
     */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $isAdmin = $me->isAdmin();
        $isManager = $me->isManager();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'channels' => ['required', 'array', 'min:1'],
            'ai_prompt_post' => ['nullable', 'string'],
            'ai_prompt_comment' => ['nullable', 'string'],
            'ai_content' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:10240'],
            'img_source' => ['nullable', 'string', Rule::in(['upload', 'generated', 'archive'])],
            'published_at' => ['nullable', 'date'],
            'action' => ['required', 'string', Rule::in(['save', 'save_and_add'])],
        ]);

        $unknown = array_diff(array_keys($data['channels']), array_keys(User::CHANNELS));
        abort_if(!empty($unknown), 422, 'Canale non valido.');

        $targetUserId = match (true) {
            $isAdmin => User::whereNotNull('parent_id')->whereNull('child_on')
                ->whereKey($data['user_id'] ?? null)->value('id'),
            $isManager => User::where('parent_id', $me->id)
                ->whereKey($data['user_id'] ?? null)->value('id'),
            default => $me->id,
        };
        abort_if(($isAdmin || $isManager) && !$targetUserId, 422, 'Account non valido.');

        $targetChannels = $targetUserId === $me->id ? $me->channels : (User::find($targetUserId)?->channels ?? []);
        $channels = $this->buildChannelsPayload($data['channels'], $targetChannels ?? []);
        [$commentsEnabled, $autoReplyEnabled] = $this->commentsAggregate($channels);

        $post = Post::create([
            'user_id' => $targetUserId,
            'created_by_user_id' => $me->id,
            'title' => $data['title'] ?? '',
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            'ai_content' => $data['ai_content'] ?? null,
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img' => null,
            'img_ai_check_on' => ($data['img_source'] ?? null) === 'generated' ? '1' : '0',
            'comments_enabled' => $commentsEnabled ? '1' : '0',
            'auto_reply_enabled' => $autoReplyEnabled ? '1' : '0',
            'channels' => $channels,
            'published_at' => $data['published_at'] ?? null,
            'published' => '0',
        ]);

        if ($request->hasFile('images')) {
            $post->update(['img' => $this->storeImages($post->id, $request->file('images'))]);
        }

        if ($data['action'] === 'save_and_add') {
            $request->session()->flash('post_prefill', [
                'title' => $post->title,
                'channels' => $data['channels'],
                'ai_prompt_post' => $post->ai_prompt_post,
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
        $this->authorize('update', $post);

        abort_if($post->status() === 'published', 403, 'Un post pubblicato non può essere modificato.');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'ai_prompt_post' => ['nullable', 'string'],
            'ai_prompt_comment' => ['nullable', 'string'],
            'ai_content' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:10240'],
            'keep_images' => ['nullable', 'string'],
            'img_source' => ['nullable', 'string', Rule::in(['upload', 'generated', 'archive'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $unknown = array_diff(array_keys($data['channels']), array_keys(User::CHANNELS));
        abort_if(!empty($unknown), 422, 'Canale non valido.');

        $channels = $this->buildChannelsPayload($data['channels'], $post->user?->channels ?? []);
        [$commentsEnabled, $autoReplyEnabled] = $this->commentsAggregate($channels);

        $existing = $post->img ?? [];
        $keep = isset($data['keep_images']) ? (json_decode($data['keep_images'], true) ?? []) : $existing;
        $kept = array_values(array_filter($keep, fn ($f) => in_array($f, $existing, true)));

        foreach (array_diff($existing, $kept) as $removed) {
            Storage::disk('public')->delete("posts/{$post->id}/{$removed}");
        }

        $newImages = $request->hasFile('images') ? $this->storeImages($post->id, $request->file('images')) : [];
        $img = array_merge($kept, $newImages);

        $imgAiCheckOn = empty($newImages)
            ? $post->img_ai_check_on
            : (($data['img_source'] ?? null) === 'generated' ? '1' : '0');

        $post->update([
            'title' => $data['title'] ?? '',
            'ai_prompt_post' => $data['ai_prompt_post'] ?? null,
            'ai_content' => $data['ai_content'] ?? null,
            'ai_prompt_comment' => $data['ai_prompt_comment'] ?? null,
            'img' => $img,
            'img_ai_check_on' => $imgAiCheckOn,
            'comments_enabled' => $commentsEnabled ? '1' : '0',
            'auto_reply_enabled' => $autoReplyEnabled ? '1' : '0',
            'channels' => $channels,
            'published_at' => $data['published_at'] ?? null,
        ]);

        $request->session()->flash('toast', 'Post aggiornato.');

        return redirect()->route('posts');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return back();
    }
}
