<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Lista / Crea / Visualizza / Elimina
 *
 * Tramite questa classe vengono creati tutti i contenuti da inviare ai vari canali digitali.
 *
 * **Canali digitali:**
 * - Facebook;
 * - Instagram;
 * - WordPress (in lavorazione);
 * - Newsletter (in lavorazione).
 *
 * **Processo per inviare il post:**
 * 1. Creazione del post
 * <br>Inserimento dei vari campi: titolo, prompt, ...
 *
 * 2. Collegamento ad OpenAI o altro sistema
 * <br>Interfacciamento ad un LLM, e quindi generazione del contenuto da creare e recupero
 *
 * 3. Collegamento ai vari canali selezionati
 * 4. Recupero dei commenti
 * 5. Generazione delle risposte
 *
 * **Campi del database:**
 * - Titolo del post (usato come campo interno per riferimento)
 * - Data di pubblicazione del post
 * - Prompt da inviare all'AI
 * - Canali: Facebook / Instagram / ...
 * - Immagine con check per confermare se l'AI debba o meno interpretarla
 *
 */
class Posts extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request_search_array = [
            'title',
            'published_at',
            'published',
        ];

        $request_validate_array = $request_search_array;

        // Query data
        $data = \App\Models\Post::query();
        $data = $data->with(['user', 'comments']);

        // Request validate
        request()->validate([
            'orderby' => ['in:' . implode(',', $request_validate_array)],
            'ordertype' => ['in:asc,desc']
        ]);

        // Filtro RICERCA
        if (request('s')) {
            $data->where(function ($q) use ($request_search_array) {

                foreach ($request_search_array as $field) {
                    $q->orWhere('posts.' . $field, 'like', '%' . request('s') . '%');
                }

            });
        }

        // Filtro ORDINAMENTO
        if (request('orderby') && request('ordertype')) {
            $data->orderby(request('orderby'), strtoupper(request('ordertype')));
        }

        /*$data = $data->select([
            'posts.id',
            'posts.title',
            'posts.published',
            'posts.published_at',
            'posts.meta_facebook_on',
            'posts.meta_instagram_on',
            'posts.wordpress_on',
            'posts.newsletter_on',
        ]);*/

        // Se l'utente è singolo
        if (auth()->user()->parent_id && !auth()->user()->child_on) {
            $data = $data->where('user_id', auth()->user()->id);
        }

        // Se l'utente è Manager
        if (auth()->user()->parent_id && auth()->user()->child_on) {
            $parentId = auth()->user()->id;
            $data = $data->whereHas('user', function ($query) use ($parentId) {
                $query->where('parent_id', $parentId);
            });
        }

        $data = $data->paginate(env('VIEWS_PAGINATE'))->withQueryString();

        session()->forget('saveRedirectPosts');

        return Inertia::render('Posts/List', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype']),
            'token' => $request->user()->createToken('posts')
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Creo un oggetto di dati vuoto
        $columns = Schema::getColumnListing('posts');

        $data = array();
        foreach ($columns as $field) {
            $data[$field] = '';
        }

        unset($data['id']);
        unset($data['deleted_at']);
        unset($data['created_at']);
        unset($data['updated_at']);

        $data['saveRedirect'] = Redirect::back()->getTargetUrl();

        /**
         * Query per recuperare gli utente a cui collegare il post
         * la query viene creata seguendo delle regole di gestione
         * degli account:
         * L'amministratore può collegare i post a tutti gli utenti
         * Il Manager può collegare i post solo ai suoi sotto utenti
         */
        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        if (!auth()->user()->parent_id || (auth()->user()->parent_id && auth()->user()->child_on)) {

            // Query data per mostrare gli utenti a cui collegare il Post
            $data['users'] = \App\Models\User::query();
            $data['users'] = $data['users']->with('children');

            // Se l'utente è admin
            if (!auth()->user()->parent_id) {
                $data['users'] = $data['users']->whereNotNull('parent_id');
                $data['users'] = $data['users']->whereNull('child_on');
            }

            // Se l'utente è Manager, mostro solo i suoi utenti
            if (auth()->user()->parent_id && auth()->user()->child_on) {
                $data['users'] = $data['users']->where('parent_id', auth()->user()->id);
            }

            $data['users'] = $data['users']->get();

        } else if (auth()->user()->parent_id) {

            $data['user'] = \App\Models\User::query();
            $data['user'] = $data['user']->where('id', auth()->user()->id);

            $data['user'] = $data['user']->first();

        }
        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        $data = json_decode(json_encode($data), true);

        return Inertia::render('Posts/Form', [
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
        ]);

        $saveRedirect = $request['saveRedirect'];
        unset($request['saveRedirect']);
        unset($request['img']);
        unset($request['users']);

        $post = new \App\Models\Post();
        $post->fill($request->all());

        $post->user_id = $request->input('user_id') ? $request->input('user_id') : auth()->user()->id;

        $post->save();
        $this->save_img('posts', $post, $request);

        return Redirect::to($saveRedirect);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $data = \App\Models\Post::with(['user', 'comments'])->find($id);

        if ($data->img)
            $data->img = Storage::disk('public')->url('posts/' . $id . '/' . $data->img);

        if ($request->input('inertiaVisit') == true && !$request->session()->get('saveRedirectPosts')) {
            $request->session()->put('saveRedirectPosts', Redirect::back()->getTargetUrl());
        }

        $data->saveRedirect = $request->session()->get('saveRedirectPosts');

        return Inertia::render('Posts/Show', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        $data = \App\Models\Post::with('user')->find($id);

        if ($data->img)
            $data->img = Storage::disk('public')->url('posts/' . $id . '/' . $data->img);

        if ($request->input('inertiaVisit') == true && !$request->session()->get('saveRedirectPosts')) {
            $request->session()->put('saveRedirectPosts', Redirect::back()->getTargetUrl());
        }

        $data->saveRedirect = $request->session()->get('saveRedirectPosts');

        return Inertia::render('Posts/Form', [
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
        ]);

        $saveRedirect = $request['saveRedirect'];
        unset($request['saveRedirect']);
        unset($request['created_at']);
        unset($request['updated_at']);
        unset($request['img']);
        unset($request['user']);

        $post = \App\Models\Post::find($id);
        $post->fill($request->all());
        $post->save();
        $this->save_img('posts', $post, $request);

        return Redirect::to($saveRedirect);
    }

    private function save_img($path, $data, $request)
    {
        if ($request->file('img')) {

            $data->img = date('mdYHis') . '-' . uniqid() . '-' . $request->file('img')->getClientOriginalName();

            if ($request->file('img')->isValid()) {

                Storage::disk('public')->deleteDirectory($path . '/' . $data->id);
                Storage::disk('public')
                    ->put(
                        $path . '/' . $data->id . '/' . $data->img,
                        $request->file('img')->get()
                    );
            }

            $data->save();
        }

        return $data;
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $post = \App\Models\Post::find($id);
        Storage::disk('public')->deleteDirectory('posts/' . $post->id);

        \App\Models\Post::destroy($id);

        return \redirect()->back();
    }
}
