<?php

use App\Modules\Integrations\Controllers\IntegrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::get('/integrations/{provider}/authorize', [IntegrationController::class, 'authorize']);
    Route::get('/integrations/{provider}/validate', [IntegrationController::class, 'validate']);
    Route::get('/integrations/{provider}/assets', [IntegrationController::class, 'assets']);
    Route::get('/integrations/{provider}/destinations', [IntegrationController::class, 'destinations']);
    Route::post('/integrations/{provider}/destinations', [IntegrationController::class, 'storeDestination']);
    Route::post('/integrations/{provider}/imports', [IntegrationController::class, 'importAsset']);
    Route::post('/integrations/slack/notify-test', [IntegrationController::class, 'notifySlack']);
    Route::delete('/integrations/{provider}/connections/{id}', [IntegrationController::class, 'destroy']);
});

Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback']);
