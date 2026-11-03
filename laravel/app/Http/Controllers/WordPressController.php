<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WordPressCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WordPressController extends Controller
{
    public function __construct(private WordPressCategories $categories) {}

    /**
     * Categorie del sito WordPress dell'account, per sceglierle da un elenco
     * invece di scrivere l'ID a mano. Endpoint pubblico di WordPress (nessuna
     * credenziale richiesta) — username/password servono solo per pubblicare,
     * gestito lato Python. Usato dalla pagina Account (redirect indietro).
     */
    public function fetchCategories(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $settings = $user->settings;
        if (empty($settings?->wordpress_url)) {
            return back()->with('toast', 'Salva prima l\'URL del sito WordPress per questo account.');
        }

        $categories = $this->categories->fetchAndCache($settings);

        if ($categories === null) {
            return back()->with('toast', 'WordPress non ha risposto correttamente. Controlla l\'URL del sito.');
        }

        return back()->with('toast', count($categories) . ' categorie caricate da WordPress.');
    }

    /**
     * Stessa cosa, ma richiamata live dal form di creazione/modifica post
     * (bottone di aggiornamento dentro lo step Canali) — risponde in JSON,
     * niente redirect, cosi' non si perde lo stato del form in corso.
     */
    public function categoriesForPost(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->canActFor($user), 403);

        // JSON esplicito: fuori da api/* gli abort() vengono resi come pagina HTML.
        $settings = $user->settings;
        if (empty($settings?->wordpress_url)) {
            return response()->json(['message' => 'Configura prima l\'URL del sito WordPress per questo account.'], 422);
        }

        $categories = $this->categories->fetchAndCache($settings);

        if ($categories === null) {
            return response()->json(['message' => 'WordPress non ha risposto correttamente. Controlla l\'URL del sito.'], 502);
        }

        return response()->json(['categories' => $categories]);
    }
}
