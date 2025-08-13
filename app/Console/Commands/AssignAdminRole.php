<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignAdminRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-admin {email} {--create : Create the admin role if it does not exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign the admin role to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $createIfNotExists = $this->option('create');

        // Find the user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        // Check if the admin role exists
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            if ($createIfNotExists) {
                $this->info('Admin role does not exist. Creating it now...');
                $adminRole = Role::create(['name' => 'admin']);
                $this->info('Admin role created successfully.');
            } else {
                $this->error('Admin role does not exist. Use --create option to create it.');
                return 1;
            }
        }

        // Assign the admin role to the user
        $user->assignRole($adminRole);

        $this->info("Successfully assigned admin role to user: {$user->email}");
        return 0;
    }
}
