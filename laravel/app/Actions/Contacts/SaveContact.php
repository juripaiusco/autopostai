<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\SuppressionList;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creazione e modifica di un contatto della newsletter SMTP: scheda
 * Contatti e tool MCP. L'autorizzazione sul contatto esistente
 * (ContactPolicy::update) la fa il chiamante.
 */
class SaveContact
{
    public const STATUSES = ['unverified', 'active', 'bounced', 'unsubscribed'];

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Account su cui $me può creare/gestire contatti: solo quelli con
     * provider smtp_custom attivo (stesso controllo della visibilità del
     * modulo) — niente senso ad anagrafiche per account su Mailchimp/Brevo.
     */
    public function eligibleAccounts(User $me): Collection
    {
        if ($me->isAdmin()) {
            $query = User::whereNotNull('parent_id')->whereNull('child_on');
        } elseif ($me->isManager()) {
            $query = User::where('parent_id', $me->id);
        } else {
            return $me->hasSmtpCustomActive() ? collect([$me]) : collect();
        }

        return $query->get()->filter(fn (User $u) => $u->hasSmtpCustomActive())->values();
    }

    /**
     * Nuovo contatto per l'account $data['user_id']. Un contatto eliminato
     * (soft delete) con la stessa email viene ripristinato invece di
     * duplicarlo. $consentSource dice da dove arriva ("manuale" dalla
     * scheda, "mcp" da un assistente AI).
     */
    public function create(User $actor, array $data, string $consentSource = 'manuale'): Contact
    {
        abort_unless($this->eligibleAccounts($actor)->pluck('id')->contains((int) $data['user_id']), 422, 'Account non valido.');

        $userId = (int) $data['user_id'];

        $this->ensureNotSuppressed($userId, $data['email'], $data['status']);

        $existing = Contact::withTrashed()
            ->where('user_id', $userId)
            ->where('email', $data['email'])
            ->first();

        if ($existing) {
            if (!$existing->trashed()) {
                throw ValidationException::withMessages(['email' => 'Contatto già presente per questo account.']);
            }
            $existing->restore();
            $existing->update([
                'status' => $data['status'],
                ...self::names($data),
            ]);
            $contact = $existing;
        } else {
            $contact = Contact::create([
                'user_id' => $userId,
                'email' => $data['email'],
                ...self::names($data),
                'status' => $data['status'],
                'consent_source' => $consentSource,
                'consent_at' => now(),
            ]);
        }

        $this->syncTags($contact, $userId, $data['tags'] ?? []);

        return $contact;
    }

    public function update(Contact $contact, array $data): Contact
    {
        $duplicate = Contact::where('user_id', $contact->user_id)
            ->where('email', $data['email'])
            ->whereKeyNot($contact->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['email' => 'Esiste già un altro contatto con questa email per questo account.']);
        }

        $this->ensureNotSuppressed($contact->user_id, $data['email'], $data['status']);

        $contact->update([
            'email' => $data['email'],
            ...self::names($data),
            'status' => $data['status'],
        ]);

        $this->syncTags($contact, $contact->user_id, $data['tags'] ?? []);

        return $contact;
    }

    /**
     * Nome e cognome facoltativi: stringa vuota salvata come null.
     *
     * @return array{first_name: ?string, last_name: ?string}
     */
    public static function names(array $data): array
    {
        $clean = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;

        return [
            'first_name' => $clean($data['first_name'] ?? null),
            'last_name' => $clean($data['last_name'] ?? null),
        ];
    }

    /**
     * Un indirizzo in suppression list (disiscritto, bounce, segnalato come
     * spam) non torna "attivo" da una modifica manuale: stessa regola
     * dell'API di registrazione. Il worker lo escluderebbe comunque
     * dall'invio, ma la scheda mostrerebbe un contatto attivo che non riceve.
     */
    private function ensureNotSuppressed(int $userId, string $email, string $status): void
    {
        if (!in_array($status, ['active', 'unverified'], true)) {
            return;
        }

        $reason = SuppressionList::where('user_id', $userId)->where('email', $email)->value('reason');
        if ($reason === null) {
            return;
        }

        $label = match ($reason) {
            'unsubscribe' => 'si è disiscritto',
            'hard_bounce' => 'l\'indirizzo non esiste (bounce)',
            'complaint' => 'ha segnalato le email come spam',
            default => $reason,
        };

        throw ValidationException::withMessages([
            'status' => "Questo indirizzo non può essere riattivato: {$label}.",
        ]);
    }

    private function syncTags(Contact $contact, int $userId, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => ContactTag::firstOrCreate(['user_id' => $userId, 'name' => $name])->id);

        $contact->tags()->sync($ids);
    }
}
