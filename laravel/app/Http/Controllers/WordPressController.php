<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WordPressController extends Controller
{
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
        abort_if(empty($settings?->wordpress_url), 422, 'Configura prima l\'URL del sito WordPress.');

        $categories = $this->fetchAndCacheCategories($settings);

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

        $settings = $user->settings;
        abort_if(empty($settings?->wordpress_url), 422, 'Configura prima l\'URL del sito WordPress per questo account.');

        $categories = $this->fetchAndCacheCategories($settings);

        abort_if($categories === null, 502, 'WordPress non ha risposto correttamente. Controlla l\'URL del sito.');

        return response()->json(['categories' => $categories]);
    }

    /**
     * @return array<int, array{id: string, name: string}>|null null se WordPress non ha risposto.
     */
    private function fetchAndCacheCategories(Settings $settings): ?array
    {
        $response = Http::get(rtrim($settings->wordpress_url, '/') . '/wp-json/wp/v2/categories', [
            'per_page' => 100,
            'orderby' => 'name',
            'order' => 'asc',
        ]);

        if (!$response->successful()) {
            return null;
        }

        $categories = collect($response->json())
            ->map(fn (array $c) => ['id' => (string) $c['id'], 'name' => $c['name']])
            ->values()
            ->all();

        $settings->update(['wordpress_options' => ['categories' => $categories]]);

        return $categories;
    }
}
