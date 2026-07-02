<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'parent_id', 'child_on', 'child_max', 'tokens_limit', 'image_model_limit'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Canali di pubblicazione disponibili: chiave => metadati canonici.
     */
    public const CHANNELS = [
        'facebook'   => ['name' => 'Facebook',   'css_class' => 'fa-brands fa-facebook'],
        'instagram'  => ['name' => 'Instagram',  'css_class' => 'fa-brands fa-instagram'],
        'linkedin'   => ['name' => 'LinkedIn',   'css_class' => 'fa-brands fa-linkedin'],
        'wordpress'  => ['name' => 'WordPress',  'css_class' => 'fa-brands fa-wordpress-simple'],
        'newsletter' => ['name' => 'Newsletter', 'css_class' => 'fa-regular fa-envelope'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'channels' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(Settings::class);
    }

    public function tokensUsed(): HasMany
    {
        return $this->hasMany(TokenLog::class)
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }

    public function imagesUsed(): HasMany
    {
        return $this->hasMany(ImageJob::class)
            ->whereDay('image_jobs.created_at', now()->day);
    }

    public function postLogs(): HasMany
    {
        return $this->hasMany(TokenLog::class)
            ->where('type', 'post')
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }

    public function replyLogs(): HasMany
    {
        return $this->hasMany(TokenLog::class)
            ->where('type', 'reply')
            ->whereMonth('token_logs.created_at', now()->month)
            ->whereYear('token_logs.created_at', now()->year);
    }

    public function isAdmin(): bool
    {
        return $this->parent_id === null;
    }

    public function isManager(): bool
    {
        return $this->child_on == 1;
    }

    /**
     * Utenti su cui questo utente può "filtrare"/scopare la propria vista:
     * l'amministratore vede tutti gli altri utenti, il manager solo i propri
     * sotto-utenti, l'utente semplice nessuno.
     */
    public function filterableUsers(): Collection
    {
        if ($this->isAdmin()) {
            $query = static::where('id', '!=', $this->id);
        } elseif ($this->isManager()) {
            $query = static::where('parent_id', $this->id);
        } else {
            return collect();
        }

        return $query->orderBy('name')->get(['id', 'name', 'email', 'parent_id', 'child_on'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->isAdmin() ? 'amministratore' : ($u->isManager() ? 'manager' : 'utente'),
            ])
            ->values();
    }

    /**
     * Valida un id di scope richiesto (es. ?user=) contro filterableUsers():
     * ritorna la voce corrispondente, o null se non richiesto/non consentito.
     */
    public function resolveScopedUser(?int $requestedId): ?array
    {
        if ($requestedId === null) {
            return null;
        }

        return $this->filterableUsers()->firstWhere('id', $requestedId);
    }
}
