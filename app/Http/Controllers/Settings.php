<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            'data' => $data
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $settings = \App\Models\Settings::find($id);
        $settings->fill($request->all());
        $settings->save();
    }
}
