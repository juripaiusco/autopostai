<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Lista utenti/account: l'amministratore (parent_id null) vede tutti,
     * chi ha un parent vede solo i propri sotto-utenti. Con uno scope
     * globale attivo (?user=), si naviga l'albero account come se si fosse
     * l'utente scopato, invece che sé stessi.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $this->authorize('viewAny', User::class);
        $isAdmin = $me->isAdmin();
        $activeUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        $scope = fn () => ($isAdmin && !$activeUserId)
            ? User::query()
            : User::where('parent_id', $activeUserId ?? $me->id);

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
                'role' => $u->isAdmin() ? 'amministratore' : ($u->isManager() ? 'manager' : 'utente'),
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
        $this->authorize('create', User::class);

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
        $this->authorize('create', User::class);

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
        $this->authorize('view', $user);
        $isAdmin = $me->isAdmin();

        $managers = User::whereNull('parent_id')->orWhere('child_on', 1)
            ->where('id', '!=', $user->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);

        $rawChannels = $user->channels ?? [];
        $channels = [];
        foreach (User::CHANNELS as $key => $meta) {
            $channels[$key] = array_merge([
                'name' => $meta['name'],
                'css_class' => $meta['css_class'],
                'id' => null,
                'on' => null,
                'reply_on' => null,
                'reply_n' => null,
                'options' => [],
            ], $rawChannels[$key] ?? []);
        }

        $s = $user->settings;

        // Elenco pagine LinkedIn amministrate da chi ha appena autorizzato
        // (flash di un solo giro, impostato da LinkedInController::callback()).
        $pendingLinkedinPages = $request->session()->get('linkedin_pages');
        $linkedinPages = ($pendingLinkedinPages && $pendingLinkedinPages['user_id'] === $user->id)
            ? $pendingLinkedinPages['pages']
            : [];

        // Quanti altri account condividono la stessa app LinkedIn (client_id +
        // secret): un refresh del token qui si propaga a tutti loro (voluto).
        $linkedinSharedWithCount = 0;
        if (!empty($s?->linkedin_client_id) && !empty($s?->linkedin_client_secret)) {
            $linkedinSharedWithCount = Settings::where('linkedin_client_id', $s->linkedin_client_id)
                ->where('linkedin_client_secret', $s->linkedin_client_secret)
                ->where('user_id', '!=', $user->id)
                ->count();
        }

        $account = [
            'id'            => $user->id,
            'parent_id'     => $user->parent_id,
            'name'          => $user->name,
            'email'         => $user->email,
            'password'      => '',
            'channels'      => $channels,
            'manager'       => $user->manager_id ?? '',
            'canSubusers'   => (bool) ($user->child_on ?? false),
            'subusersLimit' => $user->child_max ?? '',
            'tokensMonth'   => $user->tokens_limit ?? '',
            'imagesDay'     => $user->image_model_limit ?? '',
            'ai'            => [
                'profile'       => $s->ai_personality ?? '',
                'knows'         => $s->ai_prompt_prefix ?? '',
                'commentStyle'  => $s->ai_comment_prefix ?? '',
            ],
            'openai'        => ['apiKey' => $s->openai_api_key ?? '', 'connected' => !empty($s?->openai_api_key)],
            'meta'          => ['pageId' => $s->meta_page_id ?? '', 'connected' => !empty($s?->meta_page_id)],
            'linkedin'      => [
                'clientId'      => $s->linkedin_client_id ?? '',
                'clientSecret'  => $s->linkedin_client_secret ?? '',
                'pageId'        => $s->linkedin_company_id ?? '',
                'token'         => $s->linkedin_token ?? '',
                'connected'     => !empty($s?->linkedin_token),
                'tokenExpiresAt' => $s?->linkedin_token_expires_at?->diffForHumans(),
                'sharedWithCount' => $linkedinSharedWithCount,
                'connectUrl'    => route('linkedin.redirect', $user),
                'pageUpdateUrl' => route('linkedin.page.update', $user),
                'availablePages' => $linkedinPages,
            ],
            'wordpress'     => [
                'url'           => $s->wordpress_url ?? '',
                'username'      => $s->wordpress_username ?? '',
                'password'      => $s->wordpress_password ?? '',
                'categoryId'    => $s->wordpress_cat_id ?? '',
                'connected'     => !empty($s?->wordpress_url) && !empty($s?->wordpress_username),
                'categories'    => $s?->wordpress_options['categories'] ?? [],
                'categoriesUrl' => route('wordpress.categories', $user),
            ],
            'newsletter'    => [
                'mailchimp'     => [
                    'apiKey'        => $s->nl_mailchimp_api ?? '',
                    'serverPrefix'  => $s->nl_mailchimp_datacenter ?? '',
                    'audienceId'    => $s->nl_mailchimp_list_id ?? '',
                    'connected'     => !empty($s?->nl_mailchimp_api),
                    'lists'         => $s?->nl_mailchimp_options['lists'] ?? [],
                ],
                'brevo'     => [
                    'apiKey'    => $s->nl_brevo_api ?? '',
                    'listId'    => $s->nl_brevo_list_id ?? '',
                    'sender'    => $s->nl_brevo_from_email ?? '',
                    'connected' => !empty($s?->nl_brevo_api),
                    'lists'     => $s?->nl_brevo_options['lists'] ?? [],
                ],
                'smtp'      => [
                    'host'          => $s->nl_smtp_host ?? '',
                    'port'          => $s->nl_smtp_port ?? '587',
                    'username'      => $s->nl_smtp_username ?? '',
                    'password'      => $s->nl_smtp_password ?? '',
                    'encryption'    => $s->nl_smtp_encryption ?? 'tls',
                    'sender'        => $s->nl_smtp_sender ?? '',
                    'connected'     => !empty($s?->nl_smtp_host),
                ],
                'template'      => [
                    'content'   => $s->nl_template ?? '',
                    'cta'       => $s->nl_template_cta ?? '',
                ],
                'listsUrl'      => route('newsletter.lists', $user),
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
        $this->authorize('update', $user);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];

        if (!empty($request->input('password'))) {
            $user->password = bcrypt($request->input('password'));
        }

        $incoming = $request->input('channels', []);
        $channels = $user->channels ?? [];
        foreach (User::CHANNELS as $key => $meta) {
            $channels[$key] = array_merge(
                $channels[$key] ?? ['name' => $meta['name'], 'css_class' => $meta['css_class'], 'id' => null, 'options' => []],
                [
                    'on'       => $incoming[$key]['on'] ?? null,
                    'reply_on' => $incoming[$key]['reply_on'] ?? null,
                    'reply_n'  => $incoming[$key]['reply_n'] ?? null,
                    'options'  => $incoming[$key]['options'] ?? ($channels[$key]['options'] ?? []),
                ]
            );
        }
        $user->channels = $channels;

        $user->save();

        $ai = $request->input('ai', []);
        $openai = $request->input('openai', []);
        $meta = $request->input('meta', []);
        $linkedin = $request->input('linkedin', []);
        $wordpress = $request->input('wordpress', []);
        $newsletter = $request->input('newsletter', []);
        $mailchimp = $newsletter['mailchimp'] ?? [];
        $brevo = $newsletter['brevo'] ?? [];
        $smtp = $newsletter['smtp'] ?? [];
        $template = $newsletter['template'] ?? [];

        Settings::updateOrCreate(
            ['user_id' => $user->id],
            [
                'ai_personality' => $ai['profile'] ?? null,
                'ai_prompt_prefix' => $ai['knows'] ?? null,
                'ai_comment_prefix' => $ai['commentStyle'] ?? null,

                'openai_api_key' => $openai['apiKey'] ?? null,

                'meta_page_id' => $meta['pageId'] ?? null,

                'linkedin_client_id' => $linkedin['clientId'] ?? null,
                'linkedin_client_secret' => $linkedin['clientSecret'] ?? null,
                'linkedin_company_id' => $linkedin['pageId'] ?? null,

                'wordpress_url' => $wordpress['url'] ?? null,
                'wordpress_username' => $wordpress['username'] ?? null,
                'wordpress_password' => $wordpress['password'] ?? null,
                'wordpress_cat_id' => $wordpress['categoryId'] ?? null,

                'nl_mailchimp_api' => $mailchimp['apiKey'] ?? null,
                'nl_mailchimp_datacenter' => $mailchimp['serverPrefix'] ?? null,
                'nl_mailchimp_list_id' => $mailchimp['audienceId'] ?? null,

                'nl_brevo_api' => $brevo['apiKey'] ?? null,
                'nl_brevo_list_id' => $brevo['listId'] ?? null,
                'nl_brevo_from_email' => $brevo['sender'] ?? null,

                'nl_smtp_host' => $smtp['host'] ?? null,
                'nl_smtp_port' => $smtp['port'] ?? null,
                'nl_smtp_username' => $smtp['username'] ?? null,
                'nl_smtp_password' => $smtp['password'] ?? null,
                'nl_smtp_encryption' => $smtp['encryption'] ?? null,
                'nl_smtp_sender' => $smtp['sender'] ?? null,

                'nl_template' => $template['content'] ?? null,
                'nl_template_cta' => $template['cta'] ?? null,
            ]
        );

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return back();
    }
}
