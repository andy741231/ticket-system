<?php

use App\Http\Controllers\Api\TicketFileController;
use App\Http\Controllers\Api\TempFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['web', 'auth'])->group(function () {
    // Temporary file upload endpoints (before ticket exists)
    Route::prefix('temp-files')->group(function () {
        Route::post('/', [TempFileController::class, 'store']);
        Route::delete('/{file}', [TempFileController::class, 'destroy']);
    });

    // File upload endpoints
    Route::prefix('tickets/{ticket}')->group(function () {
        // Upload files to a ticket
        Route::post('/files', [TicketFileController::class, 'store']);
        
        // Delete a file from a ticket
        Route::delete('/files/{file}', [TicketFileController::class, 'destroy']);
    });
});
