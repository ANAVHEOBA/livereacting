<?php

use App\Modules\Destinations\Controllers\DestinationController;
use Illuminate\Support\Facades\Route;

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/destinations', [DestinationController::class, 'index']);
    Route::post('/destinations', [DestinationController::class, 'store']);
    Route::get('/destinations/{id}', [DestinationController::class, 'show']);
    Route::patch('/destinations/{id}', [DestinationController::class, 'update']);
    Route::delete('/destinations/{id}', [DestinationController::class, 'destroy']);
    Route::post('/destinations/{id}/validate', [DestinationController::class, 'validate']);
    Route::post('/destinations/{id}/probe', [DestinationController::class, 'probe']);
});
