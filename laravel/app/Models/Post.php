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
use Illuminate\Support\Facades\Storage;

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

    /**
     * Tentativi di pubblicazione per canale dopo i quali il worker si ferma
     * (config.PUBLISH_MAX_ATTEMPTS in Python). Il worker li conta in
     * channels.<canale>.attempts, con l'ultimo messaggio in .error: le due
     * chiavi NON sono in WORKER_CHANNEL_KEYS, quindi risalvare il post dal
     * form le azzera e il canale viene ritentato.
     */
    public const PUBLISH_MAX_ATTEMPTS = 3;

    /**
     * Giorni dalla pubblicazione oltre i quali il worker smette comunque di
     * seguire i commenti (config.TASK_COMPLETE_MAX_WAIT_DAYS in Python). La
     * chiusura vera la decide il worker con task_complete=1, anche prima.
     */
    public const COMMENT_MONITORING_DAYS = 14;

    /**
     * Gradient di sfondo per la miniatura dei post senza immagine (lista post,
     * post recenti in dashboard): scelto in modo deterministico dall'id.
     */
    private const THUMBS = [
        'linear-gradient(135deg,#5b7290,#3f5470)',
        'linear-gradient(135deg,#6b96b3,#48708c)',
        'linear-gradient(135deg,#5f8782,#456661)',
        'linear-gradient(135deg,#6e7494,#4d5270)',
        'linear-gradient(135deg,#9b7d86,#6f5860)',
        'linear-gradient(135deg,#7c8a99,#566270)',
        'linear-gradient(135deg,#5f8595,#41626f)',
        'linear-gradient(135deg,#9a8b7d,#6f6055)',
        'linear-gradient(135deg,#566b86,#3a4c63)',
        'linear-gradient(135deg,#6b7686,#4a5563)',
    ];

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

    /**
     * Canali accesi su cui la pubblicazione è fallita:
     * [canale => ['message', 'attempts', 'gaveUp']]. gaveUp = tentativi
     * esauriti, il worker non riprova da solo.
     */
    public function channelErrors(): array
    {
        return collect($this->channels ?? [])
            ->filter(fn ($c) => is_array($c) && !empty($c['on']) && empty($c['id']) && !empty($c['error']))
            ->map(fn ($c) => [
                'message' => (string) $c['error'],
                'attempts' => (int) ($c['attempts'] ?? 0),
                'gaveUp' => (int) ($c['attempts'] ?? 0) >= self::PUBLISH_MAX_ATTEMPTS,
            ])
            ->all();
    }

    /** Modificabile solo finché nessun canale è stato pubblicato. */
    /**
     * URL pubblico della prima immagine del post (cartella posts/{id}/),
     * null se il post non ha immagini.
     */
    public function coverUrl(): ?string
    {
        $first = collect($this->img ?? [])->first();

        return $first ? Storage::disk('public')->url("posts/{$this->id}/{$first}") : null;
    }

    public function thumbGradient(): string
    {
        return self::THUMBS[$this->id % count(self::THUMBS)];
    }

    public function isEditable(): bool
    {
        return !in_array($this->status(), ['published', 'done', 'partial'], true);
    }

    /**
     * Fine stimata del monitoraggio commenti (ISO 8601), null se il worker
     * non sta seguendo il post. Il worker può chiudere prima (cap raggiunto).
     */
    public function monitoringUntil(): ?string
    {
        if ($this->status() !== 'published' || $this->published_at === null) {
            return null;
        }

        return $this->published_at->copy()->addDays(self::COMMENT_MONITORING_DAYS)->toIso8601String();
    }

    public function status(): string
    {
        // Pubblicato: finché task_complete=0 il worker segue ancora i
        // commenti, poi il post è "completato".
        if ($this->published == '1') {
            return $this->task_complete == '1' ? 'done' : 'published';
        }

        // Prima risultava "draft" (modificabile): salvare il form riscriveva
        // channels perdendo gli id remoti e il worker ripubblicava sui canali
        // già usciti.
        if ($this->hasPublishedChannels()) {
            return 'partial';
        }

        // Nessun canale uscito e almeno uno fallito: prima risultava "draft",
        // l'utente non aveva modo di accorgersi dell'errore.
        if ($this->channelErrors() !== []) {
            return 'error';
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
