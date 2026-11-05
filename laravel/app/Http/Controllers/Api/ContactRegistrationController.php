<?php

namespace App\Http\Controllers\Api;

use App\Actions\Contacts\SaveContact;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\User;
use App\Rules\ValidMxRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactRegistrationController extends Controller
{
    /**
     * Registrazione contatto server-to-server (Step 5): niente form pubblico
     * né captcha — autenticazione via API key (ApiKeyAuth), limite di 60
     * richieste/minuto per account (limiter `contacts-api`), validazione
     * payload.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $account */
        $account = $request->attributes->get('contactsAccount');

        $data = $request->validate([
            'email' => ['required', 'email:rfc', new ValidMxRecord],
            'consent_source' => ['nullable', 'string', 'max:255'],
            // IP dell'iscritto (lo conosce il sito che raccoglie il consenso):
            // $request->ip() qui è quello del server chiamante, non una prova
            // di consenso GDPR valida. Resta il fallback se non inviato.
            'consent_ip' => ['nullable', 'ip'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:20'],
            // nullable: uno script che manda un tag vuoto non deve far
            // fallire la registrazione, i vuoti vengono scartati.
            'tags.*' => ['nullable', 'string', 'max:255'],
        ]);

        $email = $data['email'];
        $names = SaveContact::names($data);

        $suppressed = SuppressionList::where('user_id', $account->id)
            ->where('email', $email)
            ->first();

        if ($suppressed && in_array($suppressed->reason, ['hard_bounce', 'complaint'], true)) {
            return response()->json([
                'message' => 'Email non registrabile: presente in suppression list.',
                'reason' => $suppressed->reason,
            ], 422);
        }

        $status = $suppressed?->reason === 'unsubscribe' ? 'unsubscribed' : 'active';

        $existing = Contact::withTrashed()
            ->where('user_id', $account->id)
            ->where('email', $email)
            ->first();

        if ($existing) {
            // A7: un contatto già `bounced` (casella inesistente) vale come un
            // hard_bounce in suppression list anche se la riga lì manca: non
            // si tocca. Un `unsubscribed` resta tale: la nuova registrazione
            // non annulla una disiscrizione (stessa regola della suppression).
            if ($existing->status === 'bounced') {
                return response()->json([
                    'message' => 'Email non registrabile: indirizzo inesistente (bounce).',
                    'reason' => 'hard_bounce',
                ], 422);
            }
            if ($existing->status === 'unsubscribed') {
                $status = 'unsubscribed';
            }

            $wasTrashed = $existing->trashed();
            if ($wasTrashed) {
                $existing->restore();
            }
            // Nome/cognome solo se inviati: un POST con la sola email non
            // cancella quelli già salvati (da UI o da un invio precedente).
            $existing->update([
                'status' => $status,
                ...array_filter($names, fn ($v) => $v !== null),
            ]);
            $this->addTags($existing, $data['tags'] ?? []);

            return response()->json([
                'status' => $wasTrashed ? 'restored' : 'exists',
                'contact' => $this->payload($existing),
            ], 200);
        }

        $contact = Contact::create([
            'user_id' => $account->id,
            'email' => $email,
            ...$names,
            'status' => $status,
            'consent_source' => $data['consent_source'] ?? 'api',
            'consent_ip' => $data['consent_ip'] ?? $request->ip(),
            'consent_at' => now(),
        ]);
        $this->addTags($contact, $data['tags'] ?? []);

        return response()->json([
            'status' => $status === 'unsubscribed' ? 'suppressed_unsubscribed' : 'created',
            'contact' => $this->payload($contact),
        ], 201);
    }

    /**
     * Tag aggiunti, mai tolti: quelli assegnati da UI restano. I tag che
     * l'account non ha ancora vengono creati.
     */
    private function addTags(Contact $contact, array $names): void
    {
        $ids = app(SaveContact::class)->tagIds($contact->user_id, $names);
        if ($ids !== []) {
            $contact->tags()->syncWithoutDetaching($ids);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'status' => $contact->status,
            'tags' => $contact->tags()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
