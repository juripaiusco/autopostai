<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('delete_post')]
#[Title('Elimina post')]
#[Description('Elimina un post. Se era già uscito, FaPer3 lo rimuove anche dai canali dove è possibile (una newsletter inviata resta inviata). Chiedi sempre conferma esplicita all\'utente prima.')]
#[IsDestructive]
#[IsOpenWorld]
class DeletePost extends Tool
{
    protected string $ability = 'publish';

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['post_id' => ['required', 'integer']]);

        $post = $this->findPost($data['post_id'], 'delete');
        $wasOut = in_array($post->status(), ['published', 'done', 'partial'], true);

        // Come PostController::destroy: soft delete, il worker toglie il post
        // dai canali al giro successivo.
        $post->delete();

        return Response::structured([
            'deleted' => true,
            'post_id' => $post->id,
            'message' => $wasOut
                ? 'Post eliminato: verrà rimosso dai canali entro pochi minuti, dove la piattaforma lo consente.'
                : 'Post eliminato.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
        ];
    }
}
