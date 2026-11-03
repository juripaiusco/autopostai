<?php

namespace App\Actions\Notifications;

use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Creazione e modifica di una notifica push: archivio Notifiche e tool MCP.
 * L'invio vero (WebPush) lo fa il comando schedulato
 * notifications:send-pending: salvare la riga la mette "in coda" per il
 * prossimo giro dello scheduler (entro un minuto). L'autorizzazione
 * (PushNotificationPolicy) la fa il chiamante.
 */
class SavePushNotification
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'recipient_type' => ['required', Rule::in(['all', 'children', 'user'])],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    public function create(User $actor, array $data): PushNotification
    {
        return PushNotification::create([
            ...$this->attributes($actor, $data),
            'created_by_user_id' => $actor->id,
        ]);
    }

    public function update(User $actor, PushNotification $notification, array $data): PushNotification
    {
        abort_if($notification->sent_at !== null, 403, 'Questa notifica è già stata inviata.');

        $notification->update($this->attributes($actor, $data));

        return $notification;
    }

    /**
     * Regole di scoping: 'all' solo admin, 'children' solo manager, un
     * utente specifico deve comparire tra i filterableUsers() di chi lo sta
     * scegliendo (stesso confine gia' usato per Post/Search).
     */
    private function attributes(User $actor, array $data): array
    {
        abort_if($data['recipient_type'] === 'all' && !$actor->isAdmin(), 403);
        abort_if($data['recipient_type'] === 'children' && !$actor->isManager(), 403);

        $userId = null;
        if ($data['recipient_type'] === 'user') {
            abort_unless($actor->filterableUsers()->contains('id', $data['user_id'] ?? null), 422, 'Destinatario non valido.');
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
