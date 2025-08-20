<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('roles')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        // Show only team-scoped roles (exclude global/null team) for assignment
        $roles = Role::query()
            ->whereNotNull('team_id')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->whereNotNull('team_id'),
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Assign roles across teams: group selected roles by team_id and sync per team
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? ($registrar->getPermissionsTeamId()) : null;

        // Only allow team-scoped roles; model_has_roles.team_id is non-nullable when teams are enabled
        $selectedRoles = Role::whereIn('id', $validated['roles'])->whereNotNull('team_id')->get();
        // Group by normalized key so null doesn't become empty-string and break lookups
        $byTeam = $selectedRoles->groupBy(function ($r) {
            return is_null($r->team_id) ? 'null' : (string) ((int) $r->team_id);
        });

        foreach ($byTeam as $teamKey => $rolesForTeam) {
            $teamIdNorm = ($teamKey === 'null') ? null : (int) $teamKey;
            $registrar->setPermissionsTeamId($teamIdNorm);
            $user->syncRoles($rolesForTeam);
        }

        // restore previous team context
        $registrar->setPermissionsTeamId($prevTeam);

        // Flush permission cache so changes take effect immediately
        app(\App\Services\PermissionService::class)->flushUserCache($user->id);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created successfully!');
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load('roles');
        
        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'tickets' => $user->tickets()->latest()->take(5)->get(),
            'can' => [
                'update' => auth()->user()->can('update', $user),
                'delete' => auth()->user()->can('delete', $user),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user)
    {
        $this->authorize('update', $user);

        // Show only team-scoped roles (exclude global/null team) for assignment
        $roles = Role::query()
            ->whereNotNull('team_id')
            ->orderBy('name')
            ->get();

        // Preselect all assigned role IDs across all teams (ignore registrar team context)
        $assignedRoleIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->pluck('role_id')
            ->toArray();
        
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $assignedRoleIds,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->whereNotNull('team_id'),
            ],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync roles across teams (only when editing someone else)
        if (auth()->user()->id !== $user->id) {
            /** @var PermissionRegistrar $registrar */
            $registrar = app(PermissionRegistrar::class);
            $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? ($registrar->getPermissionsTeamId()) : null;

            // Selected roles grouped by their team
            // Only allow team-scoped roles; prevent null team_id inserts
            $selectedRoles = Role::whereIn('id', $validated['roles'])->whereNotNull('team_id')->get();
            // Normalize grouping keys so that null remains addressable
            $selectedByTeam = $selectedRoles->groupBy(function ($r) {
                return is_null($r->team_id) ? 'null' : (string) ((int) $r->team_id);
            });

            // Existing teams where the user currently has roles (normalize to the same key space)
            $existingTeams = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->pluck('team_id')
                ->map(function ($v) {
                    return is_null($v) ? 'null' : (string) ((int) $v);
                });

            $allKeys = collect($existingTeams)->merge($selectedByTeam->keys())->unique()->values();

            foreach ($allKeys as $teamKey) {
                $teamIdNorm = ($teamKey === 'null') ? null : (int) $teamKey;
                $registrar->setPermissionsTeamId($teamIdNorm);
                $rolesForTeam = $selectedByTeam->get($teamKey, collect());
                $user->syncRoles($rolesForTeam);
            }

            // restore previous team context
            $registrar->setPermissionsTeamId($prevTeam);

            // Flush permission cache so changes take effect immediately
            app(\App\Services\PermissionService::class)->flushUserCache($user->id);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Prevent deleting self
        if (auth()->user()->id === $user->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted successfully!');
    }
}
