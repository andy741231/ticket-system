<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-roles {email} {roles*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign roles to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $roleNames = $this->argument('roles');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        // Assign roles by name
        $user->syncRoles($roleNames);

        $this->info("Successfully assigned roles to user: {$user->email}");
        $this->line("Assigned roles: " . $user->roles->pluck('name')->implode(', '));

        return 0;
    }
}
