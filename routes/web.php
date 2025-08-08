<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

// Auth routes
require __DIR__.'/auth.php';

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Ticket routes
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/create', [TicketController::class, 'create'])->name('create');
        Route::post('/', [TicketController::class, 'store'])->name('store');
        
        // Ticket-specific routes
        Route::prefix('{ticket}')->group(function () {
            Route::get('/', [TicketController::class, 'show'])->name('show');
            Route::get('/edit', [TicketController::class, 'edit'])->name('edit');
            Route::put('/', [TicketController::class, 'update'])->name('update');
            Route::delete('/', [TicketController::class, 'destroy'])->name('destroy');
            Route::put('/status', [TicketController::class, 'updateStatus'])->name('status.update');
        });
    });
    
    // Dashboard route - accessible to all authenticated users
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->middleware(['auth'])
        ->name('dashboard');
        
    // Test route to debug dashboard stats
    Route::get('/api/dashboard-stats', [\App\Http\Controllers\DashboardController::class, 'getStats'])
        ->middleware(['auth'])
        ->name('dashboard.stats');

    // Admin routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        // Admin dashboard
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard');
        })->name('dashboard');
        
        // User management
        Route::resource('users', UserController::class);
    });
    
    // User dashboard route (for non-admin users)
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});
