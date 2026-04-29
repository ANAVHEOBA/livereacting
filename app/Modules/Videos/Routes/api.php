<?php

use App\Modules\Videos\Controllers\FileController;
use App\Modules\Videos\Controllers\FileImportController;
use App\Modules\Videos\Controllers\FolderController;
use App\Modules\Videos\Controllers\PlaylistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // Files
    Route::get('/files', [FileController::class, 'index']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
    Route::patch('/files/{id}/rename', [FileController::class, 'rename']);

    // Folders
    Route::get('/folders', [FolderController::class, 'index']);
    Route::post('/folders', [FolderController::class, 'store']);
    Route::delete('/folders/{id}', [FolderController::class, 'destroy']);

    // File Imports
    Route::post('/files/import', [FileImportController::class, 'import']);
    Route::get('/files/import/{id}', [FileImportController::class, 'status']);
    Route::post('/files/import/{id}/cancel', [FileImportController::class, 'cancel']);

    // Playlists
    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::get('/playlists/{id}', [PlaylistController::class, 'show']);
    Route::patch('/playlists/{id}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{id}', [PlaylistController::class, 'destroy']);
    Route::post('/playlists/{id}/items', [PlaylistController::class, 'addItem']);
    Route::delete('/playlists/{id}/items/{itemId}', [PlaylistController::class, 'removeItem']);
});
