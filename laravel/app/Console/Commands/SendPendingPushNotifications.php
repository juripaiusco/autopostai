<?php

namespace App\Console\Commands;

use App\Models\PushNotification;
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
     * sia il futuro servizio Python creano/scrivono righe 'push_notifications' con
     * sent_at nullo — questo comando (schedulato ogni minuto) le processa e le manda.
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

            /** @var Notifiable $recipient */
            foreach ($recipients as $recipient) {
                $recipient->notify(new PushNotificationAlert($notification));
            }

            $notification->update([
                'sent_at' => now(),
                'recipients_count' => $recipients->count(),
            ]);

            $this->info("Notifica #{$notification->id} inviata a {$recipients->count()} destinatari.");
        }
    }
}
