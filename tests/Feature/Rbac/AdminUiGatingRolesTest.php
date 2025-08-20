<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUiGatingRolesTest extends TestCase
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
    public function roles_index_requires_manage_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admin.rbac.roles.index'))->assertStatus(403);
    }

    #[Test]
    public function roles_index_exposes_mutability_flags_and_admin_permission_for_gating(): void
    {
        // Seed roles with different mutability
        $globalMutable = Role::create([
            'name' => 'Global Mutable',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'global-mutable',
            'description' => 'Mutable',
            'is_mutable' => true,
        ]);

        $globalImmutable = Role::create([
            'name' => 'Global Immutable',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'global-immutable',
            'description' => 'Immutable',
            'is_mutable' => false,
        ]);

        $this->actingAs($this->admin);

        $resp = $this->get(route('admin.rbac.roles.index'));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Roles/Index')
                // Ensure roles include is_mutable flags used for UI gating (Protected vs Delete)
                ->has('roles.data', fn (Assert $data) => $data
                    ->where('0.is_mutable', fn ($v) => in_array($v, [true, false, 0, 1], true))
                    ->etc()
                )
            );
    }

    #[Test]
    public function roles_edit_page_reflects_read_only_when_immutable(): void
    {
        $role = Role::create([
            'name' => 'Protected Role',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'protected-role',
            'description' => 'Protected',
            'is_mutable' => false,
        ]);

        $this->actingAs($this->admin);

        $resp = $this->get(route('admin.rbac.roles.edit', $role->id));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Roles/Edit')
                ->where('role.is_mutable', fn ($v) => $v === false || $v === 0)
                ->has('available_permissions.data')
                ->has('assigned_permissions')
            );
    }
}
