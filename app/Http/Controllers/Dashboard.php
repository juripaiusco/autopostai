<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Dashboard extends Controller
{
    public function index()
    {
        /**
         * Recupero gli utenti
         */
        $users = User::query();

        // Se l'utente è admin
        if (!auth()->user()->parent_id) {
            $users = $users->whereNotNull('parent_id');
            $users = $users->whereNull('child_on');
        }

        // Se l'utente è Manager, mostro solo i suoi utenti
        if (auth()->user()->parent_id && auth()->user()->child_on) {
            $users = $users->where('parent_id', auth()->user()->id);
        }

        $users = $users->get();

        // Se l'utente è singolo
        if (auth()->user()->parent_id && !auth()->user()->child_on) {
            $users = false;
        }

        $data['users'] = $users;

        // - - - - - - - - - - - - - - - - - - - - - - - -

        /**
         * Recupero i post
         */
        $posts = \App\Models\Post::query();

        // Se l'utente è singolo
        if (auth()->user()->parent_id && !auth()->user()->child_on) {
            $posts = $posts->where('user_id', auth()->user()->id);
        }

        // Se l'utente è Manager
        if (auth()->user()->parent_id && auth()->user()->child_on) {
            $parentId = auth()->user()->id;
            $posts = $posts->whereHas('user', function ($query) use ($parentId) {
                $query->where('parent_id', $parentId);
            });
        }

        $posts = $posts->get();

        $data['posts'] = $posts;

        // - - - - - - - - - - - - - - - - - - - - - - - -

        /**
         * Recupero i commenti
         */
        /*$comments = \App\Models\Comment::query();

        $comments = $comments->get();*/
        $comments = [];

        // - - - - - - - - - - - - - - - - - - - - - - - -

        $data['comments'] = $comments;

        return Inertia::render('Dashboard', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }
}
