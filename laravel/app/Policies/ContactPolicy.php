<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Stessa regola di PostPolicy: admin su tutti i contatti, manager su
     * quelli dei propri sotto-utenti, utente solo sui propri.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->isAdmin()
            || ($user->isManager() && $contact->user->parent_id === $user->id)
            || $contact->user_id === $user->id;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->view($user, $contact);
    }
}
