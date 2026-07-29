<?php

namespace App\Http\Middleware;

use App\Models\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Autenticazione server-to-server via header X-Api-Key: nessuna
     * sessione/cookie, solo lookup dell'hash (stesso principio di Sanctum).
     * Nessun controllo aggiuntivo su smtp_custom qui — la chiave esiste solo
     * se generata da un'area già gated, l'auth resta semplice e prevedibile.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Api-Key');

        abort_if(empty($key), 401, 'API key mancante.');

        $settings = Settings::where('contacts_api_key_hash', hash('sha256', $key))->first();

        abort_if(!$settings, 401, 'API key non valida.');

        $request->attributes->set('contactsAccount', $settings->user);

        return $next($request);
    }
}
