<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illware_Contracts_Console_Kernel::class);
$kernel->bootstrap();

use App\Models\User;

// Find the user
$user = User::where('email', 'user@example.com')->first();

if (!$user) {
    echo "User not found.\n";
    exit(1);
}

// Assign roles (IDs 6 for ticket-user and 8 for directory-user)
$user->syncRoles([6, 8]);

echo "Successfully assigned roles to user: " . $user->email . "\n";

// Verify the roles were assigned
$assignedRoles = $user->roles()->pluck('name')->toArray();
echo "Assigned roles: " . implode(', ', $assignedRoles) . "\n";
