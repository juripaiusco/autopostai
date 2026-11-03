<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Liste dell'account newsletter configurato (solo uno tra MailChimp e Brevo,
 * chi ha una API key impostata vince — stessa regola di v1), salvate in
 * settings come cache per i selettori. Usato da NewsletterController (pagina
 * Account e form post) e dal tool MCP refresh_channel_options.
 */
class NewsletterLists
{
    /**
     * `count` = iscritti al momento del caricamento (fotografia, può andare fuori sync).
     *
     * @return array{0: array<int, array{id: string, name: string, count: int|null}>, 1: string|null} [liste, messaggio di errore]
     */
    public function fetchAndCache(?Settings $settings): array
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
            ->map(fn (array $l) => [
                'id' => $l['id'],
                'name' => $l['name'],
                'count' => $l['stats']['member_count'] ?? null,
            ])
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

                return [[], $this->brevoErrorMessage($response)];
            }

            $page = $response->json('lists', []);
            $raw = array_merge($raw, $page);
            $offset += $perPage;
        } while (count($page) === $perPage && $offset < (int) $response->json('count', 0));

        $lists = collect($raw)
            ->map(fn (array $l) => [
                'id' => (string) $l['id'],
                'name' => $l['name'],
                'count' => $l['uniqueSubscribers'] ?? $l['totalSubscribers'] ?? null,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        $settings->update(['nl_brevo_options' => ['lists' => $lists]]);

        return [$lists, null];
    }

    /**
     * Brevo risponde 401 sia per API key errata sia per IP del server non
     * tra gli "IP autorizzati" dell'account ("unrecognised IP address x.x.x.x"):
     * vanno distinti, altrimenti si cerca un problema di chiave che non c'è.
     */
    private function brevoErrorMessage(Response $response): string
    {
        if ($response->status() !== 401) {
            return 'Brevo non ha risposto correttamente (HTTP ' . $response->status() . ').';
        }

        $message = (string) $response->json('message', '');

        if (stripos($message, 'IP address') === false) {
            return 'Brevo ha rifiutato la API Key. Controllala e salva di nuovo.';
        }

        $ip = preg_match('/IP address\s+([0-9a-f.:]*[0-9a-f])/i', $message, $m) ? " ({$m[1]})" : '';

        return "Brevo ha bloccato la richiesta: l'IP del server{$ip} non è tra gli IP autorizzati. "
            . 'Aggiungilo in Brevo > Sicurezza > IP autorizzati.';
    }
}
