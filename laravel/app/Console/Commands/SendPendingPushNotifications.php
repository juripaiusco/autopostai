<?php

namespace App\Console\Commands;

use App\Models\PushNotification;
use App\Notifications\PostPublishedAlert;
use App\Notifications\PushNotificationAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notifiable;

#[Signature('notifications:send-pending')]
#[Description('Invia le notifiche push in coda (create da UI o, in futuro, dal servizio Python)')]
class SendPendingPushNotifications extends Command
{
    /**
     * Execute the console command.
     *
     * Nessun endpoint HTTP pubblico per questo invio: sia la UI (Notifications/Form)
     * sia il servizio Python di pubblicazione creano/scrivono righe 'push_notifications'
     * con sent_at nullo — questo comando (schedulato ogni minuto) le processa e le manda.
     *
     * La colonna 'kind' sceglie la classe Notification, cosi' i due tipi non si mischiano:
     *  - 'post_published' => PostPublishedAlert (solo WebPush, NON in campanella)
     *  - tutto il resto    => PushNotificationAlert (database + WebPush, in campanella)
     */
    public function handle(): void
    {
        $pending = PushNotification::pending()->get();

        if ($pending->isEmpty()) {
            $this->info('Nessuna notifica in coda.');

            return;
        }

        foreach ($pending as $notification) {
            $recipients = $notification->resolveRecipients();

            $notificationClass = $notification->kind === 'post_published'
                ? PostPublishedAlert::class
                : PushNotificationAlert::class;

            /** @var Notifiable $recipient */
            foreach ($recipients as $recipient) {
                $recipient->notify(new $notificationClass($notification));
            }

            $notification->update([
                'sent_at' => now(),
                'recipients_count' => $recipients->count(),
            ]);

            $this->info("Notifica #{$notification->id} inviata a {$recipients->count()} destinatari.");
        }
    }
}
