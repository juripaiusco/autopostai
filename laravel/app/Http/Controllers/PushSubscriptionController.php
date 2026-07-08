<?php

namespace App\Http\Controllers;

use App\Notifications\PushNotificationAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Solo le notifiche "di prodotto" (create da Notifiche > Nuova, tipo
     * PushNotificationAlert) compaiono nella campanella. Le notifiche
     * funzionali (es. "post inviato", quando esisteranno) useranno un'altra
     * classe e non devono comparire qui — la campanella e' per comunicare
     * con l'utente, non per il log delle azioni sui post.
     */
    private const BELL_NOTIFICATION_TYPE = PushNotificationAlert::class;
    /**
     * Il browser ha appena chiesto/ottenuto il permesso e generato una
     * PushSubscription (endpoint + chiavi) — la colleghiamo all'utente loggato.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            endpoint: $data['endpoint'],
            key: $data['keys']['p256dh'],
            token: $data['keys']['auth'],
        );

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Polling leggero per il pallino rosso sulla campanella in header —
     * si appoggia al read_at per-notifica di Laravel (via canale 'database'
     * sulla Notification), non un flag unico per-utente come in v1.
     */
    public function checkUnread(Request $request): JsonResponse
    {
        $unread = $request->user()->unreadNotifications()
            ->where('type', self::BELL_NOTIFICATION_TYPE)
            ->exists();

        return response()->json(['unread' => $unread]);
    }

    /**
     * Click sulla campanella: segna come lette le notifiche non lette,
     * torna le ultime per popolare il dropdown.
     */
    public function markRead(Request $request): JsonResponse
    {
        $me = $request->user();

        $recent = $me->notifications()->where('type', self::BELL_NOTIFICATION_TYPE)->latest()->limit(5)->get();
        $me->unreadNotifications()->where('type', self::BELL_NOTIFICATION_TYPE)->update(['read_at' => now()]);

        return response()->json([
            'notifications' => $recent->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? null,
                'body' => $n->data['body'] ?? null,
                'url' => $n->data['url'] ?? null,
                'createdAt' => $n->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
