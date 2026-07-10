<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageArchiveController;
use App\Http\Controllers\LinkedInController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\PushSubscriptionController;
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

    Route::post('/account/{user}/newsletter/liste', [NewsletterController::class, 'fetchLists'])
        ->name('newsletter.lists');

    Route::get('/posts/canali/{user}/wordpress-categorie', [WordPressController::class, 'categoriesForPost'])
        ->name('posts.wordpress-categories');

    Route::get('/posts/canali/{user}/newsletter-liste', [NewsletterController::class, 'listsForPost'])
        ->name('posts.newsletter-lists');

    Route::get('/posts/canali/{user}/archivio-immagini', [ImageArchiveController::class, 'index'])
        ->name('posts.image-archive');

    Route::post('/posts/canali/{user}/genera-immagine', [ImageArchiveController::class, 'store'])
        ->name('posts.image-generate');

    Route::delete('/posts/canali/{user}/archivio-immagini/{filename}', [ImageArchiveController::class, 'destroy'])
        ->name('posts.image-archive.destroy');

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

    Route::get('/notifiche', [PushNotificationController::class, 'index'])
        ->name('notifications');

    Route::get('/notifiche/crea', [PushNotificationController::class, 'create'])
        ->name('notifications.create');

    Route::post('/notifiche', [PushNotificationController::class, 'store'])
        ->name('notifications.store');

    Route::get('/notifiche/{notification}/modifica', [PushNotificationController::class, 'edit'])
        ->name('notifications.edit');

    Route::put('/notifiche/{notification}', [PushNotificationController::class, 'update'])
        ->name('notifications.update');

    Route::delete('/notifiche/{notification}', [PushNotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    Route::post('/push/iscrivi', [PushSubscriptionController::class, 'store'])
        ->name('push.subscribe');

    Route::post('/push/disiscrivi', [PushSubscriptionController::class, 'destroy'])
        ->name('push.unsubscribe');

    Route::get('/push/non-lette', [PushSubscriptionController::class, 'checkUnread'])
        ->name('push.unread');

    Route::post('/push/segna-lette', [PushSubscriptionController::class, 'markRead'])
        ->name('push.mark-read');
});
