<?php

namespace App\Mcp\Tools;

use App\Models\Contact;
use App\Models\ContactTag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_contacts')]
#[Title('Contatti newsletter')]
#[Description('Contatti della newsletter SMTP (modulo Contatti): conteggi per stato, filtro per stato/tag, ricerca per e-mail, paginazione. Disponibile solo per gli account con newsletter SMTP attiva.')]
#[IsReadOnly]
class ListContacts extends Tool
{
    private const STATUSES = ['unverified', 'active', 'bounced', 'unsubscribed'];

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'tag' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $me = $this->me();
        $scopedId = $this->scopedAccountId($request);

        // Stesso controllo della pagina Contatti (ContactController::index).
        if (!$me->canViewContacts($scopedId)) {
            throw ValidationException::withMessages(['account_id' => 'Il modulo Contatti non è attivo per questo account (serve la newsletter SMTP).']);
        }

        $scope = fn () => Contact::query()->visibleTo($me, $scopedId);

        $query = $scope()->with(['tags', 'user:id,name']);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (($data['search'] ?? '') !== '') {
            $query->where('email', 'like', '%'.$data['search'].'%');
        }
        if (($data['tag'] ?? '') !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('name', $data['tag']));
        }

        $contacts = $query->orderByDesc('created_at')
            ->paginate($data['per_page'] ?? 50, ['*'], 'page', $data['page'] ?? 1);

        return Response::structured([
            'counts' => collect(self::STATUSES)
                ->mapWithKeys(fn ($s) => [$s => $scope()->where('status', $s)->count()])
                ->put('total', $scope()->count())
                ->all(),
            'available_tags' => ContactTag::whereIn('user_id', $scope()->select('user_id'))->orderBy('name')->pluck('name')->unique()->values()->all(),
            'page' => $contacts->currentPage(),
            'last_page' => $contacts->lastPage(),
            'contacts' => collect($contacts->items())->map(fn (Contact $c) => [
                'id' => $c->id,
                'email' => $c->email,
                'status' => $c->status,
                'tags' => $c->tags->pluck('name')->all(),
                'account' => ['id' => $c->user_id, 'name' => $c->user?->name],
                'consent_source' => $c->consent_source,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $this->accountIdSchema($schema),
            'status' => $schema->string()->enum(self::STATUSES),
            'tag' => $schema->string()->description('Nome del tag.'),
            'search' => $schema->string()->description("Parte dell'indirizzo e-mail."),
            'page' => $schema->integer()->min(1),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Default: 50.'),
        ];
    }
}
