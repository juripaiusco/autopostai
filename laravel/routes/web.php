<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/* Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name'),
])); */

Route::get('/', fn () => Inertia::render('Dashboard'))
    ->middleware('auth')
    ->name('home');

Route::get('/dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware('auth')
    ->name('dashboard');

Route::get('/account', fn () => Inertia::render('Account'))
    ->middleware('auth')
    ->name('account');

Route::get('/posts', fn () => Inertia::render('Posts'))
    ->middleware('auth')
    ->name('posts');

Route::get('/calendarizza', fn () => Inertia::render('Schedule'))
    ->middleware('auth')
    ->name('schedule');

Route::get('/impostazioni', fn () => Inertia::render('Settings'))
    ->middleware('auth')
    ->name('settings');
