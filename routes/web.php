<?php

use App\Http\Controllers\Newsletter\SubscriberController;
use App\Http\Controllers\Newsletter\GroupController;
use App\Http\Controllers\Newsletter\CampaignController;
use App\Http\Controllers\Newsletter\DashboardController;
use App\Http\Controllers\Newsletter\TemplateController;
use App\Http\Controllers\Newsletter\PublicController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\TmpUploadController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Load authentication routes (login, password reset, etc.)
require __DIR__.'/auth.php';

// Root route: redirect to main dashboard
Route::get('/', function () {
    return auth()->check() 
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Main Dashboard (renders app Dashboard.vue)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Web-authenticated API endpoints (session-based)
Route::middleware(['auth', 'web'])->prefix('api')->group(function () {
    Route::get('/dashboard-stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);
    // Temporary file upload endpoint (used by avatar uploader)
    Route::post('/tmp_upload', [TmpUploadController::class, 'store']);
    Route::delete('/tmp_delete', [TmpUploadController::class, 'destroy']);
});

// Newsletter Public Routes (no auth required)
$publicRoutes = function () {
    Route::get('archive', [PublicController::class, 'archive'])->name('archive');
    Route::get('campaign/{campaign}', [PublicController::class, 'viewCampaign'])->name('campaign.view');
    Route::get('unsubscribe/{token}', [\App\Http\Controllers\Newsletter\PublicController::class, 'unsubscribe'])->name('unsubscribe');
    Route::get('preferences/{token}', [\App\Http\Controllers\Newsletter\PublicController::class, 'preferences'])->name('preferences');
    Route::post('preferences/{token}', [\App\Http\Controllers\Newsletter\PublicController::class, 'updatePreferences'])->name('preferences.update');
    Route::get('track-open/{campaign}/{subscriber}/{token}', [\App\Http\Controllers\Newsletter\PublicController::class, 'trackOpen'])->name('track-open');
    Route::get('track-click/{campaign}/{subscriber}/{url}/{token}', [\App\Http\Controllers\Newsletter\PublicController::class, 'trackClick'])
        ->where('url', '.*')
        ->name('track-click');
};

// Register routes under both /newsletter/ and /newsletter/public/ for backward compatibility
Route::prefix('newsletter')->name('newsletter.public.')->group($publicRoutes);
Route::prefix('newsletter/public')->name('newsletter.public.')->group($publicRoutes);

// Newsletter Admin Routes (auth required)
Route::middleware(['auth', 'verified'])->prefix('newsletter')->name('newsletter.')->group(function () {
    // Groups
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::post('/{group}/remove-subscribers', [GroupController::class, 'removeSubscribers'])->name('remove-subscribers');
    });
    
    // Newsletter Archive landing
    Route::get('/', function () {
        $campaigns = \App\Models\Newsletter\Campaign::query()
            ->where('time_capsule', false) // Exclude time capsule campaigns
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate(10, ['id', 'name', 'subject', 'sent_at', 'created_at']);

        return Inertia::render('Newsletter/Archive', [
            'campaigns' => $campaigns,
        ]);
    })->name('index');
    
    // Admin dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Analytics
    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');
    
    // Subscribers
    Route::prefix('subscribers')->name('subscribers.')->group(function () {
        Route::get('/', [SubscriberController::class, 'index'])->name('index');
        Route::post('/', [SubscriberController::class, 'store'])->name('store');

        // Literal routes must come before wildcard routes to avoid capture by '/{subscriber}'
        Route::post('/bulk-import', [SubscriberController::class, 'bulkImport'])->name('bulk-import');
        Route::get('/bulk-export', [SubscriberController::class, 'bulkExport'])->name('bulk-export');
        Route::delete('/bulk-delete', [SubscriberController::class, 'bulkDelete'])->name('bulk-delete');
        Route::put('/bulk-update-status', [SubscriberController::class, 'bulkUpdateStatus'])->name('bulk-update-status');
        Route::get('/count', [SubscriberController::class, 'getActiveCount'])->name('count');

        // Wildcard routes
        Route::get('/{subscriber}', [SubscriberController::class, 'show'])->name('show');
        Route::put('/{subscriber}', [SubscriberController::class, 'update'])->name('update');
        Route::delete('/{subscriber}', [SubscriberController::class, 'destroy'])->name('destroy');
    });
    
    // Groups
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->name('index');
        Route::post('/', [GroupController::class, 'store'])->name('store');
        Route::get('/{group}', [GroupController::class, 'show'])->name('show');
        Route::put('/{group}', [GroupController::class, 'update'])->name('update');
        Route::delete('/{group}', [GroupController::class, 'destroy'])->name('destroy');
        Route::post('/{group}/subscribers', [GroupController::class, 'addSubscribers'])->name('add-subscribers');
        
        // Get unique subscribers count across multiple groups
        Route::post('/unique-subscribers', [GroupController::class, 'getUniqueSubscribersCount'])
            ->name('unique-subscribers');
    });
    
    // Campaigns
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        // Literal routes must come before wildcard '{campaign}' routes
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::get('/create', [CampaignController::class, 'create'])->name('create');
        Route::post('/', [CampaignController::class, 'store'])->name('store');
        // Time Capsule routes (literal)
        Route::get('/timecapsule', [CampaignController::class, 'timeCapsule'])->name('timecapsule');
        Route::post('/timecapsule', [CampaignController::class, 'storeTimeCapsule'])->name('timecapsule.store');
        Route::post('/timecapsule/{campaign}/restore', [CampaignController::class, 'restoreFromTimeCapsule'])->name('timecapsule.restore');
        // Wildcard routes for specific campaigns
        Route::get('/{campaign}', [CampaignController::class, 'show'])->name('show');
        Route::get('/{campaign}/edit', [CampaignController::class, 'edit'])->name('edit');
        Route::put('/{campaign}', [CampaignController::class, 'update'])->name('update');
        Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy');
        Route::post('/{campaign}/send', [CampaignController::class, 'send'])->name('send');
        Route::post('/{campaign}/test', [CampaignController::class, 'sendTest'])->name('test');
        Route::get('/{campaign}/preview', [CampaignController::class, 'preview'])->name('preview');
        Route::post('/{campaign}/pause', [CampaignController::class, 'pause'])->name('pause');
        Route::post('/{campaign}/resume', [CampaignController::class, 'resume'])->name('resume');
        Route::post('/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('cancel');
        Route::post('/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('duplicate');
        // Delivery management endpoints
        Route::get('/{campaign}/scheduled-sends', [CampaignController::class, 'scheduledSends'])->name('scheduled-sends');
        Route::post('/{campaign}/retry-scheduled-sends', [CampaignController::class, 'retryScheduledSends'])->name('retry-scheduled-sends');
        Route::post('/{campaign}/process-pending', [CampaignController::class, 'processPending'])->name('process-pending');
    });
    
    // Templates
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::get('/create', [TemplateController::class, 'create'])->name('create');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::get('/{template}', [TemplateController::class, 'show'])->name('show');
        Route::get('/{template}/edit', [TemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [TemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [TemplateController::class, 'destroy'])->name('destroy');
    });
});

