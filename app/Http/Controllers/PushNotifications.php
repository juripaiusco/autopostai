<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\TestPushNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotifications extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

        $request_search_array = [
            'title',
            'body',
            'url',
            'created_at',
        ];

        $request_validate_array = $request_search_array;

        // Query data
        $data = PushNotification::query();
        $data = $data->with('user');
        // $data = $data->whereNull('user_id'); // Solo notifiche generali, non per utente specifico

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
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

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
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

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
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

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
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

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
        $notification->fill($request->all());
        $notification->save();

        return Redirect::to($saveRedirect);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

        $notification = \App\Models\PushNotification::find($id);

        if ($notification) {
            $notification->delete();
        }

        return Redirect::route('notification.index', 'orderby=created_at&ordertype=desc&s=');
    }

    /*public function send_raw()
    {
        // Permessi
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

        // Recupera utenti con subscription
        $users = User::with('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return Redirect::route('notification.index')
                ->with('error', 'Nessun utente con push subscription');
        }

        // Configurazione WebPush
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => env('VAPID_SUBJECT'),
                'publicKey' => env('VAPID_PUBLIC_KEY'),
                'privateKey' => env('VAPID_PRIVATE_KEY'),
            ],
        ]);

        // Prepara il payload
        $payload = json_encode([
            'title' => 'Test',
            'body'  => 'Funziona?',
            'url'   => '/'
        ]);

        // Invia a tutti
        Log::info('Inizio invio notifiche');

        foreach ($users as $user) {
            Log::info("Utente: {$user->id} - subscription count: " . $user->pushSubscriptions->count());

            foreach ($user->pushSubscriptions as $subscription) {
                Log::info('Subscription endpoint: ' . $subscription->endpoint);

                $subscriptionArray = [
                    'endpoint' => $subscription->endpoint,
                    'expirationTime' => null,
                    'keys' => [
                        'p256dh' => $subscription->public_key,
                        'auth'   => $subscription->auth_token,
                    ],
                    'content_encoding' => $subscription->content_encoding,
                ];

                $webPush->sendOneNotification(
                    Subscription::create($subscriptionArray),
                    $payload
                );
            }
        }

        Log::info('Chiamo flush()...');

        $tuttiOk = true;
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if ($report->isSuccess()) {
                Log::info("OK -> {$endpoint}");
            } else {
                Log::error("ERRORE -> {$endpoint}: {$report->getReason()}");
                $tuttiOk = false;
            }
        }

        Log::info('Invio notifiche terminato');

        // Segna come inviata solo se tutto ok
        if ($tuttiOk) {
            $notification = PushNotification::whereNull('sent')->orderBy('created_at', 'desc')->first();
            if ($notification) {
                $notification->sent = 1;
                $notification->save();
            }
        }

        return Redirect::route('notification.index', 'orderby=created_at&ordertype=desc&s=')
            ->with($tuttiOk ? 'success' : 'warning', $tuttiOk ? 'Notifica inviata' : 'Alcune notifiche non sono state inviate');
    }*/

    public function send($id)
    {
        if (!Auth::user()->can('view', Auth::user())) {
            abort(403);
        }

        $notification = PushNotification::query()
            ->where('id', $id)
            ->whereNull('user_id')
            ->whereNull('sent_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($notification) {

            $users = \App\Models\User::query()->with('pushSubscriptions')->get();

            foreach ($users as $user) {
                $user->notify(new \App\Notifications\PushNotification([
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'url' => $notification->url,
                    'icon' => env('APP_URL') . '/faper3-logo.png',
                    'data' => [
                        'created_at' => $notification->created_at,
                        'sent_at' => $notification->sent_at
                    ],
                    'actions' => [
                        [
                            'action' => 'view_details',
                            'title' => 'Vedi dettagli',
                        ], [
                            'action' => 'dismiss',
                            'title' => 'Chiudi',
                        ],
                    ],
                ]));
                $user->notify_read_browser = null;
                $user->notify_read_web = null;
                $user->save();
            }

            if ($notification) {
                $notification->sent_at = Carbon::now('Europe/Rome')->toDateTimeString();
                $notification->save();
            }

            return Redirect::route('notification.index', 'orderby=created_at&ordertype=desc&s=');
        }
    }
}
