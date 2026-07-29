<?php

use App\Http\Controllers\Api\ContactRegistrationController;
use App\Http\Middleware\ApiKeyAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(ApiKeyAuth::class)->post('/contacts', [ContactRegistrationController::class, 'store']);
