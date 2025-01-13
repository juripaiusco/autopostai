<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

/**
 * Gestione utenti
 *
 * Qui vengono creati gli utenti:
 * - Manager
 * - Utenti
 *
 * L'amministratore piò creare utenti e manager, i manager
 * possono creare sotto utenti.
 *
 * **Manager**
 * Possono gestire il proprio profilo e i sotto utentei. Un manager
 * può creare un numero limitato di sotto utenti, questo numero viene
 * definito dall'amministratore.
 * Un mananger può gestire il profilo dei propri sotto utenti. Un manager
 * può creare dei post per il proprio sotto utente.
 *
 * **Utente**
 * L'utente può gestire il proprio profilo, i propri post e le proprie
 * impostazioni.
 */
class Users extends Controller
{
    /**
     * Questo metodo è la struttura dei canali di comunicazione.
     *
     * In questo metodo sono definiti i canali cominicativi con le relative opzioni.
     * Questo significa che la struttura diventa un'impostazione per l'utente, in ogni
     * utente viene definito cosa può fare e che tipo di canale può utilizzare.
     *
     * In un secondo momento quando viene generato il post, questa struttura viene ripresa
     * per mostrare quali canali possono essere utilizzati per il post, ma con le regole
     * definite nel profilo utente.
     *
     * *Esempio:*
     * 1. Genero un utente, inserisco quali canali può usare e con quali opzioni
     * 2. Genero un post, seleziono l'utente e in base alle impostazioni creare nel
     * punto 1, il post si comporta seguendo le regole utente.
     *
     * Questo metodo è un filo che lega opzini utente e opzioni post, così se in futuro
     * ci saranno nuovi canali da aggiungere, basterà inserirli qui, con le relative opzioni
     * e automaticamente queste verranno mostrare nel profilo utente e nella scheda del post.
     *
     * La logica di come verranno usate queste regole è dettata nello script Python utilizzato
     * per il collegamento ai vari canali.
     *
     * **Stile della struttura dei canali**
     * Questo array è strano, perché ha un utilizzo bivalente:
     * 1. Account
     * 2. Post
     *
     * Nel caso dell'account i campi vengono utilizzati in questo modo:
     * - name: Nome del canale
     * - on: se il canale può essere utilizzato dall'utente dei post
     * - reply_on: se sono accettate risposte
     * - reply_n: quante risposte sono accettate
     *
     * Nel caso dei post i campi vengono utilizzati in questo modo:
     * name: Nome del canale
     * - id: ID del post generato, quando viene inviato il post al canale, il post pubblicato
     * di solito è associato ad un ID univoco, questo ID viene salvato in questo campo
     * - on: se il post utilizza questa canale. Un post può essere inviato in modo multiplo o singolo
     * se seleziono solo WordPress il post sarà molto più lungo, nel caso di un social sarà più corto,
     * di conseguenza ogni post dovrà essere predisposto per il tipo di canale, inserire i social e la
     * newsletter nella pubblicazione del post non è una buona idea, perché o un canale o l'altro avrà
     * un contenuto non ideo dal punto di vista formale (social contenuto più corto, sito più lungo).
     *
     * @return array[]
     */
    public function get_channels()
    {
        $channels = [
            'facebook' => [
                'name' => 'Facebook',
                'css_class' => 'fa-brands fa-facebook',
                'id' => null,
                'on' => null,
                'reply_on' => null,
                'reply_n' => null,
            ],
            'instagram' => [
                'name' => 'Instagram',
                'css_class' => 'fa-brands fa-instagram',
                'id' => null,
                'on' => null,
                'reply_on' => null,
                'reply_n' => null,
            ],
            'wordpress' => [
                'name' => 'WordPress',
                'css_class' => 'fa-brands fa-wordpress-simple',
                'id' => null,
                'on' => null,
                'reply_on' => null,
                'reply_n' => null,
            ],
            'newsletter' => [
                'name' => 'Newsletter',
                'css_class' => 'fa-regular fa-envelope',
                'id' => null,
                'on' => null,
                'reply_on' => null,
                'reply_n' => null,
            ],
        ];

        return $channels;
    }
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

        $data['channels'] = $this->get_channels();

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
        $user->channels = json_encode($request->input('channels'));
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

        $data['channels'] = json_decode($data->channels, true);

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
        } else {
            unset($request['password']);
        }

        // Salvo l'utente
        $user = \App\Models\User::find($id);
        $user->fill($request->all());
        $user->channels = json_encode($request->input('channels'));
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
