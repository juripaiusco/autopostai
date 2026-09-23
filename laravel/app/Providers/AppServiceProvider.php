<?php

namespace App\Providers;

use Dotenv\Dotenv;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // API registrazione contatti: un contatore per API key (= per account),
        // non per IP — più clienti possono chiamare dallo stesso server. Hash
        // dell'header perché il throttle gira prima di ApiKeyAuth (priorità
        // middleware). Ogni richiesta fa anche un lookup DNS MX: senza tetto
        // un form compromesso o un loop lato cliente riempirebbe la lista.
        RateLimiter::for('contacts-api', fn (Request $request) => Limit::perMinute(60)
            ->by('contacts-api:'.hash('sha256', (string) $request->header('X-Api-Key'))));
    }
}
