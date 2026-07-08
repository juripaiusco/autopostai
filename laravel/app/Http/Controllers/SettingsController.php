<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Pagina self-service: chiunque modifica le proprie credenziali di
     * accesso. Il tab "Profilo AI" e' riservato al simple user — admin e
     * manager gestiscono, non pubblicano, quindi non hanno un profilo AI
     * proprio (le loro credenziali di integrazione restano in Account/Form,
     * gestite da chi li amministra).
     */
    public function edit(Request $request): Response
    {
        $me = $request->user();
        $isSimpleUser = !$me->isAdmin() && !$me->isManager();
        $s = $me->settings;

        return Inertia::render('Settings', [
            'account' => ['name' => $me->name, 'email' => $me->email],
            'isSimpleUser' => $isSimpleUser,
            'ai' => $isSimpleUser ? [
                'profile' => $s->ai_personality ?? '',
                'knows' => $s->ai_prompt_prefix ?? '',
                'commentStyle' => $s->ai_comment_prefix ?? '',
            ] : null,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$me->id}"],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $me->name = $data['name'];
        $me->email = $data['email'];
        if (!empty($data['password'])) {
            $me->password = bcrypt($data['password']);
        }
        $me->save();

        // Il profilo AI e' un campo riservato al simple user: qualunque cosa
        // arrivi nel payload di admin/manager viene ignorata, non solo nascosta lato UI.
        if (!$me->isAdmin() && !$me->isManager()) {
            $ai = $request->input('ai', []);
            Settings::updateOrCreate(
                ['user_id' => $me->id],
                [
                    'ai_personality' => $ai['profile'] ?? null,
                    'ai_prompt_prefix' => $ai['knows'] ?? null,
                    'ai_comment_prefix' => $ai['commentStyle'] ?? null,
                ]
            );
        }

        $request->session()->flash('toast', 'Impostazioni salvate.');

        return back();
    }
}
