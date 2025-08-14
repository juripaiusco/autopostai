<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
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

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = User::query()->find(Auth::user()->id);
        $user->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Subscription eliminata con successo']);
    }

    public function notify_web_check()
    {
        $user = User::find(Auth::user()->id);

        return response()->json(['notify' => $user->notify_read_web ? 1 : 0]);
    }

    /**
     * Imposta come letta la notifica push per il web
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function read_set()
    {
        $user = User::find(Auth::user()->id);
        $user->notify_read_web = 1;
        $user->save();

        $notifications = PushNotification::query()
            ->take(5)
            ->whereNull('user_id')
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->get();

        return response()->json(['notifications' => $notifications]);
    }
}
