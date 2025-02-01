<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, User $targetUser): bool
    {
                // Se l'utente è il proprietario del profilo
        return  $targetUser->id === $user->id ||

                // Se l'utente è manager può vedere i suoi sotto utenti
                ($user->parent_id && $user->child_on === 1 && $user->children->contains($targetUser->id)) ||

                // Se l'utente è admin può vedere tutto
                (!$user->parent_id && !$user->child_on);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
                // Se l'utente è manager può vedere i suoi sotto utenti
        return  ($user->parent_id && $user->child_on === 1) ||

                // Se l'utente è admin può vedere tutto
                (!$user->parent_id && !$user->child_on);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
