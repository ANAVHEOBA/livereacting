<?php

use App\Modules\Interactive\Controllers\InteractiveController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects/{id}/interactive', [InteractiveController::class, 'index']);
    Route::post('/projects/{id}/interactive', [InteractiveController::class, 'store']);
    Route::get('/projects/{id}/interactive/{elementId}', [InteractiveController::class, 'show']);
    Route::patch('/projects/{id}/interactive/{elementId}', [InteractiveController::class, 'update']);
    Route::delete('/projects/{id}/interactive/{elementId}', [InteractiveController::class, 'destroy']);
    Route::post('/projects/{id}/interactive/{elementId}/activate', [InteractiveController::class, 'activate']);
    Route::post('/projects/{id}/interactive/{elementId}/responses', [InteractiveController::class, 'respond']);
    Route::post('/projects/{id}/interactive/{elementId}/feature', [InteractiveController::class, 'featureResponse']);
});
