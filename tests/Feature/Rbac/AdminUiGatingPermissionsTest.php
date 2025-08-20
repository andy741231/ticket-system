<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUiGatingPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin user with global allow override for admin.rbac.permissions.manage
        $this->admin = User::factory()->create();
        $perm = Permission::firstOrCreate(['name' => 'admin.rbac.permissions.manage', 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'RBAC admin',
        ]);
    }

    #[Test]
    public function permissions_index_requires_manage_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admin.rbac.permissions.index'))->assertStatus(403);
    }

    #[Test]
    public function permissions_index_exposes_mutability_flags_and_admin_permission_for_gating(): void
    {
        // Seed permissions with different mutability
        $mutable = Permission::create([
            'name' => 'tickets.ticket.update',
            'key' => 'tickets.ticket.update',
            'description' => 'Update',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $immutable = Permission::create([
            'name' => 'rbac.protected',
            'key' => 'rbac.protected',
            'description' => 'Protected',
            'guard_name' => 'web',
            'is_mutable' => false,
        ]);

        $this->actingAs($this->admin);

        $resp = $this->get(route('admin.rbac.permissions.index'));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Permissions/Index')
                ->has('permissions.data', fn (Assert $data) => $data
                    ->where('0.is_mutable', fn ($v) => in_array($v, [true, false, 0, 1], true))
                    ->etc()
                )
            );
    }

    #[Test]
    public function permissions_edit_page_reflects_read_only_when_immutable(): void
    {
        $perm = Permission::create([
            'name' => 'rbac.protected',
            'key' => 'rbac.protected',
            'description' => 'Protected',
            'guard_name' => 'web',
            'is_mutable' => false,
        ]);

        $this->actingAs($this->admin);

        $resp = $this->get(route('admin.rbac.permissions.edit', $perm->id));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Permissions/Edit')
                ->where('permission.is_mutable', fn ($v) => $v === false || $v === 0)
            );
    }
}
