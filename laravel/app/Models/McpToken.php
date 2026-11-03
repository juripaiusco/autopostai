<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'name', 'token_hash', 'last_four', 'abilities', 'expires_at', 'last_used_at', 'last_used_ip', 'revoked_at'])]
#[Hidden(['token_hash'])]
class McpToken extends Model
{
    /**
     * Permessi di un token MCP, dal più debole al più forte. Ogni tool MCP ne
     * richiede uno; sopra restano comunque le Policy dell'utente: un token
     * non può mai fare più di quanto l'utente faccia dall'interfaccia.
     *  - read:    consultare (KPI, post, calendario, contatti)
     *  - write:   creare/modificare bozze e post programmati, generare con l'AI
     *  - publish: azioni che escono verso l'esterno o non si annullano
     *             (pubblica subito, elimina, modifica post già usciti)
     */
    public const ABILITIES = [
        'read' => 'Lettura',
        'write' => 'Scrittura (bozze e programmati)',
        'publish' => 'Pubblicazione ed eliminazione',
    ];

    /** Durate selezionabili alla creazione (giorni): niente token eterni. */
    public const EXPIRY_DAYS = [30, 90, 365];

    /** Token attivi per utente oltre i quali bisogna revocarne uno. */
    public const MAX_ACTIVE_PER_USER = 10;

    /** Prefisso riconoscibile: aiuta i secret scanner (GitHub ecc.) a trovarli. */
    public const PREFIX = 'fp3_';

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crea un token per $user e ritorna [model, plaintext]: il plaintext
     * esiste solo qui, in DB va l'hash SHA-256 (token casuale lungo, non
     * serve un hash lento come per le password).
     *
     * @param  array<int, string>  $abilities
     * @return array{0: self, 1: string}
     */
    public static function issue(User $user, string $name, array $abilities, int $expiryDays): array
    {
        $plaintext = self::PREFIX.Str::random(48);

        $token = self::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plaintext),
            'last_four' => substr($plaintext, -4),
            'abilities' => self::normalizeAbilities($abilities),
            'expires_at' => now()->addDays($expiryDays),
        ]);

        return [$token, $plaintext];
    }

    /**
     * I permessi sono cumulativi: chi può pubblicare può anche scrivere, chi
     * scrive può leggere. Salvati già espansi, così il controllo è un
     * semplice in_array.
     *
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    public static function normalizeAbilities(array $abilities): array
    {
        $levels = array_keys(self::ABILITIES);
        $max = collect($abilities)
            ->map(fn ($a) => array_search($a, $levels, true))
            ->filter(fn ($i) => $i !== false)
            ->max() ?? 0;

        return array_slice($levels, 0, $max + 1);
    }

    public static function findActiveByPlaintext(string $plaintext): ?self
    {
        return self::query()
            ->active()
            ->where('token_hash', hash('sha256', $plaintext))
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }
}
