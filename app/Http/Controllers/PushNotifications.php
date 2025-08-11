<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class PushNotifications extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /*$user = Auth::user();
        if (!Auth::user()->can('viewAny', $user)) {
            abort(403);
        }*/

        $request_search_array = [
            'title',
            'body',
            'url',
            'created_at',
        ];

        $request_validate_array = $request_search_array;

        // Query data
        $data = PushNotification::query();

        // Request validate
        request()->validate([
            'orderby' => ['in:' . implode(',', $request_validate_array)],
            'ordertype' => ['in:asc,desc']
        ]);

        // Filtro RICERCA
        if (request('s')) {
            $data->where(function ($q) use ($request_search_array) {

                foreach ($request_search_array as $field) {
                    $q->orWhere('push_notifications.' . $field, 'like', '%' . request('s') . '%');
                }

            });
        }

        // Filtro ORDINAMENTO
        if (request('orderby') && request('ordertype')) {

            $orderby = request('orderby');
            $ordertype = strtoupper(request('ordertype'));

            $data->orderBy($orderby, $ordertype);
        }

        $data = $data->paginate(env('VIEWS_PAGINATE'))->withQueryString();

        session()->forget('saveRedirectPosts');

        return Inertia::render('Notifications/List', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Creo un oggetto di dati vuoto
        $columns = Schema::getColumnListing('push_notifications');

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

        return Inertia::render('Notifications/Form', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'      => ['required'],
            'body'     => ['required'],
        ]);

        $saveRedirect = $request['saveRedirect'];
        unset($request['saveRedirect']);

        // Salvo la notifica
        $notification = new \App\Models\PushNotification();
        $notification->fill($request->all());
        $notification->save();

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
        $data = \App\Models\PushNotification::find($id);

        $data['saveRedirect'] = Redirect::back()->getTargetUrl();

        return Inertia::render('Notifications/Form', [
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
            'title'      => ['required'],
            'body'     => ['required'],
        ]);

        $saveRedirect = $request['saveRedirect'];
        unset($request['saveRedirect']);
        unset($request['created_at']);
        unset($request['updated_at']);

        // Salvo l'utente
        $notification = \App\Models\PushNotification::find($id);

        /*if (!Auth::user()->can('viewAny', $notification)) {
            abort(403);
        }*/

        $notification->fill($request->all());
        $notification->save();

        return Redirect::to($saveRedirect);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
