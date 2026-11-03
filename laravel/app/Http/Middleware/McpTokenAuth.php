<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpTokenAuth
{
    /**
     * Autenticazione del server MCP: header "Authorization: Bearer fp3_…",
     * lookup dell'hash come ApiKeyAuth, nessuna sessione/cookie. Token
     * scaduto, revocato o sconosciuto → 401 (AddWwwAuthenticateHeader di
     * laravel/mcp aggiunge l'header WWW-Authenticate).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken();
        $token = $plaintext ? McpToken::findActiveByPlaintext($plaintext) : null;

        if (!$token || !$token->user) {
            // Tentativi falliti nel log MCP (McpAuditLog gira solo dopo l'auth).
            Log::channel('mcp')->warning('mcp auth failed', [
                'ip' => $request->ip(),
                'token_given' => $plaintext !== null,
            ]);

            return response()->json(['message' => 'Token MCP mancante, scaduto o revocato.'], 401);
        }

        // Una scrittura al minuto basta per "ultimo utilizzo": un LLM fa
        // decine di chiamate di fila.
        if (!$token->last_used_at || $token->last_used_at->lt(now()->subMinute()) || $token->last_used_ip !== $request->ip()) {
            $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        Auth::setUser($token->user->withMcpToken($token));

        return $next($request);
    }
}
