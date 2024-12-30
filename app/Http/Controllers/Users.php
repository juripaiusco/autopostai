<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class Users extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $request_search_array = [
            'name',
            'email',
            'child_on'
        ];

        $request_validate_array = $request_search_array;

        // Query data
        $data = \App\Models\User::query();
//        $data = $data->with('posts');

        // Request validate
        request()->validate([
            'orderby' => ['in:' . implode(',', $request_validate_array)],
            'ordertype' => ['in:asc,desc']
        ]);

        // Filtro RICERCA
        if (request('s')) {
            $data->where(function ($q) use ($request_search_array) {

                foreach ($request_search_array as $field) {
                    $q->orWhere('users.' . $field, 'like', '%' . request('s') . '%');
                }

            });
        }

        // Filtro ORDINAMENTO
        if (request('orderby') && request('ordertype')) {
            $data->orderby(request('orderby'), strtoupper(request('ordertype')));
        }

        $data = $data->select([
            'users.id',
            'users.name',
            'users.email',
            'users.child_on',
        ]);

        if (auth()->user()->parent_id == null) {
            $data = $data->whereNotNull('users.parent_id');
        } else {
            $data = $data->where('parent_id', auth()->id());
        }

        $data = $data->paginate(env('VIEWS_PAGINATE'))->withQueryString();

        return Inertia::render('Users/List', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Creo un oggetto di dati vuoto
        $columns_users = Schema::getColumnListing('users');
        $columns_settings = Schema::getColumnListing('settings');

        $columns = array_merge($columns_users, $columns_settings);

        $data = array();
        foreach ($columns as $field) {
            $data[$field] = '';
        }

        unset($data['id']);
        unset($data['deleted_at']);
        unset($data['created_at']);
        unset($data['updated_at']);

        $data['saveRedirect'] = Redirect::back()->getTargetUrl();

        $data = json_decode(json_encode($data), true);

        return Inertia::render('Users/Form', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = User::with('children')->where('id', auth()->id())->first();

        if ($user->parent_id && $user->child_on == 1 && $user->child_max <= $user->children()->count()) {

            return redirect()->back()->withErrors([
                'message' => 'Hai raggiunto il numero massimo di utenti, non puoi crearne altri',
            ]);
        }

        $request->validate([
            'name'      => ['required'],
            'email'     => ['required'],
        ]);

        $saveRedirect = $request['saveRedirect'];
        unset($request['saveRedirect']);

        $request['parent_id'] = auth()->user()->id;
        $request['password'] = Hash::make($request['password']);

        // Salvo il nuovo utente
        $user = new \App\Models\User();
        $user->fill($request->all());
        $user->save();

        // Salvo le impostazioni del nuovo utente
        $settings = new Settings();
        $settings->store($request, $user->id);

        return Redirect::to($saveRedirect);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = \App\Models\User::with('parent', 'settings')->find($id);

        $data['saveRedirect'] = Redirect::back()->getTargetUrl();

        return Inertia::render('Users/Form', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name'      => ['required'],
            'email'     => ['required'],
        ]);

        $saveRedirect = $request['saveRedirect'];
        unset($request['saveRedirect']);
        unset($request['created_at']);
        unset($request['updated_at']);

        if ($request->input('password')) {
            $request['password'] = Hash::make($request->input('password'));
        }

        // Salvo l'utente
        $user = \App\Models\User::find($id);
        $user->fill($request->all());
        $user->save();

        // Salvo le impostazioni dell'utente
        $settings = new Settings();
        $settings->update($request, $id);

        return Redirect::to($saveRedirect);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        \App\Models\User::destroy($id);

        $settings = \App\Models\Settings::where('user_id', $id)->first();
        if ($settings) {
            \App\Models\Settings::destroy($settings->id);
        }

        return \redirect()->back();
    }
}
