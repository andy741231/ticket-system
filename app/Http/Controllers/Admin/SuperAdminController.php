<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\App as AppModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminController extends Controller
{
    /**
     * List super admins and provide management UI (restricted to current super admins).
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isSuperAdmin(), 403);

        $superAdminRoleIds = \Spatie\Permission\Models\Role::query()
            ->where('name', 'super_admin')
            ->pluck('id');

        $superAdmins = User::query()
            ->whereExists(function ($q) use ($superAdminRoleIds) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereIn('model_has_roles.role_id', $superAdminRoleIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $allUsers = User::query()
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        return Inertia::render('Admin/SuperAdmin/Index', [
            'superAdmins' => $superAdmins,
            'users' => $allUsers,
        ]);
    }

    /**
     * Grant global super_admin role to a user.
     */
    public function grant(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isSuperAdmin(), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::findOrFail($data['user_id']);

        // Assign the super_admin role within a concrete team context (teams are required in schema)
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;
        // Pick a canonical team id to represent global scope, prefer the 'hub' app, else any first app id
        $globalTeamId = AppModel::query()->where('slug', 'hub')->value('id')
            ?? AppModel::query()->orderBy('id')->value('id');
        if ($globalTeamId === null) {
            // If no apps exist, create a placeholder
            $globalTeamId = AppModel::query()->create(['slug' => 'hub', 'name' => 'Hub'])->id;
        }
        $registrar->setPermissionsTeamId($globalTeamId);

        if (!$target->hasRole('super_admin')) {
            $target->assignRole('super_admin');
        }

        // Restore previous team context
        $registrar->setPermissionsTeamId($prevTeam);

        // Remove duplicates of super_admin in other teams, keep the canonical team assignment
        $roleIds = \Spatie\Permission\Models\Role::where('name', 'super_admin')->pluck('id');
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $target->id)
            ->whereIn('role_id', $roleIds)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $globalTeamId)
            ->delete();

        // Flush permission cache
        app(\App\Services\PermissionService::class)->flushUserCache($target->id);

        logger()->info('[SuperAdmin] granted', ['actor_id' => $actor->id, 'target_id' => $target->id]);

        return back()->with('status', 'Super admin granted.');
    }

    /**
     * Revoke global super_admin role from a user (remove all occurrences regardless of team_id).
     */
    public function revoke(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isSuperAdmin(), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $target = User::findOrFail($data['user_id']);

        $roleIds = \Spatie\Permission\Models\Role::where('name', 'super_admin')->pluck('id');
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $target->id)
            ->whereIn('role_id', $roleIds)
            ->delete();

        // Flush permission cache
        app(\App\Services\PermissionService::class)->flushUserCache($target->id);

        logger()->info('[SuperAdmin] revoked', ['actor_id' => $actor->id, 'target_id' => $target->id]);

        return back()->with('status', 'Super admin revoked.');
    }
}
