<?php

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rbac\RoleRequest;
use App\Models\Role;
use App\Models\Permission;
use App\Models\App as AppModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $roles = Role::query()
            ->leftJoin('apps as a', 'roles.team_id', '=', 'a.id')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('roles.name', 'like', "%{$q}%")
                      ->orWhere('roles.slug', 'like', "%{$q}%")
                      ->orWhere('a.slug', 'like', "%{$q}%");
                });
            })
            ->orderBy('roles.name')
            ->select(['roles.id', 'roles.name', 'roles.guard_name', 'roles.team_id', 'roles.slug', 'roles.description', 'roles.is_mutable', 'a.slug as app_slug'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Rbac/Roles/Index', [
            'roles' => $roles,
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function create()
    {
        $apps = AppModel::query()->select(['id', 'slug', 'name'])->orderBy('slug')->get();
        return Inertia::render('Admin/Rbac/Roles/Create', [
            'apps' => $apps,
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        Role::create($data);
        return redirect()->route('admin.rbac.roles.index')->with('success', 'Role created');
    }

    public function edit(Request $request, Role $role)
    {
        $q = trim((string) $request->query('q', ''));
        $apps = AppModel::query()->select(['id', 'slug', 'name'])->orderBy('slug')->get();
        $availablePermissions = Permission::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('key', 'like', "%{$q}%");
                });
            })
            ->orderBy('key')
            ->select(['id','name','key','description','is_mutable'])
            ->paginate(15)
            ->withQueryString();

        $assignedPermissions = $role->permissions()
            ->orderBy('key')
            ->select(['permissions.id','permissions.name','permissions.key','permissions.description','permissions.is_mutable'])
            ->get();

        return Inertia::render('Admin/Rbac/Roles/Edit', [
            'role' => $role->only(['id','name','guard_name','team_id','slug','description','is_mutable']),
            'apps' => $apps,
            'available_permissions' => $availablePermissions,
            'assigned_permissions' => $assignedPermissions,
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        if (!$role->is_mutable) {
            return redirect()->route('admin.rbac.roles.index')->with('error', 'This role is protected and cannot be modified.');
        }

        $data = $request->validated();
        $role->update($data);
        return redirect()->route('admin.rbac.roles.index')->with('success', 'Role updated');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (!$role->is_mutable) {
            return redirect()->route('admin.rbac.roles.index')->with('error', 'This role is protected and cannot be deleted.');
        }

        $role->delete();
        return redirect()->route('admin.rbac.roles.index')->with('success', 'Role deleted');
    }

    public function attachPermission(Role $role, Permission $permission): RedirectResponse
    {
        if (!$role->is_mutable) {
            return back()->with('error', 'This role is protected and cannot be modified.');
        }

        $role->givePermissionTo($permission);
        return back()->with('success', 'Permission attached to role');
    }

    public function detachPermission(Role $role, Permission $permission): RedirectResponse
    {
        if (!$role->is_mutable) {
            return back()->with('error', 'This role is protected and cannot be modified.');
        }

        $role->revokePermissionTo($permission);
        return back()->with('success', 'Permission detached from role');
    }
}
