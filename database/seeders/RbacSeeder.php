<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Permission;
use App\Models\Role;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed app-scoped permissions and roles using Spatie Teams as sub-app context
        $apps = DB::table('apps')->get();

        $definitions = [
            'hub' => [
                'permissions' => [
                    'hub.user.view',
                    'hub.user.create',
                    'hub.user.update',
                    'hub.user.delete',
                    'hub.user.manage',
                ],
                'user_role_perms' => [
                    'hub.user.view',
                ],
            ],
            'tickets' => [
                'permissions' => [
                    'tickets.ticket.view',
                    'tickets.ticket.create',
                    'tickets.ticket.update',
                    'tickets.ticket.delete',
                    'tickets.ticket.manage',
                    'tickets.file.upload',
                ],
                'user_role_perms' => [
                    'tickets.ticket.view',
                    'tickets.ticket.create',
                ],
            ],
            'directory' => [
                'permissions' => [
                    'directory.app.access',
                    'directory.profile.view',
                    'directory.profile.update',
                    'directory.user.lookup',
                    'directory.profile.manage',
                ],
                'user_role_perms' => [
                    'directory.profile.view',
                ],
            ],
            'newsletter' => [
                'permissions' => [
                    'newsletter.app.access',
                ],
                'user_role_perms' => [
                    // Optionally grant basic access to standard users later
                ],
            ],
        ];

        foreach ($definitions as $appSlug => $def) {
            foreach ($def['permissions'] as $perm) {
                $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
                // Ensure description exists
                if (empty($p->description)) {
                    $desc = ucfirst(str_replace(['.', '_'], [' ', ' '], $perm));
                    $p->description = $desc;
                    $p->save();
                }
            }
        }

        // Create RBAC admin permissions (global, assign to Users app admin role)
        $rbacAdminPerms = [
            'admin.rbac.roles.manage',
            'admin.rbac.permissions.manage',
            'admin.rbac.overrides.manage',
        ];
        foreach ($rbacAdminPerms as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            if (empty($p->description)) {
                $desc = ucfirst(str_replace(['.', '_'], [' ', ' '], $perm));
                $p->description = $desc;
                $p->save();
            }
        }

        foreach ($apps as $app) {
            $slug = $app->slug;
            $appId = $app->id;

            // Create per-app roles (team_id = app id)
            $adminRole = Role::firstOrCreate(
                ['slug' => 'admin', 'guard_name' => 'web', 'team_id' => $appId],
                ['name' => 'admin', 'is_mutable' => false, 'description' => 'Administrator']
            );
            $userRole = Role::firstOrCreate(
                ['slug' => 'user', 'guard_name' => 'web', 'team_id' => $appId],
                ['name' => 'user', 'is_mutable' => true, 'description' => 'Standard User']
            );

            // Assign permissions to roles
            $appPerms = $definitions[$slug]['permissions'] ?? [];
            $userPerms = $definitions[$slug]['user_role_perms'] ?? [];

            if (!empty($appPerms)) {
                $adminRole->syncPermissions($appPerms);
            }
            if (!empty($userPerms)) {
                $userRole->syncPermissions($userPerms);
            }

            // Assign RBAC admin permissions to Hub app admin role
            if ($slug === 'hub') {
                $adminRole->givePermissionTo($rbacAdminPerms);
            }
        }

        // Optionally, ensure global roles exist and are immutable (backward compatibility)
        $globalAdmin = Role::firstOrCreate(['slug' => 'admin', 'guard_name' => 'web', 'team_id' => null], ['name' => 'admin', 'is_mutable' => false, 'description' => 'Administrator (Global)']);
        $globalUser = Role::firstOrCreate(['slug' => 'user', 'guard_name' => 'web', 'team_id' => null], ['name' => 'user', 'is_mutable' => true, 'description' => 'Standard User (Global)']);

        // Ensure base permission names from legacy seeder exist (no-op if already present)
        foreach ([
            'view tickets','create tickets','edit tickets','delete tickets',
            'view users','create users','edit users','delete users','manage tickets','manage users'
        ] as $legacy) {
            Permission::firstOrCreate(['name' => $legacy, 'guard_name' => 'web']);
        }

        // Assign per-app admin roles to existing admin if present
        $admin = DB::table('users')->where('email', 'admin@example.com')->first();
        if ($admin) {
            $userModel = app(\App\Models\User::class)->find($admin->id);
            foreach ($apps as $app) {
                $role = Role::where(['slug' => 'admin', 'guard_name' => 'web', 'team_id' => $app->id])->first();
                if ($role) {
                    // Set team context for assignment
                    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($app->id);
                    $userModel->assignRole($role);
                }
            }
            // Reset team context
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        // Assign a per-app user role to demo user if present
        $demo = DB::table('users')->where('email', 'user@example.com')->first();
        if ($demo) {
            $userModel = app(\App\Models\User::class)->find($demo->id);
            $ticketsApp = $apps->firstWhere('slug', 'tickets');
            $ticketsRole = $ticketsApp ? Role::where(['slug' => 'user', 'guard_name' => 'web', 'team_id' => $ticketsApp->id])->first() : null;
            if ($ticketsApp && $ticketsRole) {
                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($ticketsApp->id);
                $userModel->assignRole($ticketsRole);
                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            }

            // Assign directory admin role to the same user for demo purposes
            $directoryApp = $apps->firstWhere('slug', 'directory');
            $directoryAdminRole = $directoryApp ? Role::where(['slug' => 'admin', 'guard_name' => 'web', 'team_id' => $directoryApp->id])->first() : null;
            if ($directoryApp && $directoryAdminRole) {
                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($directoryApp->id);
                $userModel->assignRole($directoryAdminRole);
                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
            }
        }
    }
}
