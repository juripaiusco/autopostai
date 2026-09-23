<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'created_by_user_id',
    'title',
    'ai_prompt_post',
    'ai_content',
    'ai_prompt_comment',
    'img',
    'img_ai_check_on',
    'comments_enabled',
    'auto_reply_enabled',
    'channels',
    'preview',
    'published_at',
    'published',
    'task_complete',
    'check_attempts',
    'on_hold_until',
    'updated',
    'deleted',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Chiavi del JSON channels scritte dal worker Python dopo le azioni
     * remote (id/url del post pubblicato, rimozione, update, stats newsletter).
     * Il form non le conosce: vanno preservate quando Laravel riscrive channels.
     */
    public const WORKER_CHANNEL_KEYS = ['id', 'url', 'gallery_html', 'id_del', 'id_update', 'stats', 'completed_at'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'img' => 'array',
            'published_at' => 'datetime',
            'on_hold_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)
            ->orderBy('message_created_time', 'desc');
    }

    public function token(): HasOne
    {
        return $this->hasOne(TokenLog::class, 'reference_id')
            ->where('type', 'post');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(TokenLog::class, 'reference_id')
            ->where('type', 'post');
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(EmailSend::class);
    }

    /**
     * Ricalcola channels.newsletter.stats dai conteggi per stato in
     * email_sends (Step 8) — stessa struttura scritta anche da
     * publisher/tasks/newsletter_send.py (Python), che possiede il resto del
     * canale (id/provider/queued_at): qui si tocca solo la sotto-chiave
     * 'stats', gli altri campi restano quelli che Python ha già scritto.
     */
    public function refreshNewsletterStats(): void
    {
        $channels = $this->channels ?? [];
        if (!isset($channels['newsletter'])) {
            return;
        }

        $counts = $this->emailSends()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $channels['newsletter']['stats'] = collect(['queued', 'sent', 'delivered', 'opened', 'clicked', 'bounced'])
            ->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();

        $this->update(['channels' => $channels]);
    }

    /**
     * Almeno un canale acceso ha già un id remoto: il worker lo ha pubblicato.
     * Con published=0 significa che un altro canale è fallito (il worker
     * marca published=1 solo quando tutti i canali accesi hanno un id).
     */
    public function hasPublishedChannels(): bool
    {
        return collect($this->channels ?? [])
            ->contains(fn ($c) => is_array($c) && !empty($c['on']) && !empty($c['id']));
    }

    /** Modificabile solo finché nessun canale è stato pubblicato. */
    public function isEditable(): bool
    {
        return !in_array($this->status(), ['published', 'partial'], true);
    }

    public function status(): string
    {
        if ($this->published == '1') {
            return 'published';
        }

        // Prima risultava "draft" (modificabile): salvare il form riscriveva
        // channels perdendo gli id remoti e il worker ripubblicava sui canali
        // già usciti.
        if ($this->hasPublishedChannels()) {
            return 'partial';
        }

        if ($this->published_at !== null && $this->published_at->isFuture()) {
            return 'scheduled';
        }

        return 'draft';
    }

    /**
     * Post visibili a $me: se $scopedUserId è valorizzato (scope globale
     * attivo su un altro utente) mostra solo i suoi post, altrimenti
     * amministratore -> tutti, manager -> propri sotto-utenti, utente -> i
     * propri.
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
