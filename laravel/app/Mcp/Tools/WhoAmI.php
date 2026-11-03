<?php

namespace App\Mcp\Tools;

use App\Models\McpToken;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Title('Chi sono')]
#[Description('Utente collegato, ruolo, permessi del token e account su cui può agire (id da usare come account_id negli altri tool). Chiamalo per primo.')]
#[IsReadOnly]
class WhoAmI extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $me = $this->me();
        $token = $me->currentMcpToken();

        return Response::structured([
            'user' => [
                'id' => $me->id,
                'name' => $me->name,
                'email' => $me->email,
                'role' => $me->isAdmin() ? 'amministratore' : ($me->isManager() ? 'manager' : 'utente'),
                'active_channels' => collect($me->channelsMeta())->filter(fn ($c) => $c['available'])->keys()->values()->all(),
            ],
            'token' => [
                'name' => $token?->name,
                'abilities' => collect($token?->abilities ?? [])->mapWithKeys(fn ($a) => [$a => McpToken::ABILITIES[$a] ?? $a])->all(),
                'expires_at' => $token?->expires_at?->toIso8601String(),
            ],
            'managed_accounts' => $me->filterableUsers()->all(),
            'app_url' => url('/'),
        ]);
    }
}
