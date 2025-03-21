<?php

namespace App\Http\Controllers;

use App\Models\ImageJob;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
 * 1. Creazione del post<br>
 *    Inserimento dei vari campi: titolo, prompt, ...
 *
 * 2. Collegamento ad OpenAI o altro sistema<br>
 *    Interfacciamento ad un LLM, e quindi generazione del contenuto da creare e recupero
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
    private function getDataNewPost()
    {
        // Creo un oggetto di dati vuoto
        $table = 'posts';
        $columns = Schema::getColumnListing($table);

        $data = [];
        foreach ($columns as $field) {
            // Recupera i dettagli della colonna dal database
            $columnDetails = DB::selectOne("SHOW COLUMNS FROM " . env('DB_PREFIX') . $table . " WHERE Field = ?", [$field]);
            $default = $columnDetails->Default;

            $data[$field] = $default ?? '';
        }

        unset($data['id']);
        unset($data['deleted_at']);
        unset($data['created_at']);
        unset($data['updated_at']);

        $data['saveRedirect'] = Redirect::back()->getTargetUrl();
        if (!strstr($data['saveRedirect'], 'posts?')) {
            $data['saveRedirect'] = route('post.index') . '?orderby=published_at&ordertype=desc&s=';
        }

        $data['channels'] = (new Users)->get_channels();

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
            $data['user_id'] = $data['user']->id;

        }
        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

        $data['files'] = $this->get_image_list();
        $data['img_selected'] = null;

        $data = json_decode(json_encode($data), true);

        return $data;
    }

    public function getData()
    {
        // Query data
        $data = Post::query();
        $data = $data->with(['user', 'comments.token', 'tokens']);

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

        $data->where('preview', 0);

        return $data;
    }

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
        $data = $this->getData();

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

        $data = $data->paginate(env('VIEWS_PAGINATE'))->withQueryString();

        session()->forget('saveRedirectPosts');

        return Inertia::render('Posts/List', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype']),
            'token' => $request->user()->createToken('posts')
        ]);
    }

    public function schedule(Request $request)
    {
        return Inertia::render('Posts/Schedule', [
            'data' => $this->getDataNewPost(),
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }

    public function schedule_store(Request $request)
    {
        $saveRedirect = route('post.index') . '?orderby=published_at&ordertype=desc&s=';
        $published_at_array = $request->published_at;

        foreach ($published_at_array as $published_at)
        {
            $this->storeData($request, $published_at);
        }

        return Redirect::to($saveRedirect);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        return Inertia::render('Posts/Form', [
            'data' => $this->getDataNewPost(),
            'filters' => request()->all(['s', 'orderby', 'ordertype']),
            'token' => $request->user()->createToken('posts')
        ]);
    }

    private function storeData(Request $request, $published_at = '', $preview = false)
    {
        $request->validate([
            'title' => 'required',
            'ai_prompt_post' => 'required',
        ]);

        if ($request['img_selected'])
            $request['img'] = $request['img_selected'];

        unset($request['saveRedirect']);
        unset($request['user']);
        unset($request['users']);
        unset($request['files']);
        unset($request['ai_prompt_img']);
        unset($request['img_selected']);
        unset($request['schedule']);

        if ($published_at) {
            unset($request['published_at']);
        }

        $post = new Post();
        $post->fill($request->all());

        if ($published_at) {
            $post->published_at = $published_at;
            $post->title = $request['title'] . ' ' . date('d/m/Y H:i', strtotime($published_at));
        }

        if ($preview == true) {
            $post->preview = 1;
        }

        $post->channels = json_encode($request->input('channels'));
        $post->on_hold_until = date('Y-m-d H:i:s');

        $post->user_id = $request->input('user_id') ? $request->input('user_id') : auth()->user()->id;

        $post->save();
        $this->save_img('posts', $post, $request);

        return $post;
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $saveRedirect = $request['saveRedirect'];
        $request['preview'] = 0;

        $this->storeData($request);

        return Redirect::to($saveRedirect);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $data = Post::with(['user', 'comments.token', 'token'])->find($id);

        if (!Auth::user()->can('viewAny', $data)) {
            abort(403);
        }

        if ($data->img)
            $data->img = Storage::disk('public')->url('posts/' . $id . '/' . $data->img);

        if (!$request->session()->get('saveRedirectPosts')) {
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
        $data = Post::with('user', 'tokens')->find($id);

        if (!Auth::user()->can('viewAny', $data)) {
            abort(403);
        }

        if ($data->img)
            $data->img = Storage::disk('public')->url('posts/' . $id . '/' . $data->img);

        if (!$request->session()->get('saveRedirectPosts')) {
            $request->session()->put('saveRedirectPosts', Redirect::back()->getTargetUrl());
        }

        $data->saveRedirect = $request->session()->get('saveRedirectPosts');

        $data['channels'] = json_decode($data->channels, true);
        $data['files'] = $this->get_image_list();
        $data['img_selected'] = null;

        if ($data->published == 1) {
            return redirect()->route('post.show', ['id' => $id]);
        }

        return Inertia::render('Posts/Form', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype']),
            'token' => $request->user()->createToken('posts')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $saveRedirect = $request['saveRedirect'];
        $request['preview'] = 0;
        $this->update_no_redirect($request, $id);

        return Redirect::to($saveRedirect);
    }

    public function updateData(Request $request, string $id)
    {
        $request->validate([
            'title' => 'required',
            'ai_prompt_post' => 'required',
        ]);

        if ($request['img_selected'])
            $request['img'] = $request['img_selected'];

        unset($request['saveRedirect']);
        unset($request['created_at']);
        unset($request['updated_at']);
        unset($request['user']);
        unset($request['files']);
        unset($request['ai_prompt_img']);
        unset($request['img_selected']);
        unset($request['comments']);
        unset($request['token']);

        $post = Post::find($id);

        if (!Auth::user()->can('viewAny', $post)) {
            abort(403);
        }

        $post->fill($request->all());
        $post->on_hold_until = date('Y-m-d H:i:s');
        $post->save();
        $this->save_img('posts', $post, $request);

        return $post;
    }
    public function update_no_redirect(Request $request, string $id)
    {
        $this->updateData($request, $id);
    }

    private function save_img($path, $data, Request $request)
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

        if ($request->input('img')) {
            $img_name = basename($request->input('img'));
            $path_img = Storage::disk('public')->path('stable-diffusion/' . Auth::id() . '/' . $img_name);

            Storage::disk('public')->deleteDirectory($path . '/' . $data->id);
            Storage::disk('public')
                ->put(
                    $path . '/' . $data->id . '/' . $img_name,
                    file_get_contents($path_img)
                );

            $data->img = $img_name;

            $data->save();
        }

        return $data;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(string $id)
    {
        $post = Post::find($id);

        if (!Auth::user()->can('viewAny', $post)) {
            abort(403);
        }

        $post->delete();

        return \redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $post = Post::find($id);

        if (!Auth::user()->can('viewAny', $post)) {
            abort(403);
        }

        Storage::disk('public')->deleteDirectory('posts/' . $post->id);

        Post::destroy($id);

        return \redirect()->back();
    }

    /**
     * Restituisce la lista delle immagini dell'archivio utente
     * @return array
     */
    public function get_image_list()
    {
        $files = Storage::disk('public')->files('stable-diffusion/' . Auth::id() . '/');
        $files_url = [];

        foreach ($files as $file) {
            if (substr(basename($file), 0, 1) != '.') {
                $files_url[] = Storage::disk('public')->url($file);
            }
        }

        $imageJobs = ImageJob::whereIn('image_url', $files_url)->orderBy('created_at', 'desc')->get();

        return $imageJobs;
    }

    /**
     * Elimina l'immagine selezionata dall'archivio utente
     * @param string $img
     * @return bool
     */
    public function destroy_image(string $img)
    {
        return Storage::disk('public')->delete('stable-diffusion/' . Auth::id() . '/' . $img);
    }

    public function preview(Request $request)
    {
        $request['preview'] = 1;

        // Salvo i dati del post
        if (!$request->input('id')) {
            $data = $this->storeData($request, '', true);
        } else {
            $data = $this->updateData($request, $request->input('id'));
        }

        // Docker Python API Request ------------ //
        $response = Http::timeout(120)->post('http://' . env('AUTOPOSTAI_API_URL') . ':8000/generate', [
            'id' => $data->id
        ]);

        $autopostai_api_response = $response->json();

        if (isset($autopostai_api_response) && $autopostai_api_response['status'] == 'success') {
            $request['ai_content'] = $autopostai_api_response['content'];
        }
        // -------------------------------------- //

        // Salvo il contenuto del post
        $data = $this->updateData($request, $data->id);

        return Redirect::route('post.edit', ['id' => $data->id]);
    }
}
