<?php
/**
 * Client dell'API contatti FaPer3: POST {baseUrl}/api/contacts con header X-Api-Key.
 * Doc endpoint: docs/api-contacts-registration.md (repo FaPer3).
 *
 * Compatibile PHP 7.1+ (PrestaShop 1.7).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Faper3NewsletterClient
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /** @var int */
    private $timeout;

    /** @var string */
    private $userAgent;

    /**
     * @param string $baseUrl es. https://faper3.it
     * @param string $apiKey chiave "API contatti" dell'account FaPer3
     * @param int $timeout secondi totali della richiesta
     * @param string $userAgent
     */
    public function __construct($baseUrl, $apiKey, $timeout = 10, $userAgent = 'faper3newsletter-prestashop')
    {
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->apiKey = (string) $apiKey;
        $this->timeout = (int) $timeout;
        $this->userAgent = (string) $userAgent;
    }

    /**
     * Registra (o aggiorna, idempotente) un contatto.
     *
     * @param array $contact email, first_name, last_name, tags[], consent_source, consent_ip
     *
     * @return array ['code' => int (0 = errore di rete), 'body' => array|null, 'error' => string|null]
     */
    public function registerContact(array $contact)
    {
        if (!function_exists('curl_init')) {
            return ['code' => 0, 'body' => null, 'error' => 'Estensione PHP cURL non disponibile.'];
        }

        $ch = curl_init($this->baseUrl . '/api/contacts');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(self::cleanPayload($contact)),
        ]);

        $body = curl_exec($ch);
        $error = $body === false ? curl_error($ch) : null;
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($body) ? json_decode($body, true) : null;

        return [
            'code' => $error === null ? $code : 0,
            'body' => is_array($decoded) ? $decoded : null,
            'error' => $error,
        ];
    }

    /**
     * @param int $code
     *
     * @return bool
     */
    public static function isSuccess($code)
    {
        return $code >= 200 && $code < 300;
    }

    /**
     * Toglie campi vuoti (l'API li tratterebbe comunque come "non inviati").
     *
     * @param array $contact
     *
     * @return array
     */
    public static function cleanPayload(array $contact)
    {
        $clean = [];
        foreach ($contact as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * "prestashop, clienti" → ['prestashop', 'clienti'] (max 20, come l'API).
     *
     * @param string $csv
     *
     * @return array
     */
    public static function parseTags($csv)
    {
        $tags = [];
        foreach (explode(',', (string) $csv) as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return array_slice($tags, 0, 20);
    }
}
