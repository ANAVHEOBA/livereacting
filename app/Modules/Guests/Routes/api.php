<?php

use App\Modules\Guests\Controllers\GuestController;
use Illuminate\Support\Facades\Route;

Route::get('/guest-invites/{token}', [GuestController::class, 'previewInvite']);
Route::post('/guest-invites/{token}/accept', [GuestController::class, 'acceptInvite']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects/{id}/guests', [GuestController::class, 'showRoom']);
    Route::post('/projects/{id}/guests/room', [GuestController::class, 'upsertRoom']);
    Route::post('/projects/{id}/guests/invites', [GuestController::class, 'createInvite']);
    Route::patch('/projects/{id}/guests/invites/{inviteId}', [GuestController::class, 'updateInvite']);
    Route::delete('/projects/{id}/guests/invites/{inviteId}', [GuestController::class, 'destroyInvite']);
    Route::patch('/projects/{id}/guests/sessions/{sessionId}', [GuestController::class, 'updateSession']);
});
