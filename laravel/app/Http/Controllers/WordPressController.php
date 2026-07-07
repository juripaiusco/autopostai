<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WordPressController extends Controller
{
    /**
     * Categorie del sito WordPress dell'account, per sceglierle da un elenco
     * invece di scrivere l'ID a mano. Endpoint pubblico di WordPress (nessuna
     * credenziale richiesta) — username/password servono solo per pubblicare,
     * gestito lato Python.
     */
    public function fetchCategories(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $settings = $user->settings;
        abort_if(empty($settings?->wordpress_url), 422, 'Configura prima l\'URL del sito WordPress.');

        $response = Http::get(rtrim($settings->wordpress_url, '/') . '/wp-json/wp/v2/categories', [
            'per_page' => 100,
            'orderby' => 'name',
            'order' => 'asc',
        ]);

        if (!$response->successful()) {
            return back()->with('toast', 'WordPress non ha risposto correttamente. Controlla l\'URL del sito.');
        }

        $categories = collect($response->json())
            ->map(fn (array $c) => ['id' => (string) $c['id'], 'name' => $c['name']])
            ->values()
            ->all();

        $settings->update(['wordpress_options' => ['categories' => $categories]]);

        return back()->with('toast', count($categories) . ' categorie caricate da WordPress.');
    }
}
