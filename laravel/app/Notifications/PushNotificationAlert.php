<?php

namespace App\Notifications;

use App\Models\PushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A differenza di v1, questa e' in coda (ShouldQueue): l'invio a tanti
 * destinatari non deve bloccare la richiesta che l'ha generata.
 */
class PushNotificationAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PushNotification $pushNotification)
    {
    }

    public function via(object $notifiable): array
    {
        // 'database' da' gratis il tracking di lettura per-notifica
        // (read_at), molto piu' preciso del flag unico per-utente di v1.
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->pushNotification->title)
            ->body($this->pushNotification->body)
            ->action('Apri', 'open')
            ->data(['url' => $this->pushNotification->url])
            ->options(['TTL' => 1000]);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->pushNotification->title,
            'body' => $this->pushNotification->body,
            'url' => $this->pushNotification->url,
        ];
    }
}
