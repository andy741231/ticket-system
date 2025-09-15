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
use Illuminate\Support\Facades\Schema;

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
        // Include app slug/name for grouping/sorting in the UI (if apps table exists)
        if (Schema::hasTable('apps')) {
            $roles = Role::query()
                ->leftJoin('apps as a', 'roles.team_id', '=', 'a.id')
                ->whereNotNull('roles.team_id')
                ->orderBy('a.slug')
                ->orderBy('roles.name')
                ->select(['roles.id','roles.name','roles.slug','roles.team_id','a.slug as app_slug','a.name as app_name'])
                ->get();
        } else {
            $roles = Role::query()
                ->whereNotNull('roles.team_id')
                ->orderBy('roles.name')
                ->get(['roles.id','roles.name','roles.slug','roles.team_id'])
                ->map(function ($r) {
                    $r->app_slug = 'other';
                    $r->app_name = 'Other';
                    return $r;
                });
        }
        
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

        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'roles' => 'required|array',
                'roles.*' => [
                    'integer',
                    Rule::exists('roles', 'id')->whereNotNull('team_id'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        $createData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ];
        $user = User::create($createData);

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
            ->with('success', 'User created successfully!');
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
        // Include app slug/name for grouping/sorting in the UI (if apps table exists)
        if (Schema::hasTable('apps')) {
            $roles = Role::query()
                ->leftJoin('apps as a', 'roles.team_id', '=', 'a.id')
                ->whereNotNull('roles.team_id')
                ->orderBy('a.slug')
                ->orderBy('roles.name')
                ->select(['roles.id','roles.name','roles.slug','roles.team_id','a.slug as app_slug','a.name as app_name'])
                ->get();
        } else {
            $roles = Role::query()
                ->whereNotNull('roles.team_id')
                ->orderBy('roles.name')
                ->get(['roles.id','roles.name','roles.slug','roles.team_id'])
                ->map(function ($r) {
                    $r->app_slug = 'other';
                    $r->app_name = 'Other';
                    return $r;
                });
        }

        // Preselect all assigned role IDs across all teams (ignore registrar team context)
        $assignedRoleIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->pluck('role_id')
            ->toArray();
        
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name, // computed accessor for backwards compatibility
                'username' => $user->username,
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

        try {
            // Require roles only when editing someone else. Allow omitting roles when editing self.
            $rules = [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',
            ];
            if (auth()->id() !== $user->id) {
                $rules['roles'] = 'required|array';
                $rules['roles.*'] = [
                    'integer',
                    Rule::exists('roles', 'id')->whereNotNull('team_id'),
                ];
            }

            $validated = $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        $updateData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
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
            ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Prevent deleting self
        if (auth()->user()->id === $user->id) {
            return redirect()
                ->back()
                ->with('error', 'You cannot delete your own account.');
        }

        try {
            $user->delete();

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'User deleted successfully!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete user. Please try again.');
        }
    }
}
