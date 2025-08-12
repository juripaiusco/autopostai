<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class TestPushNotification extends Notification // implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    /**
     * Specifica i canali di notifica.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        /*Log::info('Subscription:', [
            'webpush' => $notifiable->routeNotificationFor('webpush')
        ]);*/

        return [WebPushChannel::class];
    }

    /**
     * Costruisce il messaggio per la notifica push.
     *
     * @param  mixed  $notifiable
     * @return \NotificationChannels\WebPush\WebPushMessage
     */
    public function toWebPush($notifiable, $notification)
    {
        /*$data = json_encode([
            "title" => "New Update!",
            "body" => "Check out the latest article now.",
            "icon" => "/icon.png",
            "click_action" => "https://yourwebsite.com"
        ]);

        $ch = curl_init("https://fcm.googleapis.com/fcm/send");

            curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=YOUR_SERVER_KEY',
            'Content-Type: application/json'
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_exec($ch);
        curl_close($ch);*/

        /*Log::info('toWebPush:', [
            'notifiable' => $notifiable,
            'notification' => $notification
        ]);*/

        return (new WebPushMessage)
            ->title('Ciao dal server! 👋')
            ->icon('/faper3-logo.png')
            ->body('Questa è una notifica di test inviata da FaPer3.')
            ->data(['url' => '/'])
            ->action('Vedi', 'view_details');
    }
}
