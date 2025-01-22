<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\ImageController;

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::get('/posts', function () {
    return App\Models\Post::with(['user', 'comments.token', 'token'])
        ->take(env('VIEWS_PAGINATE'))
        ->latest()
        ->get();
})->middleware('auth:sanctum');

// Esecuzione di un job in background
Route::post('/start-job', [ImageController::class, 'startJob'])
    ->middleware('auth:sanctum');
Route::get('/check-job-status/{jobId}', [ImageController::class, 'checkJobStatus'])
    ->middleware('auth:sanctum');
