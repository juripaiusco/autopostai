<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    /**
     * @return array{0: array<int, array{id: string, name: string}>, 1: string|null} [liste, messaggio di errore]
     */
    private function fetchAndCacheLists(?Settings $settings): array
    {
        if (!empty($settings?->nl_mailchimp_api)) {
            return $this->fetchMailchimpLists($settings);
        }

        if (!empty($settings?->nl_brevo_api)) {
            return $this->fetchBrevoLists($settings);
        }

        return [[], 'Salva prima la API Key di MailChimp o Brevo per questo account.'];
    }

    private function fetchMailchimpLists(Settings $settings): array
    {
        if (empty($settings->nl_mailchimp_datacenter)) {
            return [[], 'Configura prima il Server prefix MailChimp.'];
        }

        $response = Http::withBasicAuth('anystring', $settings->nl_mailchimp_api)
            ->get("https://{$settings->nl_mailchimp_datacenter}.api.mailchimp.com/3.0/lists", ['count' => 100]);

        if (!$response->successful()) {
            Log::warning('MailChimp: recupero liste fallito', [
                'settings_id' => $settings->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [[], 'MailChimp non ha risposto correttamente. Controlla API Key e Server prefix.'];
        }

        $lists = collect($response->json('lists', []))
            ->map(fn (array $l) => ['id' => $l['id'], 'name' => $l['name']])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['nl_mailchimp_options' => ['lists' => $lists]]);

        return [$lists, null];
    }

    /**
     * Brevo accetta al massimo limit=50 per pagina (oltre risponde 400):
     * si pagina con offset finché non si arriva al totale (`count`).
     */
    private function fetchBrevoLists(Settings $settings): array
    {
        $perPage = 50;
        $raw = [];
        $offset = 0;

        do {
            $response = Http::withHeaders(['api-key' => $settings->nl_brevo_api, 'accept' => 'application/json'])
                ->get('https://api.brevo.com/v3/contacts/lists', ['limit' => $perPage, 'offset' => $offset]);

            if (!$response->successful()) {
                Log::warning('Brevo: recupero liste fallito', [
                    'settings_id' => $settings->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [[], $response->status() === 401
                    ? 'Brevo ha rifiutato la API Key. Controllala e salva di nuovo.'
                    : 'Brevo non ha risposto correttamente (HTTP ' . $response->status() . ').'];
            }

            $page = $response->json('lists', []);
            $raw = array_merge($raw, $page);
            $offset += $perPage;
        } while (count($page) === $perPage && $offset < (int) $response->json('count', 0));

        $lists = collect($raw)
            ->map(fn (array $l) => ['id' => (string) $l['id'], 'name' => $l['name']])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['nl_brevo_options' => ['lists' => $lists]]);

        return [$lists, null];
    }
}
