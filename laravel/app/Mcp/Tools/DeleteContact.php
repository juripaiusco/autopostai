<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\NeedsContactsModule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_contact')]
#[Title('Elimina contatto newsletter')]
#[Description('Elimina un contatto (come il cestino della scheda Contatti). Per chi non vuole più ricevere la newsletter è meglio update_contact con status "unsubscribed". Chiedi conferma all\'utente prima.')]
#[IsDestructive]
class DeleteContact extends Tool
{
    use NeedsContactsModule;

    protected string $ability = 'write';

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate(['contact_id' => ['required', 'integer']]);

        $contact = $this->findContact($data['contact_id'], 'delete');
        $contact->delete();

        return Response::structured(['deleted' => true, 'contact_id' => $contact->id, 'email' => $contact->email]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->required(),
        ];
    }
}
