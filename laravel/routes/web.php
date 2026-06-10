<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\PostController;
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

    Route::get('/account/create', [AccountController::class, 'create'])
        ->name('account.create');

    Route::post('/account', [AccountController::class, 'store'])
        ->name('account.store');

    Route::get('/account/{user}/edit', [AccountController::class, 'edit'])
        ->name('account.edit');

    Route::put('/account/{user}', [AccountController::class, 'update'])
        ->name('account.update');

    Route::delete('/account/{user}', [AccountController::class, 'destroy'])
        ->name('account.destroy');

    Route::get('/posts', [PostController::class, 'index'])
        ->name('posts');

    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->name('posts.destroy');

    Route::get('/calendarizza', fn () => Inertia::render('Schedule'))
        ->name('schedule');

    Route::get('/impostazioni', fn () => Inertia::render('Settings'))
        ->name('settings');
});
