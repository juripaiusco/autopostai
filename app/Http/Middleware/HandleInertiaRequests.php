<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'tokens_used' => $request->user() ? $this->getUserTokens($request->user()) : 0,
                'images_used' => $request->user() ? $this->getImagesUsed($request->user()) : 0
            ],
        ];
    }

    public function getUserTokens($user)
    {
        return Cache::remember(
            "user_{$user->id}_tokens_used",
            now()->addMinutes(5),
            function () use ($user) {
                return $user->tokens_used()->sum('tokens_used');
            }
        );
    }

    public function getImagesUsed($user)
    {
        return $user->images_used()->count();
    }
}
