<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class Posts extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $request_search_array = [
            'title',
            'published_at',
            'published',
        ];

        $request_validate_array = $request_search_array;

        // Query data
        $data = \App\Models\Post::query();

        $data = $data->where('user_id', auth()->user()->id);

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

        $data = $data->select([
            'posts.id',
            'posts.title',
            'posts.published_at',
            'posts.meta_facebook',
            'posts.meta_instagram',
            'posts.wordpress',
            'posts.newsletter',
        ]);

        $data = $data->paginate(env('VIEWS_PAGINATE'))->withQueryString();

        return Inertia::render('Posts/List', [
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

        $post = new \App\Models\Post();
        $post->fill($request->all());
        $post->user_id = auth()->user()->id;
        $post->save();
        $this->save_img('posts', $post, $request);

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
    public function edit(Request $request, string $id)
    {
        $data = \App\Models\Post::find($id);
        $data->img = Storage::disk('public')->url('posts/' . $id . '/' . $data->img);

        $data->saveRedirect = Redirect::back()->getTargetUrl();

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
