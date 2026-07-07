<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Admin su tutti i post, manager sui post dei propri sotto-utenti,
     * utente solo sui propri.
     */
    public function view(User $user, Post $post): bool
    {
        return $user->isAdmin()
            || ($user->isManager() && $post->user->parent_id === $user->id)
            || $post->user_id === $user->id;
    }

    public function update(User $user, Post $post): bool
    {
        return $this->view($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->view($user, $post);
    }
}
