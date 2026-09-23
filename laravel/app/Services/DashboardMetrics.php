<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\EmailSend;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Metriche della dashboard calcolate solo da dati interni (fase 1): post
 * pubblicati, uscite sui canali, commenti ricevuti, risposte AI, aperture
 * newsletter SMTP. Visualizzazioni e copertura arriveranno dagli insights
 * delle piattaforme (fase 2, sotto-piano F).
 *
 * Le aggregazioni per periodo/mese/settimana si fanno in PHP e non in SQL:
 * niente funzioni data specifiche di MariaDB (i test girano su SQLite) e i
 * volumi (12 mesi di un account o di un manager) restano piccoli.
 */
class DashboardMetrics
{
    public const PERIOD_DAYS = 90;
    private const SPARK_WEEKS = 7;
    private const MONTHS = 12;
    private const MONTHS_IT = [1 => 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];

    private Carbon $now;

    public function __construct(private User $me, private ?int $scopedUserId = null)
    {
        $this->now = now();
    }

    public function toArray(): array
    {
        $from = $this->now->copy()->subMonths(self::MONTHS)->startOfMonth();

        // Una riga per ogni uscita post×canale (il canale ha un id remoto).
        $outputs = $this->posts()
            ->where('published_at', '>=', $from)
            ->get(['id', 'published_at', 'channels'])
            ->flatMap(fn (Post $p) => collect($p->channels ?? [])
                ->filter(fn ($c) => is_array($c) && !empty($c['on']) && !empty($c['id']))
                ->keys()
                ->map(fn ($channel) => ['post_id' => $p->id, 'channel' => $channel, 'at' => $p->published_at]));

        $comments = Comment::query()
            ->whereIn('post_id', $this->posts()->select('id'))
            ->where(fn ($q) => $q->where('message_created_time', '>=', $from)->orWhere('reply_created_time', '>=', $from))
            ->get(['channel', 'message_created_time', 'reply_id', 'reply_created_time']);

        $received = $comments
            ->filter(fn ($c) => $c->message_created_time)
            ->map(fn ($c) => ['channel' => $c->channel, 'at' => $c->message_created_time]);
        $replies = $comments
            ->filter(fn ($c) => $c->reply_id && $c->reply_created_time)
            ->map(fn ($c) => ['channel' => $c->channel, 'at' => $c->reply_created_time]);
        $postsOut = $outputs->unique('post_id');

        $start = $this->periodStart();

        return [
            'stats' => [
                'posts' => $this->stat($postsOut),
                'outputs' => $this->stat($outputs),
                'comments' => $this->stat($received),
                'replies' => $this->stat($replies),
            ],
            'monthly' => $this->monthly($postsOut, $received),
            'channels' => collect(array_keys(User::CHANNELS))->map(fn ($id) => [
                'id' => $id,
                'posts' => $this->between($outputs->where('channel', $id), $start)->count(),
                'comments' => $this->between($received->where('channel', $id), $start)->count(),
                'replies' => $this->between($replies->where('channel', $id), $start)->count(),
                'opens' => $id === 'newsletter' ? $this->newsletterOpens($start) : null,
            ])->all(),
        ];
    }

    private function posts(): Builder
    {
        return Post::query()->visibleTo($this->me, $this->scopedUserId);
    }

    private function periodStart(): Carbon
    {
        return $this->now->copy()->subDays(self::PERIOD_DAYS);
    }

    private function between(Collection $events, Carbon $from, ?Carbon $to = null): Collection
    {
        $to ??= $this->now;

        return $events->filter(fn ($e) => $e['at'] >= $from && $e['at'] < $to);
    }

    /**
     * Valore sugli ultimi 90 giorni, variazione % sui 90 precedenti (null se
     * il periodo precedente è vuoto: una percentuale su zero non dice nulla)
     * e andamento delle ultime 7 settimane per il mini-grafico.
     */
    private function stat(Collection $events): array
    {
        $start = $this->periodStart();
        $current = $this->between($events, $start)->count();
        $previous = $this->between($events, $start->copy()->subDays(self::PERIOD_DAYS), $start)->count();

        $spark = [];
        for ($w = self::SPARK_WEEKS; $w >= 1; $w--) {
            $from = $this->now->copy()->subWeeks($w);
            $spark[] = $this->between($events, $from, $from->copy()->addWeek())->count();
        }

        return [
            'value' => $current,
            'trend' => $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null,
            'spark' => $spark,
        ];
    }

    private function monthly(Collection $posts, Collection $comments): array
    {
        $months = [];
        for ($m = self::MONTHS - 1; $m >= 0; $m--) {
            $from = $this->now->copy()->startOfMonth()->subMonths($m);
            $to = $from->copy()->addMonth();
            $months[] = [
                'month' => self::MONTHS_IT[$from->month],
                'posts' => $this->between($posts, $from, $to)->count(),
                'comments' => $this->between($comments, $from, $to)->count(),
            ];
        }

        return $months;
    }

    /** Aperture tracciate dal pixel: solo newsletter SMTP custom. */
    private function newsletterOpens(Carbon $from): int
    {
        return EmailSend::query()
            ->whereIn('post_id', $this->posts()->select('id'))
            ->where('opened_at', '>=', $from)
            ->count();
    }
}
