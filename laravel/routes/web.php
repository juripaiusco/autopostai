<?php

use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/* Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name'),
])); */

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => Inertia::render('Dashboard'))
        ->name('home');

    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))
        ->name('dashboard');

    Route::get('/account', [AccountController::class, 'index'])
        ->name('account');

    Route::delete('/account/{user}', [AccountController::class, 'destroy'])
        ->name('account.destroy');

    Route::get('/posts', fn () => Inertia::render('Posts'))
        ->name('posts');

    Route::get('/calendarizza', fn () => Inertia::render('Schedule'))
        ->name('schedule');

    Route::get('/impostazioni', fn () => Inertia::render('Settings'))
        ->name('settings');
});
