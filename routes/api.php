<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::get('/posts', function () {
    return App\Models\Post::with(['user', 'comments.token', 'token'])
        ->take(env('VIEWS_PAGINATE'))
        ->latest()
        ->get();
})->middleware('auth:sanctum');
