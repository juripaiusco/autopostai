<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['created_by_user_id', 'user_id', 'audience', 'title', 'body', 'url', 'sent_at', 'recipients_count'])]
class PushNotification extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('sent_at');
    }

    /**
     * Visibili a $me: l'admin vede tutte le notifiche create, il manager solo
     * quelle create da se' stesso (stessa idea di Post::visibleTo).
     */
    public function scopeVisibleTo(Builder $query, User $me): Builder
    {
        if ($me->isAdmin()) {
            return $query;
        }

        return $query->where('created_by_user_id', $me->id);
    }

    /**
     * Destinatari effettivi al momento dell'invio: un utente specifico,
     * tutti gli utenti (solo audience 'all'), oppure i soli sotto-utenti
     * di chi ha creato la notifica (audience 'children').
     */
    public function resolveRecipients(): EloquentCollection
    {
        if ($this->user_id) {
            return User::query()->whereKey($this->user_id)->get();
        }

        if ($this->audience === 'all') {
            return User::query()->where('id', '!=', $this->created_by_user_id)->get();
        }

        if ($this->audience === 'children') {
            return User::query()->where('parent_id', $this->created_by_user_id)->get();
        }

        return new EloquentCollection();
    }
}
