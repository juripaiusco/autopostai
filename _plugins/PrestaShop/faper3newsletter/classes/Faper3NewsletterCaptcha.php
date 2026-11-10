<?php
/**
 * Verifica server-side del captcha: Google reCAPTCHA v3 o Cloudflare Turnstile.
 *
 * Compatibile PHP 7.1+ (PrestaShop 1.7).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Faper3NewsletterCaptcha
{
    const NONE = 'none';
    const RECAPTCHA_V3 = 'recaptcha_v3';
    const TURNSTILE = 'turnstile';

    /** Azione dichiarata lato JS e verificata qui (evita token presi da altri form). */
    const ACTION = 'newsletter';

    const RECAPTCHA_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** @var string */
    private $provider;

    /** @var string */
    private $secret;

    /** @var float */
    private $minScore;

    /**
     * @param string $provider none|recaptcha_v3|turnstile
     * @param string $secret secret key del provider
     * @param float $minScore soglia reCAPTCHA v3 (0..1)
     */
    public function __construct($provider, $secret, $minScore = 0.5)
    {
        $this->provider = (string) $provider;
        $this->secret = (string) $secret;
        $this->minScore = (float) $minScore;
    }

    /**
     * @return string[]
     */
    public static function providers()
    {
        return [self::NONE, self::RECAPTCHA_V3, self::TURNSTILE];
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->provider === self::RECAPTCHA_V3 || $this->provider === self::TURNSTILE;
    }

    /**
     * @param string $token token prodotto dal widget lato browser
     * @param string $remoteIp IP del visitatore
     *
     * @return bool
     */
    public function verify($token, $remoteIp)
    {
        if (!$this->isEnabled()) {
            return true;
        }
        if ((string) $token === '' || $this->secret === '') {
            return false;
        }

        $url = $this->provider === self::RECAPTCHA_V3 ? self::RECAPTCHA_URL : self::TURNSTILE_URL;
        $result = $this->post($url, [
            'secret' => $this->secret,
            'response' => (string) $token,
            'remoteip' => (string) $remoteIp,
        ]);

        if (!is_array($result) || empty($result['success'])) {
            return false;
        }
        // Turnstile restituisce action solo se impostata sul widget: la impostiamo sempre.
        if (isset($result['action']) && $result['action'] !== self::ACTION) {
            return false;
        }
        if ($this->provider === self::RECAPTCHA_V3) {
            return isset($result['score']) && (float) $result['score'] >= $this->minScore;
        }

        return true;
    }

    /**
     * @param string $url
     * @param array $fields
     *
     * @return array|null
     */
    protected function post($url, array $fields)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $decoded = is_string($body) ? json_decode($body, true) : null;

        return is_array($decoded) ? $decoded : null;
    }
}
