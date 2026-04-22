<?php

use App\Modules\Destinations\Controllers\DestinationController;
use Illuminate\Support\Facades\Route;

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/destinations', [DestinationController::class, 'index']);
});