// Tickets Routes (now using TicketController)
Route::middleware(['auth', 'verified'])->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', [TicketController::class, 'index'])->name('index');
    Route::get('/create', [TicketController::class, 'create'])->name('create');
    Route::post('/', [TicketController::class, 'store'])->name('store');
    Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
    Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->name('edit');
    Route::put('/{ticket}', [TicketController::class, 'update'])->name('update');
    Route::delete('/{ticket}', [TicketController::class, 'destroy'])->name('destroy');
    Route::put('/{ticket}/status', [TicketController::class, 'updateStatus'])->name('status.update');
    
    // Ticket Comments
    Route::prefix('{ticket}/comments')->name('comments.')->group(function () {
        Route::post('/', [\App\Http\Controllers\TicketCommentController::class, 'store'])->name('store');
        Route::put('/{comment}', [\App\Http\Controllers\TicketCommentController::class, 'update'])->name('update');
        Route::delete('/{comment}', [\App\Http\Controllers\TicketCommentController::class, 'destroy'])->name('destroy');
        Route::post('/{comment}/pin', [\App\Http\Controllers\TicketCommentController::class, 'pin'])->name('pin');
        Route::post('/{comment}/reactions', [\App\Http\Controllers\TicketCommentController::class, 'reactions'])->name('reactions');
    });
});

