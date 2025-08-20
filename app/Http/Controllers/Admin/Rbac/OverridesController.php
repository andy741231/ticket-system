<?php

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rbac\OverrideRequest;
use App\Models\UserPermissionOverride;
use App\Models\User;
use App\Models\Permission;
use App\Models\App as AppModel;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OverridesController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $overrides = UserPermissionOverride::query()
            ->with(['user:id,name', 'permission:id,key,name', 'app:id,slug,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('reason', 'like', "%{$q}%")
                      ->orWhere('effect', 'like', "%{$q}%")
                      ->orWhereHas('user', function ($u) use ($q) {
                          $u->where('name', 'like', "%{$q}%");
                      })
                      ->orWhereHas('permission', function ($p) use ($q) {
                          $p->where('key', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                      })
                      ->orWhereHas('app', function ($a) use ($q) {
                          $a->where('slug', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                      });
                });
            })
            ->select(['id', 'user_id', 'permission_id', 'team_id', 'effect', 'reason', 'expires_at', 'created_at'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Rbac/Overrides/Index', [
            'overrides' => $overrides,
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function create()
    {
        $users = User::query()->select(['id', 'name'])->orderBy('name')->limit(50)->get();
        $permissions = Permission::query()->select(['id', 'key', 'name'])->orderBy('key')->get();
        $apps = AppModel::query()->select(['id', 'slug', 'name'])->orderBy('slug')->get();
        return Inertia::render('Admin/Rbac/Overrides/Create', [
            'users' => $users,
            'permissions' => $permissions,
            'apps' => $apps,
        ]);
    }

    public function store(OverrideRequest $request, PermissionService $perm): RedirectResponse
    {
        $data = $request->validated();
        $ovr = UserPermissionOverride::create($data);
        $perm->flushUserCache((int) $data['user_id']);

        return redirect()->route('admin.rbac.overrides.index')
            ->with('success', 'Override created');
    }

    public function edit(UserPermissionOverride $override)
    {
        $override->load(['user:id,name', 'permission:id,key,name', 'app:id,slug,name']);
        $users = User::query()->select(['id', 'name'])->orderBy('name')->limit(50)->get();
        $permissions = Permission::query()->select(['id', 'key', 'name'])->orderBy('key')->get();
        $apps = AppModel::query()->select(['id', 'slug', 'name'])->orderBy('slug')->get();
        return Inertia::render('Admin/Rbac/Overrides/Edit', [
            'override' => $override,
            'users' => $users,
            'permissions' => $permissions,
            'apps' => $apps,
        ]);
    }

    public function update(OverrideRequest $request, UserPermissionOverride $override, PermissionService $perm): RedirectResponse
    {
        $data = $request->validated();
        $override->update($data);
        $perm->flushUserCache((int) $override->user_id);

        return redirect()->route('admin.rbac.overrides.index')
            ->with('success', 'Override updated');
    }

    public function destroy(UserPermissionOverride $override, PermissionService $perm): RedirectResponse
    {
        $userId = (int) $override->user_id;
        $override->delete();
        $perm->flushUserCache($userId);
        return redirect()->route('admin.rbac.overrides.index')
            ->with('success', 'Override deleted');
    }
}
