<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\SuppressionList;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    /**
     * Link pubblico firmato (route "signed"), un click, nessun login: la
     * firma stessa è l'autorizzazione. Idempotente — ricliccare un link già
     * usato non fa che ri-confermare lo stesso stato.
     */
    public function show(Contact $contact): View
    {
        $contact->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        SuppressionList::updateOrCreate(
            ['user_id' => $contact->user_id, 'email' => $contact->email],
            ['reason' => 'unsubscribe']
        );

        return view('unsubscribe', ['email' => $contact->email]);
    }
}
