<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use NotificationChannels\WebPush\HasPushSubscriptions;

class PushSubscriptionController extends Controller
{
    use HasPushSubscriptions;

    /**
     * Salva la subscription per le notifiche push
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'content_encoding' => 'required|string|in:aes128gcm,aesgcm',
        ]);

        $user = User::query()->find(Auth::user()->id);
        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding']
        );

        return response()->json(['message' => 'Subscription salvata con successo']);
    }

    /*public function store_(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'content_encoding' => 'required|string|in:aes128gcm,aesgcm',
        ]);

        // Evita duplicati: cerca se esiste già la subscription per questo endpoint
        $subscription = PushSubscription::firstOrNew([
            'endpoint' => $validated['endpoint'],
        ]);

        // Aggiorna o assegna dati
        $subscription->fill([
            'publicKey' => $validated['keys']['p256dh'],
            'authToken' => $validated['keys']['auth'],
            'content_encoding' => $validated['content_encoding'],
        ]);

        // Associa la subscription all'utente (usando relazione polimorfica)
        $subscription->subscribable_id = $user->id;
        $subscription->subscribable_type = get_class($user);

        $subscription->save();

        return response()->json(['message' => 'Subscription salvata con successo']);
    }*/

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Questo metodo era stato creato per mostrare l'ultima notifica
     * ma non è più utilizzato. Ora le notifiche vengono inviate correttamente
     * tramite il metodo `toWebPush` della classe `PushNotification`.
     */
    /*public function show(Request $request)
    {
        $endpoint = $request->query('endpoint');
        $subscription = PushSubscription::where('endpoint', $endpoint)->first();

        if (!$subscription) {
            return response()->json(['message' => 'Subscription non trovata'], 404);
        }

        $notification = PushNotification::query()
            ->orderBy('created_at', 'desc')
            ->first();

        $data = [
            'title' => $notification->title,
            'icon' => '/faper3-logo.png',
            'body' => $notification->body,
            'url' => $notification->url,    // attenzione: 'data' -> 'url' deve essere in radice oggetto JSON per service worker
            'actions' => [
                [
                    'action' => 'view_details',
                    'title' => 'Vedi dettagli',
                ], [
                    'action' => 'dismiss',
                    'title' => 'Chiudi',
                ],
            ],
        ];

        return response()->json($data);
    }*/

    /**
     * Imposta come letta la notifica push per il web
     *
     * @return void
     */
    public function read_set()
    {
        $user = User::find(Auth::user()->id);
        $user->notify_read_web = 1;
        $user->save();
    }
}
