<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SavePushNotification;
use App\Models\PushNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PushNotificationController extends Controller
{
    public function __construct(private SavePushNotification $notifications) {}

    /**
     * Archivio: l'admin vede tutte le notifiche create, il manager solo le
     * proprie. L'invio vero (WebPush) lo fa il comando schedulato
     * notifications:send-pending, non questo controller — creare una riga
     * qui la mette semplicemente "in coda" per il prossimo giro dello
     * scheduler (entro un minuto), niente bottone "invia" separato.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $this->authorize('viewAny', PushNotification::class);

        $notifications = PushNotification::query()
            ->visibleTo($me)
            ->with(['user:id,name', 'createdBy:id,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PushNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'recipient' => $n->user
                    ? $n->user->name
                    : ($n->audience === 'all' ? 'Tutti gli utenti' : 'I tuoi utenti'),
                'status' => $n->sent_at ? 'inviata' : 'in coda',
                'recipientsCount' => $n->recipients_count,
                'createdAt' => $n->created_at?->toIso8601String(),
                'sentAt' => $n->sent_at?->toIso8601String(),
            ]);

        return Inertia::render('Notifications/List', [
            'notifications' => $notifications,
        ]);
    }

    public function create(Request $request): Response
    {
        $me = $request->user();
        $this->authorize('create', PushNotification::class);

        return Inertia::render('Notifications/Form', [
            'mode' => 'create',
            'canBroadcastAll' => $me->isAdmin(),
            'recipients' => $me->filterableUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $this->authorize('create', PushNotification::class);

        $this->notifications->create($me, $request->validate(SavePushNotification::rules()));

        $request->session()->flash('toast', 'Notifica in coda: verrà inviata a breve.');

        return redirect()->route('notifications');
    }

    public function edit(Request $request, PushNotification $notification): Response|RedirectResponse
    {
        $this->authorize('view', $notification);

        if ($notification->sent_at) {
            return redirect()->route('notifications');
        }

        return Inertia::render('Notifications/Form', [
            'mode' => 'edit',
            'canBroadcastAll' => $request->user()->isAdmin(),
            'recipients' => $request->user()->filterableUsers(),
            'notification' => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'url' => $notification->url,
                'user_id' => $notification->user_id,
                'audience' => $notification->audience,
            ],
        ]);
    }

    public function update(Request $request, PushNotification $notification): RedirectResponse
    {
        $this->authorize('view', $notification);
        abort_if($notification->sent_at !== null, 403, 'Questa notifica è già stata inviata.');

        $this->notifications->update($request->user(), $notification, $request->validate(SavePushNotification::rules()));

        $request->session()->flash('toast', 'Notifica aggiornata.');

        return redirect()->route('notifications');
    }

    public function destroy(Request $request, PushNotification $notification): RedirectResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return back();
    }
}
