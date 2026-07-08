<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
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
        return response()->json(['unread' => $request->user()->unreadNotifications()->exists()]);
    }

    /**
     * Click sulla campanella: segna come lette le notifiche non lette,
     * torna le ultime per popolare il dropdown.
     */
    public function markRead(Request $request): JsonResponse
    {
        $me = $request->user();

        $recent = $me->notifications()->latest()->limit(5)->get();
        $me->unreadNotifications()->update(['read_at' => now()]);

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
