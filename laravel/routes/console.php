<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Richiede un cron di sistema che lanci `php artisan schedule:run` ogni minuto
// (o `php artisan schedule:work` per lo sviluppo) — nessuno dei due è ancora
// configurato in questo ambiente Docker. withoutOverlapping: se un invio dura
// più di un minuto il run successivo salta invece di partire in parallelo.
Schedule::command('notifications:send-pending')->everyMinute()->withoutOverlapping();
