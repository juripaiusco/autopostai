<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WordPressController extends Controller
{
    /**
     * Recupera le categorie da WordPress per un utente specifico
     *
     * @param int $userId
     * @return array|bool
     */
    function categories_get($userId)
    {
        $settings = \App\Models\Settings::where('user_id', $userId)->first();

        $response = Http::get($settings->wordpress_url . '/wp-json/wp/v2/categories', [
            'per_page' => 100,
            'orderby' => 'name',
            'order' => 'asc',
        ]);

        if ($response->successful()) {
            $categories = $response->json();
        } else {
            $categories = false; // fallback o gestione errore
        }

        if ($categories) {

            foreach ($categories as $k => $category) {
                $categories[$k]['on'] = 0;
            }

            $settings->wordpress_options = array_merge(
                json_decode($settings->wordpress_options, true) ?? [],
                array('categories' => $categories)
            );
            $settings->save();
        }

        return $settings->wordpress_options;
    }
}
