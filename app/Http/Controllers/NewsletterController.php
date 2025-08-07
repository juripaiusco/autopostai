<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dotenv\Dotenv;

class NewsletterController extends Controller
{
    /**
     * Recupera le liste di newsletter per un utente specifico
     *
     * @param int $userId
     * @return array|bool
     */
    function lists_get($userId)
    {
        $settings = \App\Models\Settings::where('user_id', $userId)->first();

        if ($settings->mailchimp_api) {

        }

        if ($settings->brevo_api) {
            return $this->brevo_lists_get($settings);
        }
    }

    public function brevo_lists_get($settings)
    {
        $this->loadCustomEnv(base_path('./docker/autopostai/.env'));

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => $settings->brevo_api,
        ])->get(env('BREVO_BASE_URL') . '/contacts/lists');

        if ($response->successful()) {
            $lists = $response->json();
        } else {
            $lists = false; // fallback o gestione errore
        }

        if ($lists) {
            $lists['channel'] = 'brevo';

            foreach ($lists['lists'] as $k => $list) {
                $lists['lists'][$k]['on'] = 0;
            }

            usort($lists['lists'], function ($a, $b) {
                return strcmp(strtolower($a['name']), strtolower($b['name']));
            });

            $settings->brevo_options = array_merge(
                json_decode($settings->brevo_options, true) ?? [],
                array('lists' => $lists)
            );

            $settings->save();
        }

        return $settings->brevo_options;
    }

    function loadCustomEnv($pathToEnvFile)
    {
        if (!file_exists($pathToEnvFile)) {
            throw new \Exception("Il file .env personalizzato non esiste: $pathToEnvFile");
        }

        $dotenv = Dotenv::createImmutable(dirname($pathToEnvFile), basename($pathToEnvFile));
        $dotenv->load();
    }
}
