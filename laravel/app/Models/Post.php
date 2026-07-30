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

    public function status(): string
    {
        if ($this->published == '1') {
            return 'published';
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
