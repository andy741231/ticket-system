<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRolePermissionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed apps used in tests
        SubApp::firstOrCreate(['slug' => 'hub'], ['name' => 'Hub']);
        SubApp::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);

        // Admin user with global allow override for admin.rbac.roles.manage
        $this->admin = User::factory()->create();
        $perm = Permission::firstOrCreate(['name' => 'admin.rbac.roles.manage', 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'RBAC admin',
        ]);
    }

    #[Test]
    public function admin_can_attach_permission_to_role(): void
    {
        $role = Role::create([
            'name' => 'Hub Moderator',
            'guard_name' => 'web',
            'team_id' => SubApp::where('slug', 'hub')->value('id'),
            'slug' => 'hub-moderator',
            'description' => 'Hub mod',
            'is_mutable' => true,
        ]);

        $perm = Permission::create([
            'name' => 'hub.user.view',
            'key' => 'hub.user.view',
            'description' => 'View hub users',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $this->actingAs($this->admin);

        $resp = $this->post(route('admin.rbac.roles.permissions.attach', [$role->id, $perm->id]));
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->assertTrue($role->fresh()->hasPermissionTo('hub.user.view'));
    }

    #[Test]
    public function admin_can_detach_permission_from_role(): void
    {
        $role = Role::create([
            'name' => 'Tickets Agent',
            'guard_name' => 'web',
            'team_id' => SubApp::where('slug', 'tickets')->value('id'),
            'slug' => 'tickets-agent',
            'description' => 'Agent',
            'is_mutable' => true,
        ]);

        $perm = Permission::create([
            'name' => 'tickets.ticket.update',
            'key' => 'tickets.ticket.update',
            'description' => 'Update tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $role->givePermissionTo($perm);
        $this->assertTrue($role->hasPermissionTo('tickets.ticket.update'));

        $this->actingAs($this->admin);

        $resp = $this->delete(route('admin.rbac.roles.permissions.detach', [$role->id, $perm->id]));
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->assertFalse($role->fresh()->hasPermissionTo('tickets.ticket.update'));
    }

    #[Test]
    public function forbidden_without_manage_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'Global Role',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'global-role',
            'description' => 'Global',
            'is_mutable' => true,
        ]);

        $perm = Permission::create([
            'name' => 'hub.user.view',
            'key' => 'hub.user.view',
            'description' => 'View hub users',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $this->post(route('admin.rbac.roles.permissions.attach', [$role->id, $perm->id]))->assertStatus(403);
        $this->delete(route('admin.rbac.roles.permissions.detach', [$role->id, $perm->id]))->assertStatus(403);
    }

    #[Test]
    public function cannot_modify_immutable_role(): void
    {
        $role = Role::create([
            'name' => 'Protected Role',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'protected-role',
            'description' => 'Protected',
            'is_mutable' => false,
        ]);

        $perm = Permission::create([
            'name' => 'hub.user.view',
            'key' => 'hub.user.view',
            'description' => 'View hub users',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $this->actingAs($this->admin);

        $attach = $this->post(route('admin.rbac.roles.permissions.attach', [$role->id, $perm->id]));
        $attach->assertRedirect();
        $attach->assertSessionHas('error');
        $this->assertFalse($role->fresh()->hasPermissionTo('hub.user.view'));

        // Pre-grant and then try detaching
        $role->givePermissionTo($perm);
        $this->assertTrue($role->hasPermissionTo('hub.user.view'));

        $detach = $this->delete(route('admin.rbac.roles.permissions.detach', [$role->id, $perm->id]));
        $detach->assertRedirect();
        $detach->assertSessionHas('error');
        $this->assertTrue($role->fresh()->hasPermissionTo('hub.user.view'));
    }
}
