<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\UserPermissionOverride;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImmutabilityDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function grantRbacAdmin(User $user): void
    {
        // Grant granular RBAC admin permissions via global overrides (team_id null)
        $rolesManage = Permission::firstOrCreate(['name' => 'admin.rbac.roles.manage', 'guard_name' => 'web']);
        $permsManage = Permission::firstOrCreate(['name' => 'admin.rbac.permissions.manage', 'guard_name' => 'web']);

        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $rolesManage->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'RBAC admin for tests',
        ]);

        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $permsManage->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'RBAC admin for tests',
        ]);
    }

    #[Test]
    public function cannot_delete_immutable_role()
    {
        $user = User::factory()->create();
        $this->grantRbacAdmin($user);
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'immutable-role',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'immutable-role',
            'description' => 'Protected',
            'is_mutable' => false,
        ]);

        $response = $this->delete(route('admin.rbac.roles.destroy', $role->id));
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'This role is protected and cannot be deleted.');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    #[Test]
    public function can_delete_mutable_role()
    {
        $user = User::factory()->create();
        $this->grantRbacAdmin($user);
        $this->actingAs($user);

        $role = Role::create([
            'name' => 'temp-role',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'temp-role',
            'description' => 'Temp',
            'is_mutable' => true,
        ]);

        $response = $this->delete(route('admin.rbac.roles.destroy', $role->id));
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Role deleted');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    #[Test]
    public function cannot_delete_immutable_permission()
    {
        $user = User::factory()->create();
        $this->grantRbacAdmin($user);
        $this->actingAs($user);

        $perm = Permission::create([
            'name' => 'rbac.protected',
            'guard_name' => 'web',
            'key' => 'rbac.protected',
            'description' => 'Protected',
            'is_mutable' => false,
        ]);

        $response = $this->delete(route('admin.rbac.permissions.destroy', $perm->id));
        $response->assertStatus(302);
        $response->assertSessionHas('error', 'This permission is protected and cannot be deleted.');
        $this->assertDatabaseHas('permissions', ['id' => $perm->id]);
    }

    #[Test]
    public function can_delete_mutable_permission()
    {
        $user = User::factory()->create();
        $this->grantRbacAdmin($user);
        $this->actingAs($user);

        $perm = Permission::create([
            'name' => 'rbac.temp',
            'guard_name' => 'web',
            'key' => 'rbac.temp',
            'description' => 'Temp',
            'is_mutable' => true,
        ]);

        $response = $this->delete(route('admin.rbac.permissions.destroy', $perm->id));
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Permission deleted');
        $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
    }
}
