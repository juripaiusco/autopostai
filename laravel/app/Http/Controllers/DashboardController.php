<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Gradient di sfondo per la thumb dei post reali (nessuna cover disponibile):
     * stessa palette usata nei dati mock, scelta in modo deterministico dall'id.
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
     * pagina). Qui serve solo risolvere lo scope per la propria query sui
     * post. Solo il conteggio post e la tabella "Ultimi post" riflettono lo
     * scope scelto con dati reali; il resto della dashboard resta sul mock
     * statico finché non esiste una pipeline di analytics (v. CLAUDE.md).
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $activeUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        $postScope = fn () => Post::query()->visibleTo($me, $activeUserId);

        $postsCount = $postScope()->count();

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
                'views' => 0,
                'comments' => $p->comments_count,
                'thumb' => self::THUMBS[$p->id % count(self::THUMBS)],
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'postsCount' => $postsCount,
            'recentPosts' => $recentPosts,
        ]);
    }

    private function formatDateIt(\Illuminate\Support\Carbon $date): string
    {
        return $date->format('d') . ' ' . self::MONTHS_IT[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
