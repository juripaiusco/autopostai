<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\WritesPushNotifications;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_push_notification')]
#[Title('Elimina notifica push')]
#[Description('Elimina una notifica: se è ancora in coda non verrà inviata, se è già inviata sparisce solo dall\'archivio.')]
#[IsDestructive]
class DeletePushNotification extends Tool
{
    use WritesPushNotifications;

    protected string $ability = 'publish';

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['notification_id' => ['required', 'integer']]);

        $notification = $this->findNotification($data['notification_id']);
        Gate::authorize('delete', $notification);
        $notification->delete();

        return Response::structured(['deleted' => true, 'notification_id' => $notification->id]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'notification_id' => $schema->integer()->required(),
        ];
    }
}
