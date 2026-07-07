<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Mesi in italiano: stessa convenzione di DashboardController (APP_LOCALE
     * resta 'en', mai tradotto per l'IT — non ci si affida a Carbon::locale()).
     */
    private const MONTHS_IT = [1 => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];

    /**
     * Calendario mensile dei post programmati (published=0, data futura),
     * stesso scope della lista Post (Post::visibleTo).
     */
    public function index(Request $request): Response
    {
        $me = $request->user();
        $activeUserId = $me->resolveScopedUser($request->session()->get('scoped_user_id'))['id'] ?? null;

        $requested = $request->string('month')->toString();
        $month = $requested && preg_match('/^\d{4}-\d{2}$/', $requested)
            ? Carbon::createFromFormat('Y-m-d', "{$requested}-01")->startOfMonth()
            : now()->startOfMonth();

        $posts = Post::query()
            ->visibleTo($me, $activeUserId)
            ->where('published', '0')
            ->where('published_at', '>', now())
            ->whereBetween('published_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->with('user:id,name')
            ->orderBy('published_at')
            ->get()
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title ?: '(senza titolo)',
                'day' => $p->published_at->format('Y-m-d'),
                'time' => $p->published_at->format('H:i'),
                'author' => $p->user->name,
                'channels' => collect($p->channels ?? [])
                    ->filter(fn ($c) => !empty($c['on']))
                    ->keys()
                    ->values()
                    ->all(),
                'url' => route('posts.show', $p),
            ])
            ->values();

        return Inertia::render('Schedule', [
            'month' => $month->format('Y-m'),
            'monthLabel' => self::MONTHS_IT[(int) $month->format('n')] . ' ' . $month->format('Y'),
            'prevMonth' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'showAuthor' => $me->isAdmin() || $me->isManager(),
            'posts' => $posts,
        ]);
    }
}
