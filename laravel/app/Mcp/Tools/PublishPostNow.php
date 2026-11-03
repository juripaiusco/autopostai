<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\UpdatePost as UpdatePostAction;
use App\Mcp\Tools\Concerns\WritesPosts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('publish_post_now')]
#[Title('Pubblica subito')]
#[Description('Pubblica subito una bozza o un post programmato: esce entro un minuto su tutti i suoi canali. Irreversibile (una newsletter inviata non si richiama): prima mostra all\'utente post, canali e testo e chiedi conferma esplicita.')]
#[IsDestructive]
#[IsOpenWorld]
class PublishPostNow extends Tool
{
    use WritesPosts;

    protected string $ability = 'publish';

    public function handle(Request $request, UpdatePostAction $updatePost): ResponseFactory
    {
        $data = $request->validate(['post_id' => ['required', 'integer']]);

        $post = $this->findPost($data['post_id'], 'update');
        $this->guard(fn () => $updatePost->ensureEditable($post));

        $channels = $this->currentFormChannels($post);
        if ($channels === []) {
            throw ValidationException::withMessages(['post_id' => 'Il post non ha canali attivi.']);
        }
        if (blank($post->ai_content) && blank($post->ai_prompt_post)) {
            throw ValidationException::withMessages(['post_id' => 'Il post non ha né testo né prompt: aggiungili con update_post.']);
        }
        $this->ensureInstagramHasImages($channels, count($post->img ?? []));

        // Stesso percorso di "Pubblica subito" dal form di modifica: la data
        // la mette il server (now), il worker lo prende al giro successivo.
        $this->guard(fn () => $updatePost->handle($post, [
            'title' => $post->title ?? '',
            'channels' => $channels,
            'ai_prompt_post' => $post->ai_prompt_post,
            'ai_content' => $post->ai_content,
            'ai_prompt_comment' => $post->ai_prompt_comment,
            'img_ai_check_on' => $post->img_ai_check_on == '1',
            'action' => 'publish_now',
        ]));

        return Response::structured([
            ...$this->postSummary($post),
            'message' => 'Post in pubblicazione: esce entro un minuto. Controlla lo stato con get_post.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required(),
        ];
    }
}
