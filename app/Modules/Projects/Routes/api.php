<?php

use App\Modules\Projects\Controllers\AdvancedController;
use App\Modules\Projects\Controllers\LiveStreamController;
use App\Modules\Projects\Controllers\ProjectController;
use App\Modules\Projects\Controllers\ProjectDestinationController;
use App\Modules\Projects\Controllers\SceneController;
use App\Modules\Projects\Controllers\SceneLayerController;
use App\Modules\Projects\Controllers\SceneTemplateController;
use App\Modules\Projects\Controllers\ScheduleController;
use App\Modules\Projects\Controllers\StudioConfigController;
use Illuminate\Support\Facades\Route;

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/studio/config', [StudioConfigController::class, 'show']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

    // Project Studio
    Route::get('/projects/{id}/scenes', [SceneController::class, 'index']);
    Route::post('/projects/{id}/scenes', [SceneController::class, 'store']);
    Route::post('/projects/{id}/scenes/reorder', [SceneController::class, 'reorder']);
    Route::get('/projects/{id}/scenes/{sceneId}', [SceneController::class, 'show']);
    Route::patch('/projects/{id}/scenes/{sceneId}', [SceneController::class, 'update']);
    Route::delete('/projects/{id}/scenes/{sceneId}', [SceneController::class, 'destroy']);
    Route::post('/projects/{id}/scenes/{sceneId}/activate', [SceneController::class, 'activate']);

    Route::get('/scene-templates', [SceneTemplateController::class, 'index']);
    Route::post('/projects/{id}/scene-templates', [SceneTemplateController::class, 'store']);
    Route::post('/projects/{id}/scene-templates/apply', [SceneTemplateController::class, 'apply']);

    Route::get('/projects/{id}/scenes/{sceneId}/layers', [SceneLayerController::class, 'index']);
    Route::post('/projects/{id}/scenes/{sceneId}/layers', [SceneLayerController::class, 'store']);
    Route::post('/projects/{id}/scenes/{sceneId}/layers/reorder', [SceneLayerController::class, 'reorder']);
    Route::patch('/projects/{id}/scenes/{sceneId}/layers/{layerId}', [SceneLayerController::class, 'update']);
    Route::delete('/projects/{id}/scenes/{sceneId}/layers/{layerId}', [SceneLayerController::class, 'destroy']);

    // Project Destinations
    Route::get('/projects/{id}/destinations', [ProjectDestinationController::class, 'index']);
    Route::post('/projects/{id}/destinations', [ProjectDestinationController::class, 'store']);
    Route::delete('/projects/{id}/destinations/{destinationId}', [ProjectDestinationController::class, 'destroy']);

    // Live Stream Control
    Route::post('/projects/{id}/validate', [LiveStreamController::class, 'validate']);
    Route::post('/projects/{id}/live', [LiveStreamController::class, 'start']);
    Route::delete('/projects/{id}/live', [LiveStreamController::class, 'stop']);

    // Scheduling
    Route::get('/projects/{id}/schedules', [ScheduleController::class, 'index']);
    Route::post('/projects/{id}/schedule', [ScheduleController::class, 'store']);
    Route::delete('/projects/{id}/schedules', [ScheduleController::class, 'destroy']);

    // Advanced Features
    Route::post('/projects/{id}/sync', [AdvancedController::class, 'sync']);
    Route::get('/projects/{id}/history', [AdvancedController::class, 'history']);
    Route::get('/projects/{id}/analytics', [AdvancedController::class, 'analytics']);
});
