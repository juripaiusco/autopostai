<?php

namespace App\Mcp\Tools;

use App\Actions\Contacts\SaveContact;
use App\Mcp\Tools\Concerns\NeedsContactsModule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_contact')]
#[Title('Modifica contatto newsletter')]
#[Description('Modifica email, nome, cognome, stato o tag di un contatto (id da list_contacts). Aggiornamento parziale: i campi omessi restano invariati; tags, se presente, sostituisce tutti i tag.')]
#[IsIdempotent]
class UpdateContact extends Tool
{
    use NeedsContactsModule;

    protected string $ability = 'write';

    public function handle(Request $request, SaveContact $contacts): ResponseFactory
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
            'email' => ['sometimes', 'email', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(SaveContact::STATUSES)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:255'],
        ]);

        $contact = $this->findContact($data['contact_id'], 'update');

        $contacts->update($contact, [
            'email' => $data['email'] ?? $contact->email,
            'first_name' => array_key_exists('first_name', $data) ? $data['first_name'] : $contact->first_name,
            'last_name' => array_key_exists('last_name', $data) ? $data['last_name'] : $contact->last_name,
            'status' => $data['status'] ?? $contact->status,
            'tags' => $data['tags'] ?? $contact->tags->pluck('name')->all(),
        ]);

        return Response::structured($this->contactSummary($contact));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->required(),
            'email' => $schema->string()->format('email'),
            'first_name' => $schema->string(),
            'last_name' => $schema->string(),
            'status' => $schema->string()->enum(SaveContact::STATUSES),
            'tags' => $schema->array()->items($schema->string()),
        ];
    }
}
