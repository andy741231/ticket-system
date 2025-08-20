<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProductionRbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure sub-applications exist (idempotent)
        $this->call(AppsSeeder::class);

        // Canonical per-app permission definitions and default role grants
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
                    'directory.profile.view',
                    'directory.profile.update',
                    'directory.user.lookup',
                ],
                'user_role_perms' => [
                    'directory.profile.view',
                ],
            ],
        ];

        // Global RBAC admin permissions (not app-scoped)
        $rbacAdminPerms = [
            'admin.rbac.roles.manage',
            'admin.rbac.permissions.manage',
            'admin.rbac.overrides.manage',
        ];

        // Create permissions and ensure descriptions
        foreach ($definitions as $def) {
            foreach ($def['permissions'] as $perm) {
                $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
                if (empty($p->description)) {
                    $p->description = ucfirst(str_replace(['.', '_'], [' ', ' '], $perm));
                    $p->save();
                }
            }
        }
        foreach ($rbacAdminPerms as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            if (empty($p->description)) {
                $p->description = ucfirst(str_replace(['.', '_'], [' ', ' '], $perm));
                $p->save();
            }
        }

        // Create per-app roles and assign permissions
        $apps = DB::table('apps')->get();
        foreach ($apps as $app) {
            $slug = $app->slug;
            $teamId = $app->id;

            // Create roles (immutable admin, mutable user) using unique key (team_id, slug)
            $adminRole = Role::firstOrCreate(
                ['team_id' => $teamId, 'slug' => 'admin', 'guard_name' => 'web'],
                ['name' => 'admin', 'is_mutable' => false, 'description' => 'Administrator']
            );
            $userRole = Role::firstOrCreate(
                ['team_id' => $teamId, 'slug' => 'user', 'guard_name' => 'web'],
                ['name' => 'user', 'is_mutable' => true, 'description' => 'Standard User']
            );

            // Assign app permissions
            $appPerms = $definitions[$slug]['permissions'] ?? [];
            $userPerms = $definitions[$slug]['user_role_perms'] ?? [];
            if (!empty($appPerms)) {
                $adminRole->syncPermissions($appPerms);
            }
            if (!empty($userPerms)) {
                $userRole->syncPermissions($userPerms);
            }

            // Hub app admin role also gets RBAC admin permissions
            if ($slug === 'hub') {
                $adminRole->givePermissionTo($rbacAdminPerms);
            }
        }

        // Optionally create a production admin and assign per-app admin roles
        if (filter_var(env('RBAC_SEED_CREATE_ADMIN', false), FILTER_VALIDATE_BOOL)) {
            $email = (string) env('RBAC_SEED_ADMIN_EMAIL', 'admin@example.com');
            $username = (string) env('RBAC_SEED_ADMIN_USERNAME', 'admin');
            $name = (string) env('RBAC_SEED_ADMIN_NAME', 'Admin');
            $password = (string) env('RBAC_SEED_ADMIN_PASSWORD', 'change-me');

            /** @var User $admin */
            $admin = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]
            );

            // Ensure password matches desired value
            if (!Hash::check($password, $admin->password)) {
                $admin->forceFill(['password' => Hash::make($password)])->save();
            }

            // Assign admin role within each app context (Spatie Teams)
            /** @var \Spatie\Permission\PermissionRegistrar $registrar */
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;

            foreach ($apps as $app) {
                $registrar->setPermissionsTeamId($app->id);
                $role = Role::where(['name' => 'admin', 'guard_name' => 'web', 'team_id' => $app->id])->first();
                if ($role && !$admin->hasRole($role)) {
                    $admin->assignRole($role);
                }
            }

            // Restore previous team context
            $registrar->setPermissionsTeamId($prevTeam);
        }

        // Explicitly do NOT create demo data or legacy global roles here.
    }
}
