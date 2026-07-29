<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMxRecord implements ValidationRule
{
    /**
     * Verifica il record MX del dominio via checkdnsrr() diretto: la rule
     * builtin di Laravel (Email::validateMxRecord()) richiede l'estensione
     * PHP intl (assente in questa immagine Docker) e fallisce sempre, anche
     * per domini validi — questa non ne dipende.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = substr((string) $value, strrpos((string) $value, '@') + 1);

        if ($domain === '' || !checkdnsrr($domain, 'MX')) {
            $fail('Il dominio dell\'indirizzo email non ha un record MX valido.');
        }
    }
}
