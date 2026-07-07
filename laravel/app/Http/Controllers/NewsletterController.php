<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NewsletterController extends Controller
{
    /**
     * Liste dell'account newsletter configurato (solo uno tra MailChimp e
     * Brevo, chi ha una API key impostata vince — stessa regola di v1),
     * per sceglierle da un elenco invece di un ID scritto a mano.
     */
    public function fetchLists(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $settings = $user->settings;

        if (!empty($settings?->mailchimp_api)) {
            return $this->fetchMailchimpLists($settings);
        }

        if (!empty($settings?->brevo_api)) {
            return $this->fetchBrevoLists($settings);
        }

        abort(422, 'Configura prima MailChimp o Brevo per questo account.');
    }

    private function fetchMailchimpLists(Settings $settings): RedirectResponse
    {
        abort_if(empty($settings->mailchimp_datacenter), 422, 'Configura prima il Server prefix MailChimp.');

        $response = Http::withBasicAuth('anystring', $settings->mailchimp_api)
            ->get("https://{$settings->mailchimp_datacenter}.api.mailchimp.com/3.0/lists", ['count' => 100]);

        if (!$response->successful()) {
            return back()->with('toast', 'MailChimp non ha risposto correttamente. Controlla API Key e Server prefix.');
        }

        $lists = collect($response->json('lists', []))
            ->map(fn (array $l) => ['id' => $l['id'], 'name' => $l['name']])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['mailchimp_options' => ['lists' => $lists]]);

        return back()->with('toast', count($lists) . ' liste caricate da MailChimp.');
    }

    private function fetchBrevoLists(Settings $settings): RedirectResponse
    {
        $response = Http::withHeaders(['api-key' => $settings->brevo_api, 'accept' => 'application/json'])
            ->get('https://api.brevo.com/v3/contacts/lists', ['limit' => 100]);

        if (!$response->successful()) {
            return back()->with('toast', 'Brevo non ha risposto correttamente. Controlla la API Key.');
        }

        $lists = collect($response->json('lists', []))
            ->map(fn (array $l) => ['id' => (string) $l['id'], 'name' => $l['name']])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['brevo_options' => ['lists' => $lists]]);

        return back()->with('toast', count($lists) . ' liste caricate da Brevo.');
    }
}
