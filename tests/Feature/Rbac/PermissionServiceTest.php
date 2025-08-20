<?php

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(PermissionService::class);
    }

    #[Test]
    public function deny_override_trumps_role_permission()
    {
        $user = User::factory()->create();
        $app = SubApp::create(['slug' => 'tickets', 'name' => 'Tickets']);
        $teamId = $app->id;

        // Permission exists
        $permName = 'tickets.ticket.manage';
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        // Give role with permission under team context
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'team_id' => $teamId]);
        $role->givePermissionTo($permName);
        $user->assignRole($role);

        // Add a deny override for the user in same team
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => Permission::where('name', $permName)->first()->id,
            'team_id' => $teamId,
            'effect' => 'deny',
            'reason' => 'Testing deny precedence',
        ]);

        $this->assertFalse($this->svc->can($user, $permName, $teamId));
    }

    #[Test]
    public function allow_override_grants_even_without_role()
    {
        $user = User::factory()->create();
        $app = SubApp::create(['slug' => 'tickets', 'name' => 'Tickets']);
        $teamId = $app->id;

        $permName = 'tickets.ticket.view';
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        // No roles assigned

        // Add an allow override (team-scoped)
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => $teamId,
            'effect' => 'allow',
            'reason' => 'Testing allow',
        ]);

        $this->assertTrue($this->svc->can($user, $permName, $teamId));
    }

    #[Test]
    public function global_override_applies_to_team_context_queries()
    {
        $user = User::factory()->create();
        $app = SubApp::create(['slug' => 'tickets', 'name' => 'Tickets']);
        $teamId = $app->id;

        $permName = 'tickets.ticket.update';
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        // Grant via role under team context
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web', 'team_id' => $teamId]);
        $role->givePermissionTo($permName);
        $user->assignRole($role);

        // Add a global deny override (team_id null)
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'deny',
            'reason' => 'Global deny',
        ]);

        $this->assertFalse($this->svc->can($user, $permName, $teamId));
    }

    #[Test]
    public function base_roles_work_when_no_overrides()
    {
        $user = User::factory()->create();
        $app = SubApp::create(['slug' => 'tickets', 'name' => 'Tickets']);
        $teamId = $app->id;

        $permName = 'tickets.file.upload';
        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $role = Role::firstOrCreate(['name' => 'uploader', 'guard_name' => 'web', 'team_id' => $teamId]);
        $role->givePermissionTo($permName);
        $user->assignRole($role);

        $this->assertTrue($this->svc->can($user, $permName, $teamId));
    }
}
