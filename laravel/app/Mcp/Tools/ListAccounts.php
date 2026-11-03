<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_accounts')]
#[Title('Account e consumi')]
#[Description('Account visibili (il proprio, più quelli gestiti per admin/manager): canali attivi, provider newsletter, consumo token AI del mese e immagini AI di oggi rispetto ai limiti. Sola lettura: chiavi e password non vengono mai mostrate.')]
#[IsReadOnly]
class ListAccounts extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $me = $this->me();
        $ids = $me->filterableUsers()->pluck('id')->push($me->id);

        $accounts = User::query()
            ->whereIn('id', $ids)
            ->with('settings')
            ->withSum(['tokensUsed as tokens_used_sum'], 'tokens_used')
            ->withCount('imagesUsed')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->isAdmin() ? 'amministratore' : ($u->isManager() ? 'manager' : 'utente'),
                'is_me' => $u->id === $me->id,
                'channels' => collect($u->channelsMeta())
                    ->map(fn ($c) => ['active' => $c['available'], 'auto_reply_allowed' => $c['replyOn'], 'provider' => $c['provider']])
                    ->all(),
                'openai_configured' => !empty($u->settings?->openai_api_key),
                'usage' => [
                    'ai_tokens_this_month' => (int) ($u->tokens_used_sum ?? 0),
                    'ai_tokens_monthly_limit' => (int) $u->tokens_limit,
                    'ai_images_today' => $u->images_used_count,
                    'ai_images_daily_limit' => (int) $u->image_model_limit,
                ],
            ])
            ->values()
            ->all();

        return Response::structured(['accounts' => $accounts]);
    }
}
