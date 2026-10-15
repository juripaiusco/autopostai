<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Mesi abbreviati in italiano: l'interfaccia è italiana indipendentemente
     * da APP_LOCALE (qui 'en' — mai configurato per l'italiano), quindi non
     * ci si può affidare a Carbon::translatedFormat(). Stessa convenzione già
     * usata nei dati mock del frontend.
     */
    private const MONTHS_IT = [1 => 'gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

    /**
     * Dashboard: isAdmin/isManager/filterableUsers/activeUser sono condivisi
     * globalmente da HandleInertiaRequests (la sidebar li usa su ogni
     * pagina). Qui serve solo risolvere lo scope: metriche (DashboardMetrics,
     * solo dati interni) e "Ultimi post" rispettano lo scope scelto.
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $activeUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        $postScope = fn () => Post::query()->visibleTo($me, $activeUserId);

        $recentPosts = $postScope()
            ->with('user:id,name')
            ->withCount('comments')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'channels' => collect($p->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'status' => $p->status(),
                'date' => $p->published_at ? $this->formatDateIt($p->published_at) : '—',
                'comments' => $p->comments_count,
                'cover' => $p->coverUrl(),
                'thumb' => $p->thumbGradient(),
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'metrics' => (new DashboardMetrics($me, $activeUserId))->toArray(),
            'periodDays' => DashboardMetrics::PERIOD_DAYS,
            'recentPosts' => $recentPosts,
        ]);
    }

    private function formatDateIt(\Illuminate\Support\Carbon $date): string
    {
        return $date->format('d') . ' ' . self::MONTHS_IT[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
