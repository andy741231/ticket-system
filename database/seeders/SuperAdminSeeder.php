<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure super_admin role exists (global: team_id = null)
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web', 'team_id' => null],
            ['is_mutable' => false]
        );

        // Create or update the super admin user
        $user = User::firstOrCreate(
            ['username' => 'mchan3'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'mchan3@example.com',
                'password' => Hash::make('123'),
                'email_verified_at' => now(),
            ]
        );

        // Ensure password is set to 123 as requested
        if (!Hash::check('123', $user->password)) {
            $user->password = Hash::make('123');
            $user->save();
        }

        // Assign the super_admin role under each app context because teams-enabled pivot requires a non-null team_id.
        $apps = DB::table('apps')->get();
        /** @var \Spatie\Permission\PermissionRegistrar $registrar */
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $prev = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;
        foreach ($apps as $app) {
            // Set team context and assign role if not already assigned in that context
            $registrar->setPermissionsTeamId($app->id);
            if (!$user->hasRole($superAdminRole)) {
                $user->assignRole($superAdminRole);
            }
        }
        // Restore previous context
        $registrar->setPermissionsTeamId($prev);
    }
}
