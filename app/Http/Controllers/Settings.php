<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class Settings extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = \App\Models\Settings::firstOrCreate(
            ['user_id' => auth()->id()]
        );

        return Inertia::render('Settings/Form', [
            'data' => $data,
            'success' => Session::get('success')
        ]);
    }

    public function store(Request $request, string $user_id)
    {
        $request['user_id'] = $user_id;

        // In questo caso utilizzo $settings->fill($request->all()) perché
        // nella crezione del nuovo utente l'array che viene restituito è
        // un array piatto
        $settings = new \App\Models\Settings();
        $settings->fill($request->all());
        $settings->save();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $user_id)
    {
        // In questo caso utilizzo $settings->fill($request->input('settings')) perché
        // nell'aggiornamento dell'utente l'array che viene restituito è un array nidificato
        $settings = \App\Models\Settings::where('user_id', $user_id)->first();
        $settings->fill($request->input('settings'));
        $settings->save();
    }

    public function update_by_user(Request $request, string $id)
    {
        $settings = \App\Models\Settings::find($id);
        $settings->fill($request->all());
        $settings->save();

        // Usa Inertia per reindirizzare e passare il messaggio
        return redirect()
            ->back()
            ->with('success', [
                'time' => time(),
                'msg' => 'Impostazioni salvate con successo.'
            ]); // Messaggio di successo
    }
}
