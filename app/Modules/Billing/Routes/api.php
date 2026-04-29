<?php

use App\Modules\Billing\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/billing/plans', [BillingController::class, 'plans']);
    Route::get('/billing/overview', [BillingController::class, 'overview']);
    Route::get('/billing/history', [BillingController::class, 'history']);
    Route::post('/billing/subscription', [BillingController::class, 'updateSubscription']);
    Route::post('/billing/credits', [BillingController::class, 'purchaseCredits']);
});
