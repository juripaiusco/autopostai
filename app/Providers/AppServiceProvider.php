<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Models\PushNotification;
use App\Policies\PostPolicy;
use App\Policies\UserPolicy;
use App\Policies\NotificationPolicy;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Post::class => PostPolicy::class,
        PushNotification::class => NotificationPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Percorso al file della versione
        $versionFileName = '.env.app-version';
        $versionFile = base_path($versionFileName);

        if (file_exists($versionFile)) {
            $dotenv = Dotenv::createMutable(base_path(), $versionFileName);
            $dotenv->load();
        }

        Inertia::share('app.name', env('APP_NAME', 'name'));
        Inertia::share('app.version', env('APP_VERSION', '0.0.0'));
        Inertia::share('app.changelog_url', env('APP_CHANGELOG_URL', '0.0.0'));
    }
}
