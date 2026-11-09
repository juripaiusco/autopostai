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
            'contactsEnabled' => $me?->canViewContacts($activeUser['id'] ?? null) ?? false,
            // Barra "Crediti utilizzati" (topbar + sidebar): consumo AI del
            // mese dell'utente filtrato, altrimenti il proprio; l'admin senza
            // filtro non ha un limite suo da mostrare (null = barra nascosta).
            'usage' => fn () => $this->usage($me, $activeUser['id'] ?? null),
            // Token veri solo all'admin nella vista generale; filtrando un
            // utente (e per manager/utenti) si vedono crediti = token/1000.
            'showTokens' => ($me?->isAdmin() ?? false) && $activeUser === null,
            'app' => [
                'version' => config('app.version'),
                'changelog_url' => config('app.changelog_url'),
            ],
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
        ];
    }

    /**
     * Consumo AI del mese (stessa somma del limite: TokenLog di ogni tipo) e
     * percentuale sul limite. Limite 0/vuoto = account bloccato (come il
     * worker, usati >= limite), quindi 100%.
     *
     * @return array{used: int, limit: int, percent: int}|null
     */
    private function usage(?User $me, ?int $scopedUserId): ?array
    {
        $target = match (true) {
            $me === null => null,
            $scopedUserId !== null => User::find($scopedUserId),
            $me->isAdmin() => null,
            default => $me,
        };

        if ($target === null) {
            return null;
        }

        $used = (int) $target->tokensUsed()->sum('tokens_used');
        $limit = (int) $target->tokens_limit;

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $limit > 0 ? (int) min(100, round($used / $limit * 100)) : 100,
        ];
    }
}
