<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use App\Models\App as SubApp;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $app = $request->attributes->get('current_app');
        $teamId = $request->attributes->get('current_team_id');
        $availableApps = Schema::hasTable('apps')
            ? SubApp::query()->orderBy('name')->get(['id', 'slug', 'name'])
            : collect();

        /** @var PermissionService $perm */
        $perm = app(PermissionService::class);

        // Cross-app Hub access flag (independent of current app context)
        $usersApp = Schema::hasTable('apps') ? SubApp::query()->where('slug', 'hub')->first() : null;
        $usersTeamId = $usersApp?->id;
        // Cross-app Tickets/Directory access flags (independent of current app context)
        $ticketsApp = Schema::hasTable('apps') ? SubApp::query()->where('slug', 'tickets')->first() : null;
        $ticketsTeamId = $ticketsApp?->id;
        $directoryApp = Schema::hasTable('apps') ? SubApp::query()->where('slug', 'directory')->first() : null;
        $directoryTeamId = $directoryApp?->id;
        // Cross-app Newsletter access flag (independent of current app context)
        $newsletterApp = Schema::hasTable('apps') ? SubApp::query()->where('slug', 'newsletter')->first() : null;
        $newsletterTeamId = $newsletterApp?->id;

        // Fallback: if the Hub app team row is missing, allow access when the user
        // holds a role named "hub admin" or "hub user" in ANY team context.
        // This bypasses team scoping by querying the pivot directly.
        $hasUsersRoleByName = false;
        if ($user) {
            try {
                $hasUsersRoleByName = DB::table('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('model_has_roles.model_type', '=', get_class($user))
                    ->where('model_has_roles.model_id', '=', $user->getKey())
                    ->whereIn('roles.name', ['hub admin', 'hub user'])
                    ->exists();
            } catch (\Throwable $e) {
                $hasUsersRoleByName = false;
            }
        }

        // Get teams data for avatar matching
        $teams = [];
        try {
            if (Schema::connection('directory')->hasTable('directory_team')) {
                $teams = Team::select(['id', 'name', 'email', 'img'])->get()->toArray();
            }
        } catch (\Throwable $e) {
            // Silently handle if directory connection or table doesn't exist
            $teams = [];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'roles' => $user->getRoleNames(),
                    // Effective, team-scoped permissions with overrides applied
                    'permissions' => $user ? $perm->permissionsFor($user, $teamId) : [],
                    'isSuperAdmin' => $user ? $user->isSuperAdmin() : false,
                    // Explicit, stable booleans for common UI gates (team-scoped)
                    'canManageUsers' => $user ? $perm->can($user, 'hub.user.manage', $teamId) : false,
                    'canManageRoles' => $user ? $perm->can($user, 'admin.rbac.roles.manage', $teamId) : false,
                    'canManagePermissions' => $user ? $perm->can($user, 'admin.rbac.permissions.manage', $teamId) : false,
                    'canManageOverrides' => $user ? $perm->can($user, 'admin.rbac.overrides.manage', $teamId) : false,
                    // Global (teamless) super admin management gate
                    'canManageSuperAdmins' => $user ? $user->isSuperAdmin() : false,
                    'canUpdateTickets' => $user ? $perm->can($user, 'tickets.ticket.update', $teamId) : false,
                    // Cross-app explicit: can the user access Hub area (view or manage) in the Hub app context?
                    'canAccessUsersApp' => $user
                        ? (
                            (
                                $usersTeamId
                                    ? ($perm->can($user, 'hub.user.view', $usersTeamId) || $perm->can($user, 'hub.user.manage', $usersTeamId))
                                    : false
                            )
                            || $hasUsersRoleByName
                        )
                        : false,
                    // Cross-app explicit: can the user access Tickets area?
                    'canAccessTicketsApp' => $user
                        ? (
                            $ticketsTeamId
                                ? $perm->can($user, 'tickets.ticket.view', $ticketsTeamId)
                                : false
                        )
                        : false,
                    // Cross-app explicit: can the user access Directory area?
                    'canAccessDirectoryApp' => $user
                        ? (
                            $directoryTeamId
                                ? $perm->can($user, 'directory.profile.view', $directoryTeamId)
                                : false
                        )
                        : false,
                    // Cross-app explicit: can the user access Newsletter area?
                    'canAccessNewsletterApp' => $user
                        ? (
                            $newsletterTeamId
                                ? $perm->can($user, 'newsletter.app.access', $newsletterTeamId)
                                : false
                        )
                        : false,
                    // Cross-app explicit: can the user manage Newsletter (internal tools)?
                    'canManageNewsletterApp' => $user
                        ? (
                            $newsletterTeamId
                                ? $perm->can($user, 'newsletter.manage', $newsletterTeamId)
                                : false
                        )
                        : false,
                ] : null,
            ],
            'appContext' => [
                'currentApp' => $app ? [
                    'id' => $app->id,
                    'slug' => $app->slug,
                    'name' => $app->name,
                ] : null,
                'teamId' => $teamId,
                'availableApps' => $availableApps,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            // Expose bulk import row-level errors separately from the validation error bag
            'import_errors' => fn () => $request->session()->get('import_errors', []),
            'teams' => $teams,
        ];
    }
}
