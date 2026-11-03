<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\RetryPost as RetryPostAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('retry_post')]
#[Title('Riprova pubblicazione')]
#[Description('Come il pulsante "Riprova": rimette in coda i canali in errore di un post (vedi get_post, channels.*.error), che il worker ritenta entro un minuto. Ripubblica verso l\'esterno: chiedi conferma all\'utente.')]
#[IsOpenWorld]
class RetryPost extends Tool
{
    protected string $ability = 'publish';

    public function handle(Request $request, RetryPostAction $retryPost): ResponseFactory
    {
        $data = $request->validate(['post_id' => ['required', 'integer']]);

        $post = $this->findPost($data['post_id'], 'update');
        $channels = array_keys($post->channelErrors());

        $this->guard(fn () => $retryPost->handle($post));

        return Response::structured([
            'post_id' => $post->id,
            'retried_channels' => $channels,
            'message' => 'Nuovo tentativo in coda: il worker riprova entro un minuto.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
        ];
    }
}
