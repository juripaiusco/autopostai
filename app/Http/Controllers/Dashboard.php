<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Prima schermata dopo il login.
 *
 * Questa classe mostra con un colpo d'occhio la situazione
 * degli utenti e dei post a chi fa login.
 *
 * Per adesso è molto banale, ma in futuro implementerà queste
 * features:
 * - Mostra quanti tocken sono stati consumati;
 * - Mostra quanto è stato speso in base ai tocken utilizzati.
 *
 * Volendo implementare un sistema a pagamento, magari ad abbonamento,
 * in questa schermata sarà possibile mostrare quanti tocken, o il cost
 * raggiunto prima che il sistema non invii più dati fino al prossimo
 * rinnovo.
 */
class Dashboard extends Controller
{
    /**
     * Renderizzione tramite Inertia dei dati
     *
     * Se sei un amministratore vengono mostrati questi dati:
     * - il numero degli utenti;
     * - il numero dei post;
     * - il numero dei commenti.
     *
     * Se sei un manager vengono mostrati questi dati:
     * - il numero dei tuoi sotto utenti;
     * - il numero dei post dei tuoi sotto utenti;
     * - il numero dei commenti dei tuoi sotto utenti.
     *
     * Se sei un utente vengono mostrati questi dati:
     * - il numero dei tuoi post;
     * - il numero dei tuoi commenti.
     *
     * @return \Inertia\Response
     */
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
        $comments = \App\Models\Comment::query();

        // Se l'utente è singolo
        if (auth()->user()->parent_id && !auth()->user()->child_on) {
            $comments = $comments->with('post');
            $comments = $comments->whereHas('post', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });
        }

        // Se l'utente è Manager
        if (auth()->user()->parent_id && auth()->user()->child_on) {
            $parentId = auth()->user()->id;
            $comments = $comments->with('post');
            $comments = $comments->whereHas('post', function ($query) use ($parentId) {
                $query = $query->with('user');
                $query->whereHas('user', function ($query) use ($parentId) {
                    $query->where('parent_id', $parentId);
                });
            });
        }

        $comments = $comments->get();

        // - - - - - - - - - - - - - - - - - - - - - - - -

        $data['comments'] = $comments;

        return Inertia::render('Dashboard', [
            'data' => $data,
            'filters' => request()->all(['s', 'orderby', 'ordertype'])
        ]);
    }
}
