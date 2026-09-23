<?php

namespace App\Console\Commands;

use App\Models\PushNotification;
use App\Notifications\PostPublishedAlert;
use App\Notifications\PushNotificationAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notifiable;
use Throwable;

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
     *
     * Al più una volta: la riga viene "presa" (sent_at) PRIMA di inviare, con un
     * update condizionato che solo un processo può vincere. Prima sent_at si
     * scriveva a fine giro: un errore o un run lento/sovrapposto rimandava la
     * notifica a tutti. Meglio perdere una notifica che mandarla due volte.
     */
    public function handle(): void
    {
        $pending = PushNotification::pending()->get();

        if ($pending->isEmpty()) {
            $this->info('Nessuna notifica in coda.');

            return;
        }

        foreach ($pending as $notification) {
            $claimed = PushNotification::whereKey($notification->id)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);
            if (!$claimed) {
                continue; // presa da un altro run nel frattempo
            }

            $recipients = $notification->resolveRecipients();

            $notificationClass = $notification->kind === 'post_published'
                ? PostPublishedAlert::class
                : PushNotificationAlert::class;

            // Un destinatario che fallisce non blocca gli altri né fa ripartire
            // l'invio: l'errore va nel log, il conteggio riporta solo i riusciti.
            $delivered = 0;
            /** @var Notifiable $recipient */
            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify(new $notificationClass($notification));
                    $delivered++;
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $notification->update(['recipients_count' => $delivered]);

            $this->info("Notifica #{$notification->id} inviata a {$delivered}/{$recipients->count()} destinatari.");
        }
    }
}
