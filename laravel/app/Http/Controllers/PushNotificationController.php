<?php

namespace App\Http\Controllers;

use App\Models\PushNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PushNotificationController extends Controller
{
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

        $data = $this->validated($request, $me);

        PushNotification::create([
            ...$data,
            'created_by_user_id' => $me->id,
        ]);

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
        $me = $request->user();
        $this->authorize('view', $notification);
        abort_if($notification->sent_at !== null, 403, 'Questa notifica è già stata inviata.');

        $notification->update($this->validated($request, $me));

        $request->session()->flash('toast', 'Notifica aggiornata.');

        return redirect()->route('notifications');
    }

    public function destroy(Request $request, PushNotification $notification): RedirectResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return back();
    }

    /**
     * Whitelist + regole di scoping: 'all' solo admin, 'children' solo
     * manager, un utente specifico deve comparire tra i filterableUsers()
     * di chi lo sta scegliendo (stesso confine gia' usato per Post/Search).
     */
    private function validated(Request $request, $me): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'recipient_type' => ['required', Rule::in(['all', 'children', 'user'])],
            'user_id' => ['nullable', 'integer'],
        ]);

        abort_if($data['recipient_type'] === 'all' && !$me->isAdmin(), 403);
        abort_if($data['recipient_type'] === 'children' && !$me->isManager(), 403);

        $userId = null;
        if ($data['recipient_type'] === 'user') {
            abort_unless($me->filterableUsers()->contains('id', $data['user_id'] ?? null), 422, 'Destinatario non valido.');
            $userId = $data['user_id'];
        }

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'] ?? null,
            'audience' => $userId ? null : $data['recipient_type'],
            'user_id' => $userId,
        ];
    }
}
