<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\SuppressionList;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    /**
     * Link pubblico firmato (route "signed:relative"), nessun login: la firma
     * è l'autorizzazione. Il GET mostra solo la conferma: prima disiscriveva
     * subito, e gli scanner antiphishing che aprono in automatico i link delle
     * email (Outlook Safe Links, gateway aziendali) disiscrivevano contatti
     * che non avevano cliccato nulla.
     */
    public function show(Request $request, Contact $contact): View
    {
        return view('unsubscribe', [
            'email' => $contact->email,
            'done' => $contact->status === 'unsubscribed',
            // Relativo (path + firma): niente http/https o host sbagliati
            // dietro un proxy.
            'action' => $request->getRequestUri(),
        ]);
    }

    /**
     * Conferma dalla pagina, oppure one-click (RFC 8058) dal client di posta
     * tramite l'header List-Unsubscribe-Post: stesso URL firmato, POST senza
     * token CSRF (escluso in bootstrap/app.php). Idempotente.
     */
    public function unsubscribe(Contact $contact): View
    {
        $contact->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        SuppressionList::updateOrCreate(
            ['user_id' => $contact->user_id, 'email' => $contact->email],
            ['reason' => 'unsubscribe']
        );

        return view('unsubscribe', ['email' => $contact->email, 'done' => true, 'action' => null]);
    }
}
