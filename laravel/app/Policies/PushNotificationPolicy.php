<?php

namespace App\Policies;

use App\Models\PushNotification;
use App\Models\User;

class PushNotificationPolicy
{
    /**
     * Solo chi ha sotto-utenti (admin o manager) gestisce le notifiche —
     * un simple user le riceve, non le invia.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Admin vede tutto l'archivio, un manager solo cio' che ha creato lui.
     */
    public function view(User $user, PushNotification $notification): bool
    {
        return $user->isAdmin() || $notification->created_by_user_id === $user->id;
    }

    public function delete(User $user, PushNotification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
