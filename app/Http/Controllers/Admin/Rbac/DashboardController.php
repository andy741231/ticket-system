<?php

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\App as SubApp;
use App\Models\UserPermissionOverride;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'apps' => SubApp::query()->count(),
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
            'overrides' => UserPermissionOverride::query()->count(),
        ];

        return Inertia::render('Admin/Rbac/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
