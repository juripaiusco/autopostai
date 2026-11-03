<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\WritesPushNotifications;
use App\Models\PushNotification;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_push_notifications')]
#[Title('Notifiche push')]
#[Description('Archivio delle notifiche push inviate o in coda agli utenti di FaPer3 (admin: tutte, manager: le proprie), più a chi si possono mandare.')]
#[IsReadOnly]
class ListPushNotifications extends Tool
{
    use WritesPushNotifications;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $me = $this->me();
        $notifications = PushNotification::query()
            ->visibleTo($me)
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page', $data['page'] ?? 1);

        return Response::structured([
            'page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'notifications' => collect($notifications->items())->map(fn ($n) => $this->notificationSummary($n))->all(),
            'can_send_to' => [
                'all' => $me->isAdmin(),
                'children' => $me->isManager(),
                'users' => $me->filterableUsers()->map(fn ($u) => ['id' => $u['id'], 'name' => $u['name']])->values()->all(),
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1),
        ];
    }
}
