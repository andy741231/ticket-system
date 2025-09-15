<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view tickets',
            'create tickets',
            'edit tickets',
            'delete tickets',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage tickets',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign created permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view tickets',
            'create tickets',
            'edit tickets',
            'delete tickets',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage tickets',
            'manage users',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userRole->givePermissionTo([
            'view tickets',
            'create tickets',
            'edit tickets',
        ]);

        // Create regular user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'first_name' => 'Regular',
                'last_name' => 'User',
                'username' => 'user',
                'password' => Hash::make('123'),
                'email_verified_at' => now(),
            ]
        );
        // Note: Do not assign roles here when Teams are enabled; per-app role assignments
        // are handled in RbacSeeder using the appropriate team (app) context.

        // Create some sample tickets
        Ticket::create([
            'user_id' => $user->id,
            'title' => 'First Sample Ticket',
            'description' => 'This is a sample ticket created by the seeder.',
            'status' => 'Received',
            'priority' => 'Medium',
        ]);

        Ticket::create([
            'user_id' => $user->id,
            'title' => 'Second Sample Ticket',
            'description' => 'Another sample ticket with high priority.',
            'status' => 'Approved',
            'priority' => 'High',
        ]);

        Ticket::create([
            'user_id' => $user->id,
            'title' => 'Completed Ticket',
            'description' => 'This ticket has been completed.',
            'status' => 'Completed',
            'priority' => 'Low',
        ]);
    }
}
