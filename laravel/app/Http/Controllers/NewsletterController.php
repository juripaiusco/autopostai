<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NewsletterController extends Controller
{
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
        abort_if(empty($settings?->mailchimp_api) && empty($settings?->brevo_api), 422, 'Configura prima MailChimp o Brevo per questo account.');

        [$lists, $error] = $this->fetchAndCacheLists($settings);

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

        [$lists, $error] = $this->fetchAndCacheLists($user->settings);

        abort_if($error !== null, 422, $error ?? 'Configura prima MailChimp o Brevo per questo account.');

        return response()->json([
            'provider' => $this->providerOf($user->settings),
            'lists' => $lists,
        ]);
    }

    private function providerOf(?Settings $settings): ?string
    {
        if (!empty($settings?->mailchimp_api)) {
            return 'mailchimp';
        }
        if (!empty($settings?->brevo_api)) {
            return 'brevo';
        }
        return null;
    }

    /**
     * @return array{0: array<int, array{id: string, name: string}>, 1: string|null} [liste, messaggio di errore]
     */
    private function fetchAndCacheLists(?Settings $settings): array
    {
        if (!empty($settings?->mailchimp_api)) {
            return $this->fetchMailchimpLists($settings);
        }

        if (!empty($settings?->brevo_api)) {
            return $this->fetchBrevoLists($settings);
        }

        return [[], 'Configura prima MailChimp o Brevo per questo account.'];
    }

    private function fetchMailchimpLists(Settings $settings): array
    {
        if (empty($settings->mailchimp_datacenter)) {
            return [[], 'Configura prima il Server prefix MailChimp.'];
        }

        $response = Http::withBasicAuth('anystring', $settings->mailchimp_api)
            ->get("https://{$settings->mailchimp_datacenter}.api.mailchimp.com/3.0/lists", ['count' => 100]);

        if (!$response->successful()) {
            return [[], 'MailChimp non ha risposto correttamente. Controlla API Key e Server prefix.'];
        }

        $lists = collect($response->json('lists', []))
            ->map(fn (array $l) => ['id' => $l['id'], 'name' => $l['name']])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['mailchimp_options' => ['lists' => $lists]]);

        return [$lists, null];
    }

    private function fetchBrevoLists(Settings $settings): array
    {
        $response = Http::withHeaders(['api-key' => $settings->brevo_api, 'accept' => 'application/json'])
            ->get('https://api.brevo.com/v3/contacts/lists', ['limit' => 100]);

        if (!$response->successful()) {
            return [[], 'Brevo non ha risposto correttamente. Controlla la API Key.'];
        }

        $lists = collect($response->json('lists', []))
            ->map(fn (array $l) => ['id' => (string) $l['id'], 'name' => $l['name']])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['brevo_options' => ['lists' => $lists]]);

        return [$lists, null];
    }
}
