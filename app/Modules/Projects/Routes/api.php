<?php

use App\Modules\Projects\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

    // Project Destinations
    Route::get('/projects/{id}/destinations', [\App\Modules\Projects\Controllers\ProjectDestinationController::class, 'index']);
    Route::post('/projects/{id}/destinations', [\App\Modules\Projects\Controllers\ProjectDestinationController::class, 'store']);
    Route::delete('/projects/{id}/destinations/{destinationId}', [\App\Modules\Projects\Controllers\ProjectDestinationController::class, 'destroy']);

    // Live Stream Control
    Route::post('/projects/{id}/validate', [\App\Modules\Projects\Controllers\LiveStreamController::class, 'validate']);
    Route::post('/projects/{id}/live', [\App\Modules\Projects\Controllers\LiveStreamController::class, 'start']);
    Route::delete('/projects/{id}/live', [\App\Modules\Projects\Controllers\LiveStreamController::class, 'stop']);

    // Scheduling
    Route::post('/projects/{id}/schedule', [\App\Modules\Projects\Controllers\ScheduleController::class, 'store']);
    Route::delete('/projects/{id}/schedules', [\App\Modules\Projects\Controllers\ScheduleController::class, 'destroy']);

    // Advanced Features
    Route::post('/projects/{id}/sync', [\App\Modules\Projects\Controllers\AdvancedController::class, 'sync']);
    Route::get('/projects/{id}/history', [\App\Modules\Projects\Controllers\AdvancedController::class, 'history']);
    Route::get('/projects/{id}/analytics', [\App\Modules\Projects\Controllers\AdvancedController::class, 'analytics']);
});
