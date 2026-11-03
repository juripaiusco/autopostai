<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use App\Services\NewsletterLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function __construct(private NewsletterLists $lists) {}

    /**
     * Liste dell'account newsletter configurato (solo uno tra MailChimp e
     * Brevo, chi ha una API key impostata vince — stessa regola di v1),
     * per sceglierle da un elenco invece di un ID scritto a mano. Usato
     * dalla pagina Account (redirect indietro).
     */
    public function fetchLists(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $settings = $user->settings;
        [$lists, $error] = $this->lists->fetchAndCache($settings);

        if ($error !== null) {
            return back()->with('toast', $error);
        }

        return back()->with('toast', count($lists) . ' liste caricate da ' . ucfirst($this->providerOf($settings)) . '.');
    }

    /**
     * Stessa cosa, ma richiamata live dal form di creazione/modifica post —
     * risponde in JSON, niente redirect, cosi' non si perde lo stato del
     * form in corso.
     */
    public function listsForPost(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        [$lists, $error] = $this->lists->fetchAndCache($user->settings);

        // JSON esplicito: fuori da api/* gli abort() vengono resi come pagina HTML.
        if ($error !== null) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json([
            'provider' => $this->providerOf($user->settings),
            'lists' => $lists,
        ]);
    }

    private function providerOf(?Settings $settings): ?string
    {
        return $settings?->newsletterProvider();
    }
}
