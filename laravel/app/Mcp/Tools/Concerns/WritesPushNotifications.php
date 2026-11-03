<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\PushNotification;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tool delle notifiche push: solo admin e manager (PushNotificationPolicy),
 * come la sezione Notifiche.
 */
trait WritesPushNotifications
{
    public function shouldRegister(): bool
    {
        return parent::shouldRegister() && Gate::allows('viewAny', PushNotification::class);
    }

    /**
     * Via MCP il link della notifica può puntare solo a FaPer3 (percorso
     * "/…" o URL dell'app): una notifica arriva a utenti veri, un LLM
     * manipolato non deve poter mandare tutti su un sito esterno.
     */
    protected function internalUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $appUrl = rtrim((string) config('app.url'), '/');
            $internal = (Str::startsWith($value, '/') && !Str::startsWith($value, '//'))
                || $value === $appUrl
                || Str::startsWith($value, $appUrl.'/');

            if (!$internal) {
                $fail('Il link deve essere una pagina di FaPer3 (es. "/posts" o '.$appUrl.'/…).');
            }
        };
    }

    protected function findNotification(mixed $id): PushNotification
    {
        $notification = PushNotification::find((int) $id);

        if (!$notification || Gate::denies('view', $notification)) {
            throw ValidationException::withMessages(['notification_id' => 'Notifica non trovata.']);
        }

        return $notification;
    }

    protected function notificationSummary(PushNotification $n): array
    {
        $n->loadMissing('user:id,name');

        return [
            'id' => $n->id,
            'title' => $n->title,
            'body' => $n->body,
            'url' => $n->url,
            'recipient' => $n->user
                ? ['type' => 'user', 'id' => $n->user_id, 'name' => $n->user->name]
                : ['type' => $n->audience],
            'status' => $n->sent_at ? 'inviata' : 'in coda',
            'recipients_count' => $n->recipients_count,
            'created_at' => $n->created_at?->toIso8601String(),
            'sent_at' => $n->sent_at?->toIso8601String(),
        ];
    }
}
