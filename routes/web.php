<?php

use App\Http\Controllers\Dashboard;
use App\Http\Controllers\Posts;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\Users;
use App\Http\Controllers\PushNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/site.webmanifest-' . str_replace('.', '-', env('APP_VERSION')), function () {
    return response()->json([
        'name' => env('APP_NAME'),
        'short_name' => env('APP_NAME'),
        'theme_color' => env('PWA_THEME_COLOR', '#ffffff'),
        'background_color' => env('PWA_BG_COLOR', '#ffffff'),
        'display' => 'standalone',
        'icons' => [
            [
                "src" => asset('/android-chrome-192x192.png'),
                "sizes" => "192x192",
                "type" => "image/png"
            ],
            [
                "src" => asset('/android-chrome-512x512.png'),
                "sizes" => "512x512",
                "type" => "image/png"
            ]
        ],
        'start_url' => env('PWA_START_URL', '/'),
        'version' => env('APP_VERSION', 'dev'),
    ])->header('Content-Type', 'application/manifest+json');
});

// Redirect to posts with default sorting
Route::get('/', function () {
    return redirect('/posts?orderby=published_at&ordertype=desc&s=');
});

// Invio delle notifiche push a tutti gli utenti iscritti
Route::get('/notifications/send-to-specific-users', [PushNotifications::class, 'send_to_specific_users'])
    ->name('notification.send_to_specific_users');

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

    Route::get('/notifications', [PushNotifications::class, 'index'])
        ->name('notification.index');
    Route::get('/notifications/show/{id}', [PushNotifications::class, 'show'])
        ->name('notification.show');
    Route::get('/notifications/create', [PushNotifications::class, 'create'])
        ->name('notification.create');
    Route::post('/notifications/store', [PushNotifications::class, 'store'])
        ->name('notification.store');
    Route::get('/notifications/edit/{id}', [PushNotifications::class, 'edit'])
        ->name('notification.edit');
    Route::post('/notifications/update/{id}', [PushNotifications::class, 'update'])
        ->name('notification.update');
    Route::get('/notifications/destroy/{id}', [PushNotifications::class, 'destroy'])
        ->name('notification.destroy');
    Route::get('/notifications/send/{id}', [PushNotifications::class, 'send'])
        ->name('notification.send');

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

    /*Route::get('/sse/notification', function () {
        return response()->stream(function () {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $user = Auth::user();
            $lastValue = null;

            $start = time();
            while (time() - $start < 15) {
                $value = $user->fresh()->notify_read_web == 1 ? 1 : 0;

                if ($value !== $lastValue) {
                    echo "data: " . json_encode(['active' => $value]) . "\n\n";
                    ob_flush();
                    flush();
                    $lastValue = $value;
                } else {
                    // Keep alive ogni 20 secondi
                    echo ": keep-alive\n\n";
                    ob_flush();
                    flush();
                }

                sleep(3);
            }
        });
    })->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);*/

});

require __DIR__.'/auth.php';
