<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $me = $request->user();
        $activeUser = $me?->resolveScopedUser($request->session()->get('scoped_user_id'));

        // Modulo Contatti: riflette l'account attualmente visualizzato (scope
        // attivo), non l'utente loggato — stesso concetto di channelsMeta()
        // per il compose dei post.
        $contactsUser = isset($activeUser['id']) ? User::find($activeUser['id']) : $me;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $me?->only('id', 'name', 'email'),
            ],
            // Scope globale "filtra per utente": condiviso su ogni pagina così
            // la sidebar (AppLayout) può mostrare il controllo ovunque senza
            // che ogni pagina/controller debba passarlo esplicitamente.
            'isAdmin' => $me?->isAdmin() ?? false,
            'isManager' => $me?->isManager() ?? false,
            'filterableUsers' => $me?->filterableUsers() ?? [],
            'activeUserId' => $activeUser['id'] ?? null,
            'activeUser' => $activeUser,
            'contactsEnabled' => $contactsUser?->hasSmtpCustomActive() ?? false,
            'app' => [
                'version' => env('APP_VERSION', '0.0.0'),
                'changelog_url' => env('APP_CHANGELOG_URL', '#'),
            ],
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
        ];
    }
}
