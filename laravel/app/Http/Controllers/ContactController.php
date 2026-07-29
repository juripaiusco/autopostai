<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Stesso controllo di User::canViewContacts() usato per la voce sidebar,
     * ripetuto qui lato server per non essere raggiungibile via URL diretto
     * quando nascosto in UI: ruolo admin/manager (come Account) + se c'è uno
     * scope attivo su un utente specifico, quell'utente deve avere smtp_custom
     * attivo (con Mailchimp/Brevo i contatti interni non hanno senso).
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $scopedUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        abort_unless($me->canViewContacts($scopedUserId), 403);

        return Inertia::render('Contacts/Index');
    }
}
