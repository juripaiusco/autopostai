<?php

namespace App\Mcp\Tools;

use App\Actions\Images\StartImageGeneration;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('generate_image')]
#[Title('Genera immagine con l\'AI')]
#[Description('Avvia la generazione di un\'immagine con la chiave OpenAI dell\'account (conta nel limite giornaliero). È asincrona: controlla get_image_job ogni 10-20 secondi finché non è "completed", poi allega il filename con archive_images.')]
#[IsOpenWorld]
class GenerateImage extends Tool
{
    protected string $ability = 'write';

    public function handle(Request $request, StartImageGeneration $startGeneration): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'prompt' => StartImageGeneration::rules()['prompt'],
            'model' => ['nullable', 'string', Rule::in(array_keys(config('ai.image_models')))],
        ]);

        $account = $this->targetAccount($request);
        $model = $data['model'] ?? config('ai.default_image_model');

        $job = $startGeneration->handle($account, $this->me(), $data['prompt'], $model);

        return Response::structured([
            'job_id' => $job->id,
            'account_id' => $account->id,
            'model' => $model,
            'status' => $job->status,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Account per cui generare (vedi whoami). Default: il tuo.'),
            'prompt' => $schema->string()->required()->max(2000)->description('Descrizione dell\'immagine.'),
            'model' => $schema->string()->enum(array_keys(config('ai.image_models')))
                ->description(collect(config('ai.image_models'))->map(fn ($m, $id) => "{$id}: {$m['description']}")->implode('; ').'. Default: '.config('ai.default_image_model').'.'),
        ];
    }
}
