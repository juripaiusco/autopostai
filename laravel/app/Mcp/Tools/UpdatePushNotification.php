<?php

namespace App\Mcp\Tools;

use App\Actions\Notifications\SavePushNotification;
use App\Mcp\Tools\Concerns\WritesPushNotifications;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_push_notification')]
#[Title('Modifica notifica push in coda')]
#[Description('Modifica una notifica push non ancora inviata. Aggiornamento parziale: i campi omessi restano invariati.')]
#[IsIdempotent]
class UpdatePushNotification extends Tool
{
    use WritesPushNotifications;

    protected string $ability = 'publish';

    public function handle(Request $request, SavePushNotification $notifications): ResponseFactory
    {
        $data = $request->validate([
            'notification_id' => ['required', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:1000'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048', $this->internalUrlRule()],
            'recipient_type' => ['sometimes', Rule::in(['all', 'children', 'user'])],
            'user_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $notification = $this->findNotification($data['notification_id']);
        $current = [
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'recipient_type' => $notification->user_id ? 'user' : $notification->audience,
            'user_id' => $notification->user_id,
        ];

        $this->guard(fn () => $notifications->update($this->me(), $notification, [
            ...$current,
            ...collect($data)->except('notification_id')->all(),
        ]));

        return Response::structured($this->notificationSummary($notification->fresh()));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'notification_id' => $schema->integer()->required(),
            'title' => $schema->string()->max(255),
            'body' => $schema->string()->max(1000),
            'url' => $schema->string()->nullable(),
            'recipient_type' => $schema->string()->enum(['all', 'children', 'user']),
            'user_id' => $schema->integer()->nullable(),
        ];
    }
}
