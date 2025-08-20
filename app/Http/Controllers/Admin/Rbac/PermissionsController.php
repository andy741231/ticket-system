<?php

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rbac\PermissionRequest;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $permissions = Permission::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('key', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->select(['id', 'name', 'key', 'description', 'guard_name', 'is_mutable'])
            ->orderBy('key')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Rbac/Permissions/Index', [
            'permissions' => $permissions,
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Rbac/Permissions/Create');
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        Permission::create($data);
        return redirect()->route('admin.rbac.permissions.index')->with('success', 'Permission created');
    }

    public function edit(Permission $permission)
    {
        return Inertia::render('Admin/Rbac/Permissions/Edit', [
            'permission' => $permission->only(['id','name','key','description','guard_name','is_mutable']),
        ]);
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        if (!$permission->is_mutable) {
            return redirect()->route('admin.rbac.permissions.index')->with('error', 'This permission is protected and cannot be modified.');
        }

        $data = $request->validated();
        $permission->update($data);
        return redirect()->route('admin.rbac.permissions.index')->with('success', 'Permission updated');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if (!$permission->is_mutable) {
            return redirect()->route('admin.rbac.permissions.index')->with('error', 'This permission is protected and cannot be deleted.');
        }

        $permission->delete();
        return redirect()->route('admin.rbac.permissions.index')->with('success', 'Permission deleted');
    }
}
