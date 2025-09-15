<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $admin = \App\Models\User::where('email', 'admin@example.com')->first();
        
        if (!$admin) {
            \App\Models\User::create([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('123'),
                'email_verified_at' => now(),
            ]);
        } else {
            // Update existing admin user with the correct password and role
            $admin->update([
                'username' => 'admin',
                'password' => bcrypt('123'),
                'email_verified_at' => now(),
            ]);
            // Note: Do not assign roles here when Teams are enabled. Per-app role assignments
            // are handled in RbacSeeder using the appropriate team (app) context.
        }
    }
}
