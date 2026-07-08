<?php

namespace App\Notifications;

use App\Models\PushNotification;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Niente ShouldQueue: l'invio parte dal comando schedulato
 * notifications:send-pending (cron, non da una richiesta HTTP utente), quindi
 * non c'e' nulla da "non bloccare" — e in questo ambiente non gira nessun
 * queue:work, percio' una notifica in coda restava bloccata per sempre.
 */
class PushNotificationAlert extends Notification
{
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
