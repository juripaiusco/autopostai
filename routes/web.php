<?php

use App\Http\Controllers\Dashboard;
use App\Http\Controllers\Posts;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\Users;
use Illuminate\Support\Facades\Route;

use App\Models\PushSubscription;
use App\Notifications\TestPushNotification;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use Illuminate\Support\Facades\Notification;

Route::get('/', function () {
    return redirect('/posts?orderby=published_at&ordertype=desc&s=');
});

Route::get('/test', function () {
    $users = \App\Models\User::with('pushSubscriptions')->get();

    foreach ($users as $user) {
        $user->notify(new TestPushNotification());
    }

    return 'Notifica inviata!';
});

//Route::get('/dashboard', [Dashboard::class])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

//    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('/dashboard', function () {
        return redirect('/posts?orderby=published_at&ordertype=desc&s=');
    })->name('dashboard');

    Route::get('/users', [Users::class, 'index'])
        ->name('user.index');
    Route::get('/users/show/{id}', [Users::class, 'show'])
        ->name('user.show');
    Route::get('/users/create', [Users::class, 'create'])
        ->name('user.create');
    Route::post('/users/store', [Users::class, 'store'])
        ->name('user.store');
    Route::get('/users/edit/{id}', [Users::class, 'edit'])
        ->name('user.edit');
    Route::post('/users/update/{id}', [Users::class, 'update'])
        ->name('user.update');
    Route::get('/users/destroy/{id}', [Users::class, 'destroy'])
        ->name('user.destroy');

    Route::get('/posts', [Posts::class, 'index'])
        ->name('post.index');
    Route::get('/posts/show/{id}', [Posts::class, 'show'])
        ->name('post.show');
    Route::get('/posts/create', [Posts::class, 'create'])
        ->name('post.create');
    Route::post('/posts/store', [Posts::class, 'store'])
        ->name('post.store');
    Route::get('/posts/schedule', [Posts::class, 'schedule'])
        ->name('post.schedule');
    Route::post('/posts/schedule_store', [Posts::class, 'schedule_store'])
        ->name('post.schedule_store');
    Route::get('/posts/edit/{id}', [Posts::class, 'edit'])
        ->name('post.edit');
    Route::post('/posts/update/{id}', [Posts::class, 'update'])
        ->name('post.update');
    Route::post('/posts/update_no_redirect/{id}', [Posts::class, 'update_no_redirect'])
        ->name('post.update_no_redirect');
    Route::get('/posts/delete/{id}', [Posts::class, 'delete'])
        ->name('post.delete');
    Route::get('/posts/destroy/{id}', [Posts::class, 'destroy'])
        ->name('post.destroy');
    Route::get('/posts/destroy_image/{img}', [Posts::class, 'destroy_image'])
        ->name('post.destroy_image');
    Route::post('/posts/preview', [Posts::class, 'preview'])
        ->name('post.preview');
    Route::get('/posts/duplicate/{id}', [Posts::class, 'duplicate'])
        ->name('post.duplicate');

    Route::get('/settings', [Settings::class, 'index'])
        ->name('settings.index');
    Route::post('/settings/update_by_user/{id}', [Settings::class, 'update_by_user'])
        ->name('settings.update');

    Route::get('/linkedin/redirect/{linkedin_client_id}', [\App\Http\Controllers\LinkedInController::class, 'redirect'])
        ->name('linkedin.redirect');
    Route::get('/linkedin/callback', [\App\Http\Controllers\LinkedInController::class, 'callback'])
        ->name('linkedin.callback');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

});

require __DIR__.'/auth.php';
