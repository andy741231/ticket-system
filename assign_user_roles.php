<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

// Find the user
$user = User::where('email', 'user@example.com')->first();

if (!$user) {
    echo "User not found.\n";
    exit(1);
}

// Get the roles with their team context
$ticketRole = \Spatie\Permission\Models\Role::where('name', 'ticket-user')
    ->where('team_id', 2) // Team ID for tickets
    ->first();

$directoryRole = \Spatie\Permission\Models\Role::where('name', 'directory-user')
    ->where('team_id', 3) // Team ID for directory
    ->first();

if (!$ticketRole || !$directoryRole) {
    echo "One or more roles not found.\n";
    exit(1);
}

// Assign the roles with team context
$user->roles()->syncWithoutDetaching([
    $ticketRole->id => ['team_id' => 2],
    $directoryRole->id => ['team_id' => 3]
]);

echo "Successfully assigned roles to user: " . $user->email . "\n";

// Verify the roles were assigned
$assignedRoles = $user->roles()->pluck('name')->toArray();
echo "Assigned roles: " . implode(', ', $assignedRoles) . "\n";