// Directory Routes (now using DirectoryController)
Route::middleware(['auth', 'verified'])->prefix('directory')->name('directory.')->group(function () {
    Route::get('/', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\DirectoryController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\DirectoryController::class, 'store'])->name('store');
    Route::get('/{team}', [\App\Http\Controllers\DirectoryController::class, 'show'])->name('show');
    Route::get('/{team}/edit', [\App\Http\Controllers\DirectoryController::class, 'edit'])->name('edit');
    Route::put('/{team}', [\App\Http\Controllers\DirectoryController::class, 'update'])->name('update');
    Route::delete('/{team}', [\App\Http\Controllers\DirectoryController::class, 'destroy'])->name('destroy');
});

// Admin Routes
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Users Management (controller-based to support team-scoped RBAC)
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
    
    // RBAC Management
    Route::prefix('rbac')->name('rbac.')->group(function () {
        Route::get('/', function () {
            $apps = \Illuminate\Support\Facades\Schema::hasTable('apps')
                ? \App\Models\App::query()->count()
                : 0;
            $roles = \Spatie\Permission\Models\Role::query()->count();
            $permissions = \Spatie\Permission\Models\Permission::query()->count();
            $overrides = \App\Models\UserPermissionOverride::query()->count();

            return Inertia::render('Admin/Rbac/Dashboard', [
                'stats' => [
                    'apps' => $apps,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'overrides' => $overrides,
                ]
            ]);
        })->name('dashboard');
        Route::get('/roles', function () {
            $roles = \Spatie\Permission\Models\Role::query()
                ->orderBy('name')
                ->paginate(10, ['id', 'name', 'guard_name', 'created_at']);
            return Inertia::render('Admin/Rbac/Roles/Index', [
                'roles' => $roles,
            ]);
        })->name('roles.index');
        Route::get('/permissions', function () {
            $permissions = \Spatie\Permission\Models\Permission::query()
                ->orderBy('name')
                ->paginate(10, ['id', 'name', 'guard_name', 'created_at']);
            return Inertia::render('Admin/Rbac/Permissions/Index', [
                'permissions' => $permissions,
            ]);
        })->name('permissions.index');
        Route::get('/permissions/create', function () {
            return Inertia::render('Admin/Rbac/Permissions/Create');
        })->name('permissions.create');
        Route::get('/permissions/{permission}/edit', function (\Spatie\Permission\Models\Permission $permission) {
            return Inertia::render('Admin/Rbac/Permissions/Edit', [
                'permission' => $permission,
            ]);
        })->name('permissions.edit');
        Route::put('/permissions/{permission}', function (\Spatie\Permission\Models\Permission $permission) {
            $data = request()->validate([
                'name' => ['required', 'string', 'max:255'],
                'key' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'is_mutable' => ['boolean'],
            ]);
            // Guard against updating immutable permissions if such a column exists
            if (isset($permission->is_mutable) && !$permission->is_mutable) {
                return redirect()->back()->with('error', 'This permission is immutable.');
            }
            $permission->fill($data);
            $permission->save();
            return redirect()->route('admin.rbac.permissions.index')->with('success', 'Permission updated.');
        })->name('permissions.update');
        Route::get('/apps', function () {
            if (\Illuminate\Support\Facades\Schema::hasTable('apps')) {
                $apps = \App\Models\App::query()->orderBy('name')->paginate(10, ['id', 'name', 'slug', 'created_at']);
            } else {
                $apps = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]);
            }
            return Inertia::render('Admin/Rbac/Apps/Index', [
                'apps' => $apps,
            ]);
        })->name('apps.index');
        
        Route::post('/apps', function () {
            if (!\Illuminate\Support\Facades\Schema::hasTable('apps')) {
                return redirect()->back()->with('error', 'Apps table does not exist.');
            }
            
            $data = request()->validate([
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'unique:apps,slug'],
                'description' => ['nullable', 'string'],
            ]);
            
            $app = new \App\Models\App($data);
            $app->save();
            
            return redirect()->route('admin.rbac.apps.index')
                ->with('success', 'App created successfully.');
        })->name('apps.store');
        Route::get('/overrides', function () {
            $overrides = \App\Models\UserPermissionOverride::with(['user', 'grantedBy'])
                ->orderByDesc('created_at')
                ->paginate(10, ['id', 'user_id', 'permission', 'granted_by_user_id', 'team_id', 'created_at']);
            return Inertia::render('Admin/Rbac/Overrides/Index', [
                'overrides' => $overrides,
            ]);
        })->name('overrides.index');
        Route::get('/overrides/create', function () {
            $users = \App\Models\User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
                
            $permissions = \Spatie\Permission\Models\Permission::query()
                ->orderBy('key')
                ->get(['id', 'key', 'name']);
                
            $apps = \Illuminate\Support\Facades\Schema::hasTable('apps')
                ? \App\Models\App::query()->orderBy('name')->get(['id', 'name', 'slug'])
                : collect();
                
            return Inertia::render('Admin/Rbac/Overrides/Create', [
                'users' => $users,
                'permissions' => $permissions,
                'apps' => $apps,
            ]);
        })->name('overrides.create');
        
        Route::get('/roles/create', function () {
            return Inertia::render('Admin/Rbac/Roles/Create');
        })->name('roles.create');
        Route::get('/roles/{role}/edit', function (\Spatie\Permission\Models\Role $role) {
            // Apps list (optional)
            $apps = \Illuminate\Support\Facades\Schema::hasTable('apps')
                ? \App\Models\App::query()->orderBy('name')->get(['id', 'slug', 'name'])
                : collect();

            // Filters
            $q = request('q');

            // Available permissions with optional search
            $permissionsQuery = \Spatie\Permission\Models\Permission::query()->orderBy('name');
            if ($q) {
                $permissionsQuery->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%");
                    if (\Illuminate\Support\Facades\Schema::hasColumn('permissions', 'key')) {
                        $query->orWhere('key', 'like', "%{$q}%");
                    }
                });
            }
            $available = $permissionsQuery->paginate(10);

            // Assigned permissions
            $assigned = $role->permissions()
                ->orderBy('name')
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'key' => $p->key ?? $p->name,
                        'name' => $p->name,
                        'description' => $p->description ?? null,
                    ];
                });

            return Inertia::render('Admin/Rbac/Roles/Edit', [
                'role' => $role,
                'apps' => $apps,
                'available_permissions' => $available,
                'assigned_permissions' => $assigned,
                'filters' => [ 'q' => $q ],
            ]);
        })->name('roles.edit');
        Route::put('/roles/{role}', function (\Spatie\Permission\Models\Role $role) {
            $data = request()->validate([
                'name' => ['required', 'string', 'max:255'],
                'guard_name' => ['nullable', 'string', 'max:255'],
                'team_id' => ['nullable', 'integer'],
                'slug' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'is_mutable' => ['boolean'],
            ]);
            if (isset($role->is_mutable) && !$role->is_mutable) {
                return redirect()->back()->with('error', 'This role is immutable.');
            }
            $role->fill($data);
            $role->save();
            return redirect()->route('admin.rbac.roles.index')->with('success', 'Role updated.');
        })->name('roles.update');
        Route::post('/roles/{role}/permissions/{permission}', function (\Spatie\Permission\Models\Role $role, \Spatie\Permission\Models\Permission $permission) {
            if (isset($role->is_mutable) && !$role->is_mutable) {
                return redirect()->back()->with('error', 'This role is immutable.');
            }
            $role->givePermissionTo($permission);
            return redirect()->back();
        })->name('roles.permissions.attach');
        Route::delete('/roles/{role}/permissions/{permission}', function (\Spatie\Permission\Models\Role $role, \Spatie\Permission\Models\Permission $permission) {
            if (isset($role->is_mutable) && !$role->is_mutable) {
                return redirect()->back()->with('error', 'This role is immutable.');
            }
            $role->revokePermissionTo($permission);
            return redirect()->back();
        })->name('roles.permissions.detach');
    });
    
    // Invites Management
    Route::prefix('invites')->name('invites.')->group(function () {
        Route::get('/', function () {
            $invites = \App\Models\Invite::with('invitedBy')
                ->orderByDesc('created_at')
                ->paginate(10);
            return Inertia::render('Admin/Invites/Index', [
                'invites' => $invites,
            ]);
        })->name('index');
        Route::get('/create', function () {
            // Get all apps keyed by ID for quick lookup
            $apps = \App\Models\App::all()->keyBy('id');
            
            // Get all roles and map them with their app info
            $roles = \Spatie\Permission\Models\Role::query()
                ->orderBy('name')
                ->get()
                ->map(function ($role) use ($apps) {
                    // Default app info for roles without a team
                    $appSlug = 'other';
                    $appName = 'Other';
                    
                    // If role has a team_id that matches an app ID
                    if ($role->team_id && $apps->has($role->team_id)) {
                        $app = $apps[$role->team_id];
                        $appSlug = $app->slug;
                        $appName = $app->name;
                    }
                    
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'app_slug' => $appSlug,
                        'app_name' => $appName,
                    ];
                });
            return Inertia::render('Admin/Invites/Create', [
                'roles' => $roles,
            ]);
        })->name('create');
        Route::post('/', function () {
            $validated = request()->validate([
                'email' => ['required', 'email'],
                'roles' => ['array'],
                'roles.*' => ['integer'],
                'expires_in_hours' => ['required', 'integer', 'min:1', 'max:168'],
            ]);

            // For backward compatibility, default to 'user' role if no roles selected
            $defaultRole = 'user';
            $selectedRoles = [];
            
            if (!empty($validated['roles'])) {
                // Fetch selected roles with team_id for team-scoped assignment later
                $roleModels = \Spatie\Permission\Models\Role::whereIn('id', $validated['roles'])->get();

                // For backward compatibility, keep first role name
                $defaultRole = optional($roleModels->first())->name ?? 'hub user';

                // Persist full role info for acceptance flow
                $selectedRoles = $roleModels->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'team_id' => $role->team_id, // null for global roles
                    ];
                })->values()->all();
            } else {
                // Default to 'hub user' role if none selected (metadata empty)
                $selectedRoles = [];
                $defaultRole = 'hub user';
            }

            $invite = \App\Models\Invite::create([
                'email' => $validated['email'],
                'role' => $defaultRole, // Keep for backward compatibility
                'token' => \App\Models\Invite::generateToken(),
                'expires_at' => now()->addHours($validated['expires_in_hours']),
                'invited_by_user_id' => auth()->id(),
                'metadata' => [
                    'roles' => $selectedRoles, // array of {id,name,team_id} or empty
                    'name' => request('name'),
                    'username' => request('username'),
                ],
            ]);

            // Send invitation email
            try {
                \Illuminate\Support\Facades\Mail::to($invite->email)
                    ->send(new \App\Mail\InvitationMail($invite));
            } catch (\Throwable $e) {
                // Swallow mail errors to avoid blocking UI; consider logging in real app
            }

            return redirect()->route('admin.invites.index');
        })->name('store');
        Route::get('/{invite}', function (\App\Models\Invite $invite) {
            return Inertia::render('Admin/Invites/Show', [
                'invite' => $invite->load('invitedBy'),
            ]);
        })->name('show');
        Route::delete('/{invite}', function (\App\Models\Invite $invite) {
            $invite->delete();
            return redirect()->route('admin.invites.index');
        })->name('destroy');
        Route::post('/{invite}/resend', function (\App\Models\Invite $invite) {
            try {
                \Illuminate\Support\Facades\Mail::to($invite->email)
                    ->send(new \App\Mail\InvitationMail($invite));
            } catch (\Throwable $e) {
                // Swallow mail errors; consider logging
            }
            return redirect()->back();
        })->name('resend');
    });
    
    // Super Admin Management
    Route::prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', function () {
            $superAdmins = \App\Models\User::role('super_admin')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            $users = \App\Models\User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            return Inertia::render('Admin/SuperAdmin/Index', [
                'superAdmins' => $superAdmins,
                'users' => $users,
            ]);
        })->name('index');

        Route::post('/grant', function () {
            $data = request()->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ]);
            $user = \App\Models\User::find($data['user_id']);
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
            if ($user && !$user->hasRole('super_admin')) {
                $user->assignRole($role);
            }
            return redirect()->back()->with('success', 'Super admin granted.');
        })->name('grant');

        Route::post('/revoke', function () {
            $data = request()->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ]);
            $user = \App\Models\User::find($data['user_id']);
            if ($user && $user->hasRole('super_admin')) {
                $user->removeRole('super_admin');
            }
            return redirect()->back()->with('success', 'Super admin revoked.');
        })->name('revoke');
    });
    
    // Admin Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');
});

// Profile Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', function () {
        return Inertia::render('Profile/Edit');
    })->name('profile.edit');
});

// (Removed duplicate guest invite accept route; see routes/auth.php for invites.accept/process)