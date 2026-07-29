<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactsApiKeyController extends Controller
{
    /**
     * Genera/rigenera la API key di registrazione contatti (Step 5) per
     * $user. Stessa autorizzazione di AccountController::update. Il
     * plaintext si vede una sola volta: flash di un solo giro, stesso
     * pattern di LinkedInController::callback() per linkedin_pages —
     * AccountController::edit() lo consuma e lo passa alla view.
     */
    public function regenerate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $settings = Settings::firstOrCreate(['user_id' => $user->id]);
        $plaintext = $settings->generateContactsApiKey();

        return back()
            ->with('contacts_api_key_plaintext', $plaintext)
            ->with('toast', 'Chiave API generata');
    }
}
