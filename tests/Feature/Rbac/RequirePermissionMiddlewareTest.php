<?php

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RequirePermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the app slug used by SetAppContext exists
        SubApp::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
        SubApp::firstOrCreate(['slug' => 'users'], ['name' => 'Users']);
    }

    #[Test]
    public function blocks_access_without_permission()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/tickets');
        $response->assertStatus(403);
    }

    #[Test]
    public function allows_access_with_role_permission_in_team_context()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tickets = SubApp::where('slug', 'tickets')->first();
        $teamId = $tickets->id;

        $permName = 'tickets.ticket.view';
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $role = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web', 'team_id' => $teamId]);
        $role->givePermissionTo($permName);
        $user->assignRole($role);

        $response = $this->get('/tickets');
        $response->assertStatus(200);
    }

    #[Test]
    public function allows_access_with_allow_override_without_role()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tickets = SubApp::where('slug', 'tickets')->first();
        $teamId = $tickets->id;

        $permName = 'tickets.ticket.view';
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        // Add allow override in team scope
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => $teamId,
            'effect' => 'allow',
            'reason' => 'Middleware allow override',
        ]);

        $response = $this->get('/tickets');
        $response->assertStatus(200);
    }

    #[Test]
    public function deny_override_blocks_even_with_role()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tickets = SubApp::where('slug', 'tickets')->first();
        $teamId = $tickets->id;

        $permName = 'tickets.ticket.view';
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        // Give role permission
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $role = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web', 'team_id' => $teamId]);
        $role->givePermissionTo($permName);
        $user->assignRole($role);

        // Add deny override
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => $teamId,
            'effect' => 'deny',
            'reason' => 'Middleware deny override',
        ]);

        $response = $this->get('/tickets');
        $response->assertStatus(403);
    }
}
