<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use function Laravel\Prompts\select;

class LinkedInController extends Controller
{
    public function redirect($linkedin_client_id)
    {
        $linkedin_data = \App\Models\Settings::select([
            'linkedin_client_id',
            'linkedin_client_secret',
        ])->where('linkedin_client_id', $linkedin_client_id)->first();

        Session::put('linkedin_client_id', $linkedin_data->linkedin_client_id);
        Session::put('linkedin_client_secret', $linkedin_data->linkedin_client_secret);

        $redirectUri = route('linkedin.callback');
        $scope = 'w_member_social w_organization_social r_basicprofile';

        return redirect("https://www.linkedin.com/oauth/v2/authorization?" . http_build_query([
                'response_type' => 'code',
                'client_id' => $linkedin_client_id,
                'redirect_uri' => $redirectUri,
                'scope' => $scope,
                'state' => Str::random(40), // opzionale
            ]));
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return 'Errore: ' . $request->get('error_description');
        }

        if (!$request->has('code')) {
            return 'Nessun codice fornito';
        }

        $code = $request->get('code');

        // Ora chiediamo il token
        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => route('linkedin.callback'), // deve combaciare!
            'client_id' => Session::get('linkedin_client_id'),
            'client_secret' => Session::get('linkedin_client_secret'),
        ]);

        if (!$response->successful()) {
            return 'Errore nella richiesta token: ' . $response->body();
        }

        $token = $response->json()['access_token'];

        // Salva il token nel database
        $linkedin_data = \App\Models\Settings::where('linkedin_client_id', Session::get('linkedin_client_id'))
            ->first();
        $linkedin_data->linkedin_token = $token;
        $linkedin_data->save();

        Session::forget('linkedin_client_id');
        Session::forget('linkedin_client_secret');

        return 'Access Token ottenuto!';
    }
}
