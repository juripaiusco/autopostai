<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\UpdatePublishedChannelContent;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('update_published_post')]
#[Title('Modifica post già pubblicato')]
#[Description('Sostituisce il testo di un post già uscito su Facebook o WordPress (le altre piattaforme non lo consentono): la modifica arriva online entro un minuto. Mostra il nuovo testo all\'utente e chiedi conferma prima.')]
#[IsDestructive]
#[IsOpenWorld]
class UpdatePublishedPost extends Tool
{
    protected string $ability = 'publish';

    public function handle(Request $request, UpdatePublishedChannelContent $updateContent): ResponseFactory
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer'],
            'channel' => ['required', Rule::in(Post::EDITABLE_AFTER_PUBLISH)],
            ...UpdatePublishedChannelContent::rules(),
        ]);

        $post = $this->findPost($data['post_id'], 'update');

        // Via MCP il testo WordPress è Markdown (come ai_content in create_post).
        $this->guard(fn () => $updateContent->handle($post, $data['channel'], $data['content'], Post::FORMAT_MARKDOWN));

        return Response::structured([
            'post_id' => $post->id,
            'channel' => $data['channel'],
            'message' => 'Modifica in coda: il canale viene aggiornato entro un minuto. L\'esito è in get_post (channels.*.update_pending / update_error).',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
            'channel' => $schema->string()->enum(Post::EDITABLE_AFTER_PUBLISH)->required(),
            'content' => $schema->string()->required()->description('Nuovo testo completo per quel canale: Markdown per WordPress (prima riga `#` = titolo), testo semplice per Facebook. Shortcode come in create_post.'),
        ];
    }
}
