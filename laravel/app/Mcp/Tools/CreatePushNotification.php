<?php

namespace App\Mcp\Tools;

use App\Actions\Notifications\SavePushNotification;
use App\Mcp\Tools\Concerns\WritesPushNotifications;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('create_push_notification')]
#[Title('Invia notifica push')]
#[Description('Mette in coda una notifica push per gli utenti di FaPer3: parte entro un minuto e arriva sui loro dispositivi. Destinatari: "all" (solo admin), "children" (i propri utenti, solo manager) o "user" con user_id (vedi list_push_notifications.can_send_to). Mostra testo e destinatari all\'utente e chiedi conferma prima.')]
#[IsOpenWorld]
class CreatePushNotification extends Tool
{
    use WritesPushNotifications;

    protected string $ability = 'publish';

    public function handle(Request $request, SavePushNotification $notifications): ResponseFactory
    {
        $data = $request->validate([
            ...SavePushNotification::rules(),
            'body' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:2048', $this->internalUrlRule()],
        ]);

        $notification = $this->guard(fn () => $notifications->create($this->me(), $data));

        return Response::structured($this->notificationSummary($notification));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->max(255),
            'body' => $schema->string()->required()->max(1000),
            'url' => $schema->string()->description('Pagina di FaPer3 da aprire al tocco, es. "/posts".'),
            'recipient_type' => $schema->string()->enum(['all', 'children', 'user'])->required(),
            'user_id' => $schema->integer()->description('Solo con recipient_type "user".'),
        ];
    }
}
