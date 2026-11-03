<?php

namespace App\Mcp\Tools;

use App\Services\ImageArchive;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_image_archive')]
#[Title('Archivio immagini AI')]
#[Description('Immagini già generate con l\'AI per un account, dalla più recente, con il prompt usato: il filename si usa in archive_images di create_post/update_post.')]
#[IsReadOnly]
class ListImageArchive extends Tool
{
    public function handle(Request $request, ImageArchive $archive): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $account = $this->targetAccount($request);
        $images = $archive->images($account, $this->me());

        return Response::structured([
            'account_id' => $account->id,
            'total' => $images->count(),
            'images' => $images->take($data['limit'] ?? 30)->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Account (vedi whoami). Default: il tuo.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Default: 30.'),
        ];
    }
}
