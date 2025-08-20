<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UsersViewOnlySeeder extends Seeder
{
    /**
     * Seed a dev-only user with view-only access to the Users app.
     * Email: viewonly@example.com / Password: 123
     */
    /**
     * Create a basic view-only user
     */
    public function run(): void
    {
        try {
            // Create or update the user
            $user = User::updateOrCreate(
                ['email' => 'viewonly@example.com'],
                [
                    'name' => 'View Only User',
                    'password' => Hash::make('123'),
                    // Add any other required fields here
                    'username' => 'viewonly',
                    'email_verified_at' => now(),
                ]
            );

            $this->command->info('View-only user created/updated successfully!');
            $this->command->info('Email: viewonly@example.com');
            $this->command->info('Password: 123');
            
        } catch (\Exception $e) {
            $this->command->error('Error creating view-only user: ' . $e->getMessage());
            throw $e;
        }
    }
}
