<?php

namespace App\Notifications;

use App\Models\PushNotification;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifica funzionale "post inviato": avvisa chi ha creato il post che la
 * pubblicazione automatica e' andata a buon fine. La riga in coda la inserisce
 * il worker Python dopo aver pubblicato (kind='post_published').
 *
 * A differenza di PushNotificationAlert usa SOLO WebPush, NON il canale
 * 'database': queste notifiche funzionali non devono comparire nella campanella
 * (riservata alle notifiche di prodotto/marketing). Vedi il branch in
 * SendPendingPushNotifications. Niente ShouldQueue per lo stesso motivo
 * spiegato in PushNotificationAlert (parte da cron, non da HTTP).
 */
class PostPublishedAlert extends Notification
{
    public function __construct(private readonly PushNotification $pushNotification)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
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
}
