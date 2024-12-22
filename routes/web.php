<?php

use App\Http\Controllers\Posts;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/posts', [Posts::class, 'index'])->name('post.index');
    Route::get('/posts/create', [Posts::class, 'create'])->name('post.create');
    Route::post('/posts/store', [Posts::class, 'store'])->name('post.store');
    Route::get('/posts/edit/{id}', [Posts::class, 'edit'])->name('post.edit');
    Route::post('/posts/update/{id}', [Posts::class, 'update'])->name('post.update');
    Route::get('/posts/destroy/{id}', [Posts::class, 'destroy'])->name('post.destroy');

    Route::get('/settings', [Settings::class, 'index'])->name('settings.index');
    Route::post('/settings/update/{id}', [Settings::class, 'update'])->name('settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

});

require __DIR__.'/auth.php';
