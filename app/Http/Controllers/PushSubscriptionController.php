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

    public function store(Request $request)
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
    }

    public function show(Request $request)
    {
        $endpoint = $request->query('endpoint');
        $subscription = PushSubscription::where('endpoint', $endpoint)->first();

        if (!$subscription) {
            return response()->json(['message' => 'Subscription non trovata'], 404);
        }

        /*$user = User::find($subscription->subscribable_id);
        'Ciao ' . $user->name . ' dal server! 👋'*/

        /*$notification = PushNotification::query()
            ->whereNull('sent')
            ->orderBy('created_at', 'desc')
            ->first();*/
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
    }

    public function read_set()
    {
        $user = User::find(Auth::user()->id);
        $user->notify_read_web = 1;
        $user->save();
    }
}
