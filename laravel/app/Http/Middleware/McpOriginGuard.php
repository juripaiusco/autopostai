<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class McpOriginGuard
{
    /**
     * Protezione DNS rebinding richiesta dalla specifica MCP: i client nativi
     * (Claude Code, Cursor, Claude Desktop) non mandano l'header Origin, un
     * browser sì. Se c'è, deve essere l'app stessa o un'origine esplicitamente
     * consentita (MCP_ALLOWED_ORIGINS, separate da virgola).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && !in_array(rtrim($origin, '/'), $this->allowedOrigins(), true)) {
            return response()->json(['message' => 'Origine non consentita.'], 403);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function allowedOrigins(): array
    {
        $url = parse_url((string) config('app.url'));
        $appOrigin = isset($url['scheme'], $url['host'])
            ? $url['scheme'].'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '')
            : null;

        return collect(config('services.mcp.allowed_origins', []))
            ->push($appOrigin)
            ->map(fn ($o) => rtrim(trim((string) $o), '/'))
            ->filter()
            ->values()
            ->all();
    }
}
