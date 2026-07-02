<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScopeController extends Controller
{
    /**
     * Imposta/rimuove lo scope globale "filtra per utente" in sessione,
     * così persiste attraverso la normale navigazione (link della sidebar)
     * finché non viene disattivato o cambiato esplicitamente.
     */
    public function update(Request $request): RedirectResponse
    {
        $resolved = $request->user()->resolveScopedUser($request->integer('user') ?: null);

        if ($resolved === null) {
            $request->session()->forget('scoped_user_id');
        } else {
            $request->session()->put('scoped_user_id', $resolved['id']);
        }

        return back();
    }
}
