<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class PushSubscription extends Model
{
    use Notifiable;

    protected $fillable = ['endpoint', 'publicKey', 'authToken', 'content_encoding'];

    public function subscribable()
    {
        return $this->morphTo();
    }

    public function routeNotificationForWebPush()
    {
        Log::info('Subscriptions per user ' . $this->id, ['subs' => $this->pushSubscriptions]);

        // Verifica che tutti i dati necessari siano presenti
        if (!$this->endpoint || !$this->publicKey || !$this->authToken) {
            return null;
        }

        // Restituisce un array invece di un oggetto WebPushSubscription
        return [
            'endpoint' => $this->endpoint,
            'publicKey' => $this->publicKey,
            'authToken' => $this->authToken,
            'content_encoding' => $this->content_encoding ?? 'aes128gcm',
            'exception' => function ($exception) {
                // Gestione dell'eccezione, se necessario
                Log::error('WebPush subscription error: ' . $exception->getMessage());
            }
        ];
    }
}
