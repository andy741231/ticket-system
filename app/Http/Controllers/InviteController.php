<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

// Models
use App\Models\Invite;
use App\Models\User;

class InviteController extends Controller
{
    /**
     * Show invite acceptance page (guest route).
     */
    public function accept(Request $request, string $token)
    {
        $invite = Invite::where('token', $token)->first();

        // Validate invite
        if (!$invite || ($invite->expires_at && now()->greaterThan($invite->expires_at))) {
            return Inertia::render('Auth/InviteInvalid');
        }

        return Inertia::render('Auth/InviteAccept', [
            'email' => $invite->email,
            'token' => $token,
            'prefill' => [
                'first_name' => $invite->metadata['first_name'] ?? null,
                'last_name' => $invite->metadata['last_name'] ?? null,
                'username' => $invite->metadata['username'] ?? null,
                'roles' => $invite->metadata['roles'] ?? [],
            ],
        ]);
    }

    /**
     * Process invite acceptance (create user, assign roles, login).
     */
    public function processAcceptance(Request $request, string $token)
    {
        $invite = Invite::where('token', $token)->first();
        if (!$invite || ($invite->expires_at && now()->greaterThan($invite->expires_at))) {
            return back()->withErrors(['invite' => 'This invitation is invalid or has expired.']);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create user (or find existing by email)
        $user = User::firstOrCreate(
            ['email' => $invite->email],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => $data['username'] ?? null,
                'password' => Hash::make($data['password']),
            ]
        );

        // Assign roles (team-scoped)
        $metaRoles = (array)($invite->metadata['roles'] ?? []);
        if (!empty($metaRoles)) {
            // Support both new format (array of {name, team_id}) and legacy format (array of names)
            $isAssocFormat = is_array($metaRoles[0] ?? null) && array_key_exists('name', $metaRoles[0]);

            // Capture previous team context
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;

            try {
                if ($isAssocFormat) {
                    // Group role names by team_id and assign per team
                    $byTeam = collect($metaRoles)
                        ->groupBy(function ($r) { return $r['team_id'] === null ? 'null' : (string) $r['team_id']; })
                        ->map(fn ($items) => collect($items)->pluck('name')->values());

                    foreach ($byTeam as $teamKey => $roleNames) {
                        $teamIdNorm = ($teamKey === 'null') ? null : (int) $teamKey;
                        $registrar->setPermissionsTeamId($teamIdNorm);
                        $user->syncRoles($roleNames->all());
                    }
                } else {
                    // Legacy formats
                    $first = $metaRoles[0] ?? null;
                    if (is_numeric($first)) {
                        // Roles provided as IDs -> map to names grouped by team
                        $roleModels = \Spatie\Permission\Models\Role::whereIn('id', $metaRoles)->get(['id','name','team_id']);
                        $byTeam = $roleModels->groupBy(function ($r) { return $r->team_id === null ? 'null' : (string) $r->team_id; })
                            ->map(fn ($items) => $items->pluck('name')->values());

                        foreach ($byTeam as $teamKey => $roleNames) {
                            $teamIdNorm = ($teamKey === 'null') ? null : (int) $teamKey;
                            $registrar->setPermissionsTeamId($teamIdNorm);
                            $user->syncRoles($roleNames->all());
                        }
                    } else {
                        // Roles provided as names without team context
                        $user->syncRoles($metaRoles);
                    }
                }
            } catch (\Throwable $e) {
                // swallow errors to not block invite acceptance
            } finally {
                // Restore previous team context
                $registrar->setPermissionsTeamId($prevTeam);
            }

            // Flush permission cache for this user
            try {
                app(\App\Services\PermissionService::class)->flushUserCache($user->id);
            } catch (\Throwable $e) {
                // ignore
            }
        } elseif (!empty($invite->role)) {
            try {
                $user->assignRole($invite->role);
            } catch (\Throwable $e) {
                // Ignore role assignment errors silently
            }
        }

        // Mark invite accepted (delete or set timestamp)
        if ($user->wasRecentlyCreated) {
            // If new user, just delete invite
            $invite->delete();
        } else {
            // Existing user by email, still invalidate invite
            $invite->delete();
        }

        // Log the user in
        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
