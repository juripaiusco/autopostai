<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\ImageController;
use \App\Http\Controllers\WordPressController;
use \App\Http\Controllers\NewsletterController;
use \App\Http\Controllers\PushSubscriptionController;

Route::middleware('auth:sanctum')->group(function () {

    // PUSH NOTIFICATIONS - START -----------------------------------------------

    // Salvataggio della subscription per le notifiche push
    Route::post('/push-subscribe', [PushSubscriptionController::class, 'store']);

    // Eliminazione della subscription per le notifiche push
    Route::post('/push-destroy', [PushSubscriptionController::class, 'destroy']);

    // PUSH NOTIFICATIONS - END -------------------------------------------------


    // WEB NOTIFICATIONS - START -----------------------------------------------

    // Verifica se l'utente ha notifiche web da leggere
    Route::get('/notify-web-check', [PushSubscriptionController::class, 'notify_web_check']);

    // Imposta come letta la notifica web e recupera le notifiche
    Route::get('/notify-read-web', [PushSubscriptionController::class, 'read_set']);

    // WEB NOTIFICATIONS - END -------------------------------------------------


    // Aggiornamento dei posts in background
    Route::get('/posts', function () {
        $posts = new \App\Http\Controllers\Posts();
        $data = $posts->getData();
        $data = $data->take(env('VIEWS_PAGINATE'));
        $data = $data->latest();

        return response()->json(['posts' => $data->get()]);
    });

    // Esecuzione di un job in background per la generazione di immagini
    Route::post('/start-job', [ImageController::class, 'startJob']);
    Route::get('/check-job-status/{jobId}', [ImageController::class, 'checkJobStatus']);

    // WordPressController recupera le categorie
    Route::get('/wordpress-categories/{userId}', [WordPressController::class, 'categories_get']);

    // NewsletterController recupera le liste di newsletter
    Route::get('/newsletter-lists/{userId}', [NewsletterController::class, 'lists_get']);

});
