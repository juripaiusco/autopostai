<?php

namespace App\Providers;

use Dotenv\Dotenv;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        $versionFileName = '.env.app-version';
        $versionFile = base_path($versionFileName);

        if (file_exists($versionFile)) {
            $dotenv = Dotenv::createMutable(base_path(), $versionFileName);
            $dotenv->load();
        }
    }
}
