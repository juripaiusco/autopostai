<?php

namespace App\Http\Controllers;

use App\Actions\Posts\CreatePost;
use App\Actions\Posts\DuplicatePost;
use App\Actions\Posts\RetryPost;
use App\Actions\Posts\UpdatePost;
use App\Actions\Posts\UpdatePublishedChannelContent;
use App\Models\Post;
use App\Models\User;
use App\Services\PostHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    private const TEXT_QUEUED_TOAST = "Post salvato. Il testo verrà generato dall'AI a breve: potrai rivederlo prima della pubblicazione.";

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

        $counts = [
            'tutti' => $scope()->count(),
            'pubblicati' => $scope()->listFilter('pubblicati')->count(),
            'programmati' => $scope()->listFilter('programmati')->count(),
            'bozze' => $scope()->listFilter('bozze')->count(),
        ];

        $filter = $request->string('filter')->toString() ?: 'tutti';
        $search = trim($request->string('search')->toString());
        $sortKey = $request->string('sort')->toString() ?: 'publishedAt';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'title' => 'title',
            'publishedAt' => 'published_at',
        ];
        $sortColumn = $sortable[$sortKey] ?? 'published_at';

        $query = $scope()->listFilter($filter);

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        // Ordinando per data le bozze (senza data) restano in cima in entrambe
        // le direzioni: sono i post da ultimare. MariaDB in DESC metterebbe i
        // NULL in fondo. Titolo: ordine scelto dall'utente, senza pin.
        if ($sortColumn === 'published_at') {
            $query->orderByRaw('published_at IS NULL DESC');
        }

        $posts = $query
            ->with('user:id,name')
            ->withCount('comments')
            ->orderBy($sortColumn, $dir)
            ->orderByDesc('id')
            ->paginate(15)
            ->onEachSide(1)
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
                'monitoringUntil' => $p->monitoringUntil(),
                'publishedAt' => $p->published_at?->toIso8601String(),
                'comments' => $p->comments_count,
                'newsletter' => $p->newsletterProgress(),
                'cover' => $p->coverUrl(),
                'thumb' => $p->thumbGradient(),
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
     * Modelli selezionabili in "Genera con AI" (config/ai.php) per il
     * combobox del form, più quello preselezionato.
     */
    private function imageModelsProps(): array
    {
        return [
            'imageModels' => collect(config('ai.image_models'))
                ->map(fn (array $m, string $id) => [
                    'id' => $id,
                    'name' => $m['label'],
                    'provider' => $m['provider'],
                    'description' => $m['description'],
                ])
                ->values()
                ->all(),
            'defaultImageModel' => config('ai.default_image_model'),
        ];
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
                    'channelsMeta' => $u->channelsMeta(),
                ])
                ->values();
        }

        return Inertia::render('Posts/Form', [
            'channelsMeta' => $me->channelsMeta(),
            'users' => $users ?? [],
            ...$this->imageModelsProps(),
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
            'tokens',
            'comments' => fn ($q) => $q->orderByDesc('message_created_time'),
            'comments.token',
        ]);

        $commentsByChannel = $post->comments->countBy('channel');

        // Più log "post" per lo stesso post: anteprime collegate al
        // salvataggio, job di generazione, worker. Conta la somma.
        $postTokens = (int) $post->tokens->sum('tokens_used');
        $totalTokens = $postTokens
            + $post->comments->sum(fn ($c) => $c->token?->tokens_used ?? 0);

        $images = $this->imageUrls($post);

        return Inertia::render('Posts/Show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'status' => $post->status(),
                'monitoringUntil' => $post->monitoringUntil(),
                'channelStatus' => $post->channelStatuses(),
                'newsletter' => $post->newsletterProgress(),
                'channelErrors' => collect($post->channelErrors())
                    ->map(fn ($e, $channel) => ['channel' => $channel, ...$e])
                    ->values()
                    ->all(),
                'maxAttempts' => Post::PUBLISH_MAX_ATTEMPTS,
                // Box "Modifica post": testo del canale (o quello comune) e
                // stato dell'ultimo invio a posts_update.
                'channelEdit' => collect($post->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->map(fn ($c, $channel) => [
                        'editable' => in_array($channel, Post::EDITABLE_AFTER_PUBLISH, true) && !empty($c['id']),
                        ...$this->publishedChannelContent($post, $channel, $c),
                        'pending' => !empty($c['update_pending']),
                        'error' => $c['update_error'] ?? null,
                    ])
                    ->all(),
                'owner' => ['name' => $post->user->name, 'email' => $post->user->email],
                'ownerId' => $post->user_id,
                'channels' => collect($post->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'prompt' => $post->ai_prompt_post,
                'aiContent' => $post->ai_content,
                // Testo articolo già in HTML sicuro (sanitizer o Markdown convertito).
                'aiContentHtml' => $post->textChannel() !== null && trim((string) $post->ai_content) !== ''
                    ? $post->contentHtml()
                    : null,
                'postTokens' => $postTokens,
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
                'replyFailedAt' => $c->reply_failed_at?->toIso8601String(),
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

        if (!$post->isEditable()) {
            return redirect()->route('posts.show', $post);
        }

        return Inertia::render('Posts/Form', [
            'mode' => 'edit',
            'channelsMeta' => $post->user->channelsMeta(),
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'ownerId' => $post->user_id,
                'owner' => $isAdmin || $isManager
                    ? ['name' => $post->user->name, 'email' => $post->user->email]
                    : null,
                'channels' => $post->channels ?? [],
                'ai_prompt_post' => $post->ai_prompt_post,
                // Con WordPress/Newsletter l'editor ricco lavora in HTML: il
                // Markdown (testo AI, post vecchi) arriva già convertito.
                ...$this->editorContent($post),
                'ai_content_social' => $post->ai_content_social,
                'ai_prompt_comment' => $post->ai_prompt_comment,
                'images' => $this->imageUrls($post),
                'img_ai_check_on' => $post->img_ai_check_on == '1',
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
            ],
            'seriesCopy' => $request->session()->get('series_copy_id') === $post->id,
            ...$this->imageModelsProps(),
        ]);
    }

    /**
     * Testo attuale di un canale pubblicato per il box "Modifica post":
     * WordPress in HTML per l'editor ricco, i social in testo semplice
     * ('html' = articolo da ripulire in pagina, stesse regole del worker).
     *
     * @return array{content: ?string, format: string}
     */
    private function publishedChannelContent(Post $post, string $channel, array $c): array
    {
        $own = $c['content'] ?? null;

        if ($channel === 'wordpress' || $channel === 'newsletter') {
            $html = match (true) {
                $own === null => $post->contentHtml(),
                ($c['content_format'] ?? null) === Post::FORMAT_HTML => app(PostHtmlSanitizer::class)->clean($own),
                default => Post::markdownToHtml($own),
            };

            return ['content' => $html, 'format' => Post::FORMAT_HTML];
        }

        return match (true) {
            $own !== null => ['content' => $own, 'format' => Post::FORMAT_TEXT],
            filled($post->ai_content_social) && $post->textChannel() !== null => ['content' => $post->ai_content_social, 'format' => Post::FORMAT_TEXT],
            $post->textChannel() !== null => ['content' => $post->contentHtml(), 'format' => Post::FORMAT_HTML],
            default => ['content' => $post->ai_content, 'format' => Post::FORMAT_TEXT],
        };
    }

    /**
     * @return array{ai_content: ?string, ai_content_format: string}
     */
    private function editorContent(Post $post): array
    {
        if ($post->textChannel() === null || trim((string) $post->ai_content) === '') {
            return ['ai_content' => $post->ai_content, 'ai_content_format' => $post->ai_content_format ?? Post::FORMAT_MARKDOWN];
        }

        return ['ai_content' => $post->contentHtml(), 'ai_content_format' => Post::FORMAT_HTML];
    }

    /**
     * Salva un nuovo post (bozza, programmato o da pubblicare).
     */
    public function store(Request $request, CreatePost $createPost): RedirectResponse
    {
        $data = $request->validate(CreatePost::rules());

        [$post, $textQueued] = $createPost->handle($request->user(), $data, $request->file('images', []));

        if ($data['action'] === 'save_and_add') {
            return $this->saveAndAdd($request, $post);
        }

        $request->session()->flash('toast', $this->savedToast($data, $textQueued ? self::TEXT_QUEUED_TOAST : 'Post salvato.'));

        return redirect()->route('posts');
    }

    /**
     * "Salva e aggiungi" (come v1): dopo il salvataggio crea la copia
     * programmata 1 minuto dopo e ne apre il form. series_copy_id fa
     * mostrare nel form l'avviso di doppia pubblicazione se la copia esce
     * entro un'ora (vedi DuplicatePost::nextInSeries).
     */
    private function saveAndAdd(Request $request, Post $post): RedirectResponse
    {
        $copy = app(DuplicatePost::class)->nextInSeries($request->user(), $post->refresh());

        $request->session()->flash('series_copy_id', $copy->id);
        $request->session()->flash('toast', 'Post salvato. Copia programmata 1 minuto dopo: adattala a un altro canale e salva.');

        return redirect()->route('posts.edit', $copy);
    }

    private function savedToast(array $data, string $default): string
    {
        return ($data['action'] ?? null) === 'publish_now'
            ? 'Post in pubblicazione: esce entro un minuto.'
            : $default;
    }

    /**
     * Aggiorna un post esistente (solo titolo/contenuti/canali/data, non lo stato già pubblicato).
     */
    public function update(Request $request, Post $post, UpdatePost $updatePost): RedirectResponse
    {
        $this->authorize('update', $post);
        $updatePost->ensureEditable($post);

        $data = $request->validate([...UpdatePost::rules(), 'keep_images' => ['nullable', 'string']]);

        // keep_images (JSON del form) assente = tieni tutte le immagini.
        $keep = isset($data['keep_images']) ? (json_decode($data['keep_images'], true) ?? []) : null;

        $textQueued = $updatePost->handle($post, $data, $request->file('images', []), $keep);

        if (($data['action'] ?? null) === 'save_and_add') {
            return $this->saveAndAdd($request, $post);
        }

        $request->session()->flash('toast', $this->savedToast($data, $textQueued ? self::TEXT_QUEUED_TOAST : 'Post aggiornato.'));

        return redirect()->route('posts');
    }

    /**
     * "Riprova" dalla pagina del post (vedi RetryPost).
     */
    public function retry(Request $request, Post $post, RetryPost $retryPost): RedirectResponse
    {
        $this->authorize('update', $post);

        $retryPost->handle($post);

        $request->session()->flash('toast', 'Nuovo tentativo in coda: il worker riprova entro un minuto.');

        return back();
    }

    /**
     * "Duplica" dalla lista post: copia come bozza senza data e apre il form
     * della copia per scegliere quando inviarla (vedi DuplicatePost).
     */
    public function duplicate(Request $request, Post $post, DuplicatePost $duplicatePost): RedirectResponse
    {
        $this->authorize('update', $post);

        $copy = $duplicatePost->handle($request->user(), $post);

        $request->session()->flash('toast', 'Post duplicato: scegli la data di invio e salva.');

        return redirect()->route('posts.edit', $copy);
    }

    /**
     * "Modifica post" dal box di un canale già pubblicato (vedi UpdatePublishedChannelContent).
     */
    public function updateChannelContent(Request $request, Post $post, string $channel, UpdatePublishedChannelContent $updateContent): RedirectResponse
    {
        $this->authorize('update', $post);
        $updateContent->ensureEditable($post, $channel);

        $data = $request->validate(UpdatePublishedChannelContent::rules());

        $updateContent->handle($post, $channel, $data['content'], $data['format'] ?? null);

        $label = User::CHANNELS[$channel]['name'];
        $request->session()->flash('toast', "Modifica in coda: {$label} viene aggiornato entro un minuto.");

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return back();
    }
}
