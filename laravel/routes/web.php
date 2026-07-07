<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LinkedInController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScopeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WordPressController;
use Illuminate\Support\Facades\Route;

/* Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name'),
])); */

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->name('home');

    Route::get('/dashboard', [DashboardController::class, 'index'])
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

    Route::get('/account/{user}/linkedin/connetti', [LinkedInController::class, 'redirect'])
        ->name('linkedin.redirect');

    Route::get('/linkedin/callback', [LinkedInController::class, 'callback'])
        ->name('linkedin.callback');

    Route::put('/account/{user}/linkedin/pagina', [LinkedInController::class, 'updatePage'])
        ->name('linkedin.page.update');

    Route::post('/account/{user}/wordpress/categorie', [WordPressController::class, 'fetchCategories'])
        ->name('wordpress.categories');

    Route::get('/posts', [PostController::class, 'index'])
        ->name('posts');

    Route::get('/posts/create', [PostController::class, 'create'])
        ->name('posts.create');

    Route::post('/posts', [PostController::class, 'store'])
        ->name('posts.store');

    Route::get('/posts/{post}', [PostController::class, 'show'])
        ->name('posts.show');

    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])
        ->name('posts.edit');

    Route::put('/posts/{post}', [PostController::class, 'update'])
        ->name('posts.update');

    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->name('posts.destroy');

    Route::post('/scope', [ScopeController::class, 'update'])
        ->name('scope.update');

    Route::get('/search', [SearchController::class, 'index'])
        ->name('search');

    Route::get('/calendarizza', [ScheduleController::class, 'index'])
        ->name('schedule');

    Route::get('/impostazioni', [SettingsController::class, 'edit'])
        ->name('settings');

    Route::put('/impostazioni', [SettingsController::class, 'update'])
        ->name('settings.update');
});
