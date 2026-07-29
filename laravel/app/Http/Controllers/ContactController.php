<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Modulo visibile solo per gli account con provider newsletter smtp_custom
     * attivo — stesso controllo usato per la voce sidebar (contactsEnabled in
     * HandleInertiaRequests), ripetuto qui lato server per non essere
     * raggiungibile via URL diretto quando nascosto in UI.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $scopedUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;
        $contactsUser = $scopedUserId ? User::find($scopedUserId) : $me;

        abort_unless($contactsUser?->hasSmtpCustomActive(), 403);

        return Inertia::render('Contacts/Index');
    }
}
