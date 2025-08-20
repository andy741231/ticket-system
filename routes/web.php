<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\Rbac\DashboardController as RbacDashboardController;
use App\Http\Controllers\Admin\Rbac\RolesController as RbacRolesController;
use App\Http\Controllers\Admin\Rbac\PermissionsController as RbacPermissionsController;
use App\Http\Controllers\Admin\Rbac\OverridesController as RbacOverridesController;
use Illuminate\Foundation\Application;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth routes
use App\Http\Controllers\ImageUploadController;

require __DIR__.'/auth.php';

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Image upload (requires auth)
    Route::post('/upload-image', [ImageUploadController::class, 'store'])->name('image.upload');
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Ticket routes
    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index')->middleware('perm:tickets.ticket.view|tickets.ticket.manage');
        Route::get('/create', [TicketController::class, 'create'])->name('create')->middleware('perm:tickets.ticket.create');
        Route::post('/', [TicketController::class, 'store'])->name('store')->middleware('perm:tickets.ticket.create');
        
        // Ticket-specific routes
        Route::prefix('{ticket}')->group(function () {
            Route::get('/', [TicketController::class, 'show'])->name('show');
            Route::get('/edit', [TicketController::class, 'edit'])->name('edit')->middleware('perm:tickets.ticket.update');
            Route::put('/', [TicketController::class, 'update'])->name('update')->middleware('perm:tickets.ticket.update');
            Route::delete('/', [TicketController::class, 'destroy'])->name('destroy')->middleware('perm:tickets.ticket.delete');
            Route::put('/status', [TicketController::class, 'updateStatus'])->name('status.update');

            // Ticket comments (nested)
            Route::prefix('comments')->name('comments.')->group(function () {
                // Any authenticated user who can view the ticket may post a comment; controller enforces view policy
                Route::post('/', [TicketCommentController::class, 'store'])->name('store');
                // Delete a specific comment
                Route::delete('{comment}', [TicketCommentController::class, 'destroy'])->name('destroy');
            });
        });
    });
    
    // Directory routes
    Route::prefix('directory')->name('directory.')->group(function () {
        Route::get('/', [DirectoryController::class, 'index'])->name('index')->middleware('perm:directory.profile.view|directory.profile.manage');
        Route::get('/{team}', [DirectoryController::class, 'show'])->name('show')->middleware('perm:directory.profile.view|directory.profile.manage');
        Route::get('/{team}/edit', [DirectoryController::class, 'edit'])->name('edit')->middleware('perm:directory.profile.manage');
        Route::put('/{team}', [DirectoryController::class, 'update'])->name('update')->middleware('perm:directory.profile.manage');
        Route::patch('/{team}/image', [DirectoryController::class, 'updateImage'])->name('updateImage')->middleware('perm:directory.profile.manage');
    });

    // Dashboard route - accessible to all authenticated users
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->middleware(['auth'])
        ->name('dashboard');
        
    // Test route to debug dashboard stats
    Route::get('/api/dashboard-stats', [\App\Http\Controllers\DashboardController::class, 'getStats'])
        ->middleware(['auth'])
        ->name('dashboard.stats');

    // Admin routes (Hub app context mapped from '/users')
    Route::middleware(['perm:hub.user.view|hub.user.manage'])->prefix('users/admin')->name('admin.')->group(function () {
        // Admin dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // User management
        Route::resource('users', UserController::class);
    });
    
    // Admin RBAC routes (granular per-area permissions)
    Route::prefix('admin/rbac')->name('admin.rbac.')->group(function () {
        // Dashboard visible if user can manage any RBAC area
        Route::get('/', [RbacDashboardController::class, 'index'])
            ->middleware('perm:admin.rbac.roles.manage|admin.rbac.permissions.manage|admin.rbac.overrides.manage')
            ->name('dashboard');

        // Roles management
        Route::middleware(['perm:admin.rbac.roles.manage'])->group(function () {
            Route::resource('roles', RbacRolesController::class)->except(['show']);

            // Role ↔ Permission assignment
            Route::post('roles/{role}/permissions/{permission}', [RbacRolesController::class, 'attachPermission'])->name('roles.permissions.attach');
            Route::delete('roles/{role}/permissions/{permission}', [RbacRolesController::class, 'detachPermission'])->name('roles.permissions.detach');
        });

        // Permissions management
        Route::middleware(['perm:admin.rbac.permissions.manage'])->group(function () {
            Route::resource('permissions', RbacPermissionsController::class)->except(['show']);
        });

        // Overrides management
        Route::middleware(['perm:admin.rbac.overrides.manage'])->group(function () {
            Route::resource('overrides', RbacOverridesController::class)->except(['show']);
        });
    });
    
    
});
