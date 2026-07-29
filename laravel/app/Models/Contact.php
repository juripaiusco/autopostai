<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'email',
    'status',
    'consent_source',
    'consent_ip',
    'consent_at',
    'unsubscribed_at',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_contact_tag');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(EmailSend::class);
    }

    /**
     * Contatti visibili a $me: stessa logica di Post::scopeVisibleTo — se
     * $scopedUserId è valorizzato (scope globale attivo su un altro utente)
     * mostra solo i suoi contatti, altrimenti amministratore -> tutti,
     * manager -> propri sotto-utenti, utente -> i propri.
     */
    public function scopeVisibleTo(Builder $query, User $me, ?int $scopedUserId = null): Builder
    {
        if ($scopedUserId !== null) {
            return $query->where('user_id', $scopedUserId);
        }

        if ($me->isAdmin()) {
            return $query;
        }

        if ($me->isManager()) {
            return $query->whereIn('user_id', $me->children()->pluck('id'));
        }

        return $query->where('user_id', $me->id);
    }
}
