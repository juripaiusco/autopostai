<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LinkedInController extends Controller
{
    /**
     * Scope voluto dal cliente: piu' account condividono la stessa app
     * LinkedIn (client_id/secret) per non dover ri-autorizzare il token
     * account per account — quando si aggiorna, si propaga a tutti quelli
     * collegati alla stessa app (vedi callback()). Qui sotto sistemiamo solo
     * i due bug reali della v1: nessun controllo di chi puo' avviare il
     * collegamento per un account, e "state" generato ma mai verificato.
     */
    public function redirect(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $settings = $user->settings;
        abort_if(
            !$settings?->linkedin_client_id || !$settings?->linkedin_client_secret,
            422,
            'Configura prima Client ID e Client Secret LinkedIn per questo account.'
        );

        $state = Str::random(40);
        $request->session()->put('linkedin_oauth', [
            'state' => $state,
            'user_id' => $user->id,
        ]);

        $scope = implode(' ', [
            'openid',
            'profile',
            'r_ads_reporting',
            'r_organization_social',
            'rw_organization_admin',
            'w_member_social',
            'r_ads',
            'w_organization_social',
            'rw_ads',
            'r_basicprofile',
            'r_organization_admin',
            'email',
            'r_1st_connections_size',
        ]);

        return redirect('https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $settings->linkedin_client_id,
            'redirect_uri' => route('linkedin.callback'),
            'scope' => $scope,
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $oauth = $request->session()->pull('linkedin_oauth');
        $backTo = $oauth ? route('account.edit', $oauth['user_id']) : route('account');

        if (!$oauth || $request->query('state') !== $oauth['state']) {
            return redirect($backTo)->with('toast', 'Sessione di autorizzazione LinkedIn non valida o scaduta. Riprova.');
        }

        $user = User::findOrFail($oauth['user_id']);
        $this->authorize('update', $user);

        if ($request->query('error')) {
            return redirect($backTo)->with('toast', 'Autorizzazione LinkedIn annullata.');
        }

        $settings = $user->settings;

        $tokenResponse = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $request->query('code'),
            'redirect_uri' => route('linkedin.callback'),
            'client_id' => $settings->linkedin_client_id,
            'client_secret' => $settings->linkedin_client_secret,
        ]);

        if (!$tokenResponse->successful()) {
            return redirect($backTo)->with('toast', 'LinkedIn non ha confermato l\'autorizzazione. Riprova.');
        }

        // Propagazione voluta: stessa app (client_id + secret) => stesso token
        // per tutti gli account collegati, cosi' un solo refresh vale per tutti.
        Settings::where('linkedin_client_id', $settings->linkedin_client_id)
            ->where('linkedin_client_secret', $settings->linkedin_client_secret)
            ->update([
                'linkedin_token' => $tokenResponse->json('access_token'),
                'linkedin_token_expires_at' => now()->addSeconds((int) $tokenResponse->json('expires_in', 0)),
            ]);

        return redirect($backTo)->with('toast', 'LinkedIn collegato.');
    }
}
