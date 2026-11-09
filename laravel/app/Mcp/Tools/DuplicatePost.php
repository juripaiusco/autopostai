<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\DuplicatePost as DuplicatePostAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('duplicate_post')]
#[Title('Duplica post')]
#[Description('Come "Duplica" nella lista post: copia un post (anche già pubblicato) come nuova bozza dello stesso account, con titolo "… (copia)", prompt, testo, immagini e canali, ma senza data di invio. Poi usa update_post sul nuovo post_id per data e modifiche: finché non ha una data non esce.')]
class DuplicatePost extends Tool
{
    protected string $ability = 'write';

    public function handle(Request $request, DuplicatePostAction $duplicatePost): ResponseFactory
    {
        $data = $request->validate(['post_id' => ['required', 'integer']]);

        $post = $this->findPost($data['post_id'], 'update');

        $copy = $this->guard(fn () => $duplicatePost->handle($this->me(), $post));

        return Response::structured([
            'post_id' => $copy->id,
            'source_post_id' => $post->id,
            'title' => $copy->title,
            'status' => $copy->status(),
            'message' => 'Post duplicato come bozza senza data: imposta published_at con update_post per programmarlo.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
        ];
    }
}
