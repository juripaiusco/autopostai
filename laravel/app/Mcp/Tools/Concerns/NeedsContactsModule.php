<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Tool del modulo Contatti: visibili solo a chi lo vede in interfaccia
 * (User::canViewContacts — admin/manager, o utente con newsletter SMTP).
 */
trait NeedsContactsModule
{
    public function shouldRegister(): bool
    {
        return parent::shouldRegister() && $this->me()->canViewContacts();
    }

    /**
     * Contatto visibile e autorizzato per $ability (ContactPolicy). Stesso
     * messaggio per inesistente e non visibile.
     */
    protected function findContact(mixed $id, string $ability = 'view'): Contact
    {
        $contact = Contact::with('tags')->find((int) $id);

        if (!$contact || Gate::denies('view', $contact)) {
            throw ValidationException::withMessages(['contact_id' => 'Contatto non trovato.']);
        }

        if ($ability !== 'view' && Gate::denies($ability, $contact)) {
            throw ValidationException::withMessages(['contact_id' => 'Non hai i permessi per questa operazione su questo contatto.']);
        }

        return $contact;
    }

    protected function contactSummary(Contact $contact): array
    {
        $contact->load('tags');

        return [
            'id' => $contact->id,
            'account_id' => $contact->user_id,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'status' => $contact->status,
            'tags' => $contact->tags->pluck('name')->all(),
            'consent_source' => $contact->consent_source,
        ];
    }
}
