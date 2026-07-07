<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Lista sotto-utenti: solo chi ne ha (admin o manager).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Admin vede/gestisce chiunque, un manager solo i propri figli diretti.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $model->parent_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() || $model->parent_id === $user->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() || $model->parent_id === $user->id;
    }
}
