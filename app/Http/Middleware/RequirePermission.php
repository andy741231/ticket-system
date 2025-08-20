<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('perm:permission.name|alt.permission')
     */
    public function handle(Request $request, Closure $next, string $permissionList): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $teamId = $request->attributes->get('current_team_id');
        /** @var PermissionService $svc */
        $svc = app(PermissionService::class);

        // Support multiple permissions separated by '|': pass if any is allowed
        $perms = array_map('trim', explode('|', $permissionList));
        $allowed = false;
        $evaluations = [];
        foreach ($perms as $perm) {
            $res = $svc->can($user, $perm, $teamId);
            if (config('app.debug')) {
                $evaluations[] = ['perm' => $perm, 'result' => (bool) $res];
            }
            if ($res) {
                $allowed = true;
                break;
            }
        }

        // Fallback: If accessing Hub admin area but the Hub app team row is missing (teamId null),
        // allow users who hold a role named "hub admin" or "hub user" in any team context.
        // Only applies for Hub app permissions to avoid broad bypasses.
        if (!$allowed) {
            $targetsUsersApp = false;
            foreach ($perms as $p) {
                if ($p === 'hub.user.view' || $p === 'hub.user.manage') {
                    $targetsUsersApp = true;
                    break;
                }
            }

            if ($targetsUsersApp && $teamId === null) {
                try {
                    $hasHubRole = DB::table('model_has_roles')
                        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->where('model_has_roles.model_type', '=', get_class($user))
                        ->where('model_has_roles.model_id', '=', $user->getKey())
                        ->whereIn('roles.name', ['hub admin', 'hub user'])
                        ->exists();
                    if ($hasHubRole) {
                        $allowed = true;
                    }
                } catch (\Throwable $e) {
                    // ignore and proceed to forbid
                }
            }
        }

        if (config('app.debug')) {
            logger()->debug('[RequirePermission] evaluation', [
                'user_id' => $user->id,
                'teamId' => $teamId,
                'perms' => $perms,
                'evaluations' => $evaluations,
                'allowed' => $allowed,
            ]);
        }

        if (!$allowed) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
