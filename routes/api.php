<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\ImageController;
use \App\Http\Controllers\WordPressController;
use \App\Http\Controllers\NewsletterController;

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::get('/posts', function () {

    $posts = new \App\Http\Controllers\Posts();
    $data = $posts->getData();
    $data = $data->take(env('VIEWS_PAGINATE'));
    $data = $data->latest();

    return $data->get();
})->middleware('auth:sanctum');

// Esecuzione di un job in background
Route::post('/start-job', [ImageController::class, 'startJob'])
    ->middleware('auth:sanctum');
Route::get('/check-job-status/{jobId}', [ImageController::class, 'checkJobStatus'])
    ->middleware('auth:sanctum');

// Esecuzione WordPressController
Route::get('/wordpress-categories/{userId}', [WordPressController::class, 'categories_get'])
    ->middleware('auth:sanctum');

// Esecuzione NewsletterController
/*Route::get('/newsletter-lists/{userId}', [NewsletterController::class, 'lists_get'])
    ->middleware('auth:sanctum');*/
