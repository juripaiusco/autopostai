<?php

namespace App\Mcp\Tools;

use App\Actions\Posts\GeneratePostTextPreview;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('generate_post_text')]
#[Title('Genera testo con l\'AI dell\'account')]
#[Description('Come "Genera anteprima": scrive il testo di un post con la chiave OpenAI, la personalità e le conoscenze AI dell\'account. Non salva nulla: usa il risultato come ai_content in create_post/update_post e passa token_log_id in preview_token_log_ids, così il consumo viene attribuito al post. Consuma token del limite mensile dell\'account.')]
#[IsOpenWorld]
class GeneratePostText extends Tool
{
    protected string $ability = 'write';

    public function handle(Request $request, GeneratePostTextPreview $preview): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'prompt' => ['required', 'string', 'max:10000'],
            'channel' => ['nullable', Rule::in(array_keys(User::CHANNELS))],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(array_keys(User::CHANNELS))],
        ]);

        $account = $this->targetAccount($request);

        $result = $preview->handle($account, $data['prompt'], $data['channel'] ?? null, $data['channels'] ?? []);

        return Response::structured([
            'account_id' => $account->id,
            'content' => $result['content'],
            'tokens_used' => $result['tokens'],
            'token_log_id' => $result['token_log_id'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Account per cui scrivere (vedi whoami). Default: il tuo.'),
            'prompt' => $schema->string()->required()->description('Istruzioni per il testo (diventano ai_prompt_post).'),
            'channel' => $schema->string()->enum(array_keys(User::CHANNELS))->description('Canale principale: wordpress e newsletter producono Markdown.'),
            'channels' => $schema->array()->items($schema->string()->enum(array_keys(User::CHANNELS)))->description('Tutti i canali su cui uscirà il post: con linkedin il testo resta sotto il limite di 3000 caratteri.'),
        ];
    }
}
