<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class McpAuditLog
{
    /**
     * Una riga per chiamata MCP in storage/logs/mcp-*.log: chi (utente,
     * token), cosa (metodo JSON-RPC e tool) ed esito. Mai i valori degli
     * argomenti: possono contenere testi dei post o dati dei contatti.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);

        $response = $next($request);

        $body = json_decode($request->getContent(), true);
        $user = $request->user();
        $result = json_decode((string) $response->getContent(), true);

        Log::channel('mcp')->info('mcp', [
            'user_id' => $user?->id,
            'token_id' => $user?->currentMcpToken()?->id,
            'ip' => $request->ip(),
            'method' => is_array($body) ? ($body['method'] ?? null) : null,
            'tool' => is_array($body) ? ($body['params']['name'] ?? null) : null,
            'status' => $response->getStatusCode(),
            'tool_error' => is_array($result) ? (bool) ($result['result']['isError'] ?? false) : null,
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $response;
    }
}
