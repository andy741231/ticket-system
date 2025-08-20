<?php

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Spatie\Permission\PermissionRegistrar;

class PermissionCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SubApp::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
    }

    #[Test]
    public function caches_are_flushed_when_role_is_attached_to_user()
    {
        $tickets = SubApp::where('slug', 'tickets')->first();
        $user = User::factory()->create();

        $perm = Permission::create([
            'name' => 'tickets.ticket.update',
            'key' => 'tickets.ticket.update',
            'description' => 'Update tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $role = Role::create([
            'name' => 'Tickets Agent',
            'guard_name' => 'web',
            'team_id' => $tickets->id,
            'slug' => 'tickets-agent',
            'description' => 'Agent',
            'is_mutable' => true,
        ]);
        $role->givePermissionTo($perm);

        $svc = app(PermissionService::class);

        // Cached deny initially
        $this->assertFalse($svc->can($user, 'tickets.ticket.update', $tickets->id));

        // Attach role under the tickets team context so assignment is team-scoped
        $registrar = app(PermissionRegistrar::class);
        $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;
        $registrar->setPermissionsTeamId($tickets->id);
        try {
            // Attach role should fire Spatie RoleAttached event and our listener flushes cache
            $user->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($prevTeam);
        }

        $this->assertTrue($svc->can($user, 'tickets.ticket.update', $tickets->id));
    }

    #[Test]
    public function caches_are_flushed_when_permission_is_granted_to_a_role()
    {
        $tickets = SubApp::where('slug', 'tickets')->first();
        $user = User::factory()->create();

        $perm = Permission::create([
            'name' => 'tickets.ticket.close',
            'key' => 'tickets.ticket.close',
            'description' => 'Close tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $role = Role::create([
            'name' => 'Tickets Moderator',
            'guard_name' => 'web',
            'team_id' => $tickets->id,
            'slug' => 'tickets-moderator',
            'description' => 'Moderator',
            'is_mutable' => true,
        ]);

        // Assign role in tickets team context
        $registrar = app(PermissionRegistrar::class);
        $prevTeam = method_exists($registrar, 'getPermissionsTeamId') ? $registrar->getPermissionsTeamId() : null;
        $registrar->setPermissionsTeamId($tickets->id);
        try {
            $user->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($prevTeam);
        }

        $svc = app(PermissionService::class);

        // Initially no permission
        $this->assertFalse($svc->can($user, 'tickets.ticket.close', $tickets->id));

        // Grant permission to role should fire Spatie PermissionAttached event and our listener flushes caches
        $role->givePermissionTo($perm);

        $this->assertTrue($svc->can($user, 'tickets.ticket.close', $tickets->id));
    }
}
