<?php

use App\Http\Controllers\Api\ContactRegistrationController;
use App\Http\Middleware\ApiKeyAuth;
use Illuminate\Support\Facades\Route;

Route::middleware([ApiKeyAuth::class, 'throttle:contacts-api'])->post('/contacts', [ContactRegistrationController::class, 'store']);
