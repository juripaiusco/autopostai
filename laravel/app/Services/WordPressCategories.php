<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Categorie del sito WordPress dell'account (endpoint pubblico, nessuna
 * credenziale), salvate in settings come cache per i selettori. Usato da
 * WordPressController (pagina Account e form post) e dal tool MCP
 * refresh_channel_options.
 */
class WordPressCategories
{
    /**
     * `count` = articoli nella categoria al momento del caricamento.
     *
     * @return array<int, array{id: string, name: string, count: int|null}>|null null se WordPress non ha risposto.
     */
    public function fetchAndCache(Settings $settings): ?array
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
            ->map(fn (array $c) => [
                'id' => (string) $c['id'],
                // la REST API restituisce il nome con entità HTML (es. "Eventi &amp; News")
                'name' => html_entity_decode($c['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'count' => $c['count'] ?? null,
            ])
            ->values()
            ->all();

        $settings->update(['wordpress_options' => ['categories' => $categories]]);

        return $categories;
    }
}
