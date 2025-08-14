<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\ImageController;
use \App\Http\Controllers\WordPressController;
use \App\Http\Controllers\NewsletterController;
use \App\Http\Controllers\PushSubscriptionController;

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

// PUSH API - Salvataggio della subscription per le notifiche push
Route::post('/push-subscribe', [PushSubscriptionController::class, 'store'])
    ->middleware('auth:sanctum');

// PUSH API - Eliminazione della subscription per le notifiche push
Route::post('/push-destroy', [PushSubscriptionController::class, 'destroy'])
    ->middleware('auth:sanctum');

Route::get('/notify-web-check', [PushSubscriptionController::class, 'notify_web_check'])
    ->middleware('auth:sanctum');

// PUSH API Web - Imposta come letta la notifica push per il web e recupera le notifiche
Route::get('/notify-read-web', [PushSubscriptionController::class, 'read_set'])
    ->middleware('auth:sanctum');

// Aggiornamento dei posts in background
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
Route::get('/newsletter-lists/{userId}', [NewsletterController::class, 'lists_get'])
    ->middleware('auth:sanctum');
