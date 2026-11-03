<?php

namespace App\Mcp\Tools;

use App\Actions\Contacts\SaveContact;
use App\Mcp\Tools\Concerns\NeedsContactsModule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

#[Name('create_contact')]
#[Title('Aggiungi contatto newsletter')]
#[Description('Aggiunge un contatto alla newsletter SMTP di un account, come la scheda Contatti (origine registrata: "mcp"). Usa "active" solo se la persona ha dato il consenso a ricevere la newsletter; altrimenti "unverified". Gli indirizzi disiscritti o in bounce non si possono riattivare.')]
class CreateContact extends Tool
{
    use NeedsContactsModule;

    protected string $ability = 'write';

    public function handle(Request $request, SaveContact $contacts): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            ...SaveContact::rules(),
        ]);

        $contact = $this->guard(fn () => $contacts->create($this->me(), [
            ...$data,
            'user_id' => $data['account_id'] ?? $this->me()->id,
        ], 'mcp'));

        return Response::structured($this->contactSummary($contact));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('Account con newsletter SMTP (vedi whoami). Obbligatorio per admin/manager.'),
            'email' => $schema->string()->format('email')->required(),
            'status' => $schema->string()->enum(SaveContact::STATUSES)->required(),
            'tags' => $schema->array()->items($schema->string())->description('Nomi dei tag (creati se non esistono).'),
        ];
    }
}
