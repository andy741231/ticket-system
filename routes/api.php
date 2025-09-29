<?php

use App\Http\Controllers\Api\TicketFileController;
use App\Http\Controllers\Api\TempFileController;
use App\Http\Controllers\Api\TicketImageController;
use App\Http\Controllers\Api\AnnotationController;
use App\Http\Controllers\Api\NewsletterCampaignController;
use App\Http\Controllers\Newsletter\PublicController as NewsletterPublicController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\Newsletter\LogoController;
use App\Http\Controllers\Api\DirectoryPublicController;
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

    // Newsletter campaign utilities for authenticated users
    Route::get('/newsletter/campaigns/drafts', [NewsletterCampaignController::class, 'drafts']);

    // File upload endpoints
    Route::prefix('tickets/{ticket}')->group(function () {
        // Upload files to a ticket
        Route::post('/files', [TicketFileController::class, 'store'])->middleware('perm:tickets.file.upload');
        
        // Delete a file from a ticket
        Route::delete('/files/{file}', [TicketFileController::class, 'destroy'])->middleware('perm:tickets.file.upload');
        
        // Get mentionable users for a ticket
        Route::get('/mentionable-users', [\App\Http\Controllers\TicketCommentController::class, 'mentionableUsers']);

        // Annotation system routes
        Route::prefix('images')->group(function () {
            // Image management
            Route::get('/', [TicketImageController::class, 'index']);
            Route::post('/from-url', [TicketImageController::class, 'storeFromUrl']);
            Route::post('/from-file', [TicketImageController::class, 'storeFromFile']);
            Route::post('/from-newsletter', [TicketImageController::class, 'storeFromNewsletter']);
            Route::get('/{ticketImage}', [TicketImageController::class, 'show']);
            Route::delete('/{ticketImage}', [TicketImageController::class, 'destroy']);
            Route::get('/{ticketImage}/status', [TicketImageController::class, 'status']);

            // Annotation management for specific images
            Route::prefix('{ticketImage}/annotations')->group(function () {
                Route::get('/', [AnnotationController::class, 'index']);
                Route::post('/', [AnnotationController::class, 'store']);
                Route::get('/{annotation}', [AnnotationController::class, 'show']);
                Route::put('/{annotation}', [AnnotationController::class, 'update']);
                Route::delete('/{annotation}', [AnnotationController::class, 'destroy']);
                Route::put('/{annotation}/status', [AnnotationController::class, 'updateStatus']);

                // Annotation comments
                Route::get('/{annotation}/comments', [AnnotationController::class, 'getComments']);
                Route::post('/{annotation}/comments', [AnnotationController::class, 'storeComment']);
                Route::put('/{annotation}/comments/{comment}', [AnnotationController::class, 'updateComment']);
                Route::delete('/{annotation}/comments/{comment}', [AnnotationController::class, 'destroyComment']);

                // Image-level comments (no specific annotation)
                Route::get('/image-comments', [AnnotationController::class, 'listImageComments']);
                Route::post('/image-comments', [AnnotationController::class, 'storeImageComment']);
                Route::put('/image-comments/{comment}', [AnnotationController::class, 'updateImageComment']);
                Route::delete('/image-comments/{comment}', [AnnotationController::class, 'destroyImageComment']);
            });
        });
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

// Public Directory API (no auth) with CORS support
Route::options('/directory/team', [DirectoryPublicController::class, 'options']);
Route::get('/directory/team', [DirectoryPublicController::class, 'index']);

// Public annotation routes
Route::prefix('public/annotations/{image}')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'store']);
    Route::delete('/{annotation}', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'destroy']);
    
    // Public image-level comments
    Route::post('/image-comments', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'storeImageComment']);
    
    Route::prefix('annotations/{annotation}')->group(function () {
        Route::get('/comments', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'getComments']);
        Route::post('/comments', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'storeComment']);
        Route::put('/comments/{comment}', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'updateComment']);
        Route::delete('/comments/{comment}', [\App\Http\Controllers\Api\PublicAnnotationController::class, 'destroyComment']);
    });
});
