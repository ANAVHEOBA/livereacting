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

    // Project Studio
    Route::get('/projects/{id}/scenes', [\App\Modules\Projects\Controllers\SceneController::class, 'index']);
    Route::post('/projects/{id}/scenes', [\App\Modules\Projects\Controllers\SceneController::class, 'store']);
    Route::post('/projects/{id}/scenes/reorder', [\App\Modules\Projects\Controllers\SceneController::class, 'reorder']);
    Route::get('/projects/{id}/scenes/{sceneId}', [\App\Modules\Projects\Controllers\SceneController::class, 'show']);
    Route::patch('/projects/{id}/scenes/{sceneId}', [\App\Modules\Projects\Controllers\SceneController::class, 'update']);
    Route::delete('/projects/{id}/scenes/{sceneId}', [\App\Modules\Projects\Controllers\SceneController::class, 'destroy']);
    Route::post('/projects/{id}/scenes/{sceneId}/activate', [\App\Modules\Projects\Controllers\SceneController::class, 'activate']);

    Route::get('/projects/{id}/scenes/{sceneId}/layers', [\App\Modules\Projects\Controllers\SceneLayerController::class, 'index']);
    Route::post('/projects/{id}/scenes/{sceneId}/layers', [\App\Modules\Projects\Controllers\SceneLayerController::class, 'store']);
    Route::post('/projects/{id}/scenes/{sceneId}/layers/reorder', [\App\Modules\Projects\Controllers\SceneLayerController::class, 'reorder']);
    Route::patch('/projects/{id}/scenes/{sceneId}/layers/{layerId}', [\App\Modules\Projects\Controllers\SceneLayerController::class, 'update']);
    Route::delete('/projects/{id}/scenes/{sceneId}/layers/{layerId}', [\App\Modules\Projects\Controllers\SceneLayerController::class, 'destroy']);

    // Project Destinations
    Route::get('/projects/{id}/destinations', [\App\Modules\Projects\Controllers\ProjectDestinationController::class, 'index']);
    Route::post('/projects/{id}/destinations', [\App\Modules\Projects\Controllers\ProjectDestinationController::class, 'store']);
    Route::delete('/projects/{id}/destinations/{destinationId}', [\App\Modules\Projects\Controllers\ProjectDestinationController::class, 'destroy']);

    // Live Stream Control
    Route::post('/projects/{id}/validate', [\App\Modules\Projects\Controllers\LiveStreamController::class, 'validate']);
    Route::post('/projects/{id}/live', [\App\Modules\Projects\Controllers\LiveStreamController::class, 'start']);
    Route::delete('/projects/{id}/live', [\App\Modules\Projects\Controllers\LiveStreamController::class, 'stop']);

    // Scheduling
    Route::get('/projects/{id}/schedules', [\App\Modules\Projects\Controllers\ScheduleController::class, 'index']);
    Route::post('/projects/{id}/schedule', [\App\Modules\Projects\Controllers\ScheduleController::class, 'store']);
    Route::delete('/projects/{id}/schedules', [\App\Modules\Projects\Controllers\ScheduleController::class, 'destroy']);

    // Advanced Features
    Route::post('/projects/{id}/sync', [\App\Modules\Projects\Controllers\AdvancedController::class, 'sync']);
    Route::get('/projects/{id}/history', [\App\Modules\Projects\Controllers\AdvancedController::class, 'history']);
    Route::get('/projects/{id}/analytics', [\App\Modules\Projects\Controllers\AdvancedController::class, 'analytics']);
});
