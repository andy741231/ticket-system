<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LimitedRbacUserSeeder extends Seeder
{
    /**
     * Seed a user lacking admin.rbac.* permissions for manual UI tests.
     */
    public function run(): void
    {
        // Create or update the limited user
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => 'limited@example.com'],
            [
                'name' => 'Limited RBAC User',
                'password' => Hash::make('123'),
            ]
        );

        // Ensure the user is NOT super admin
        if (method_exists($user, 'removeRole')) {
            // In case any global admin role was previously assigned in dev
            try {
                $user->removeRole('admin');
            } catch (\Throwable) {
                // ignore if not assigned
            }
        }

        // Remove any existing admin.rbac.* overrides if present
        $rbacAdminPerms = [
            'admin.rbac.roles.manage',
            'admin.rbac.permissions.manage',
            'admin.rbac.overrides.manage',
        ];

        foreach ($rbacAdminPerms as $name) {
            $perm = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            UserPermissionOverride::query()
                ->where('user_id', $user->id)
                ->where('permission_id', $perm->id)
                ->delete();
        }

        // Optionally assign a very limited app role or no roles at all.
        // Leaving as-is to ensure lack of admin.rbac.* is the distinguishing factor.
    }
}
