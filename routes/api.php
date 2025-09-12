<?php

use App\Http\Controllers\Api\TicketFileController;
use App\Http\Controllers\Api\TempFileController;
use App\Http\Controllers\Newsletter\PublicController as NewsletterPublicController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\Newsletter\LogoController;
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
        Route::post('/', [TempFileController::class, 'store'])->middleware('perm:tickets.file.upload');
        Route::delete('/{file}', [TempFileController::class, 'destroy'])->middleware('perm:tickets.file.upload');
    });

    // File upload endpoints
    Route::prefix('tickets/{ticket}')->group(function () {
        // Upload files to a ticket
        Route::post('/files', [TicketFileController::class, 'store'])->middleware('perm:tickets.file.upload');
        
        // Delete a file from a ticket
        Route::delete('/files/{file}', [TicketFileController::class, 'destroy'])->middleware('perm:tickets.file.upload');
    });

    // Dashboard stats
    Route::get('/dashboard-stats', [DashboardController::class, 'stats']);

    // Newsletter logos management
    Route::get('/newsletter/logos', [LogoController::class, 'index']);
    Route::delete('/newsletter/logos/{filename}', [LogoController::class, 'destroy']);
    Route::put('/newsletter/logos/{filename}/rename', [LogoController::class, 'rename']);
});

// Public Newsletter Subscription API (no auth)
Route::post('/subscribe', [NewsletterPublicController::class, 'subscribe']);
Route::get('/unsubscribe/{token}', [NewsletterPublicController::class, 'unsubscribeApi']);
Route::put('/subscriber/{token}', [NewsletterPublicController::class, 'updatePreferences']);

// Image upload endpoint (used by newsletter builder)
Route::post('/image-upload', [ImageUploadController::class, 'store'])->name('image.upload');
