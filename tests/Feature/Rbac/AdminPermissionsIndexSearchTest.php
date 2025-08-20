<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPermissionsIndexSearchTest extends TestCase
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
    public function filters_permissions_by_key_name_and_description()
    {
        // Seed a few permissions
        $p1 = Permission::create([
            'name' => 'tickets.ticket.update',
            'key' => 'tickets.ticket.update',
            'description' => 'Update tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);
        $p2 = Permission::create([
            'name' => 'tickets.ticket.create',
            'key' => 'tickets.ticket.create',
            'description' => 'Create tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);
        $p3 = Permission::create([
            'name' => 'directory.profile.view',
            'key' => 'directory.profile.view',
            'description' => 'View profiles',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        $this->actingAs($this->admin);

        // Search by key substring 'update'
        $resp = $this->get(route('admin.rbac.permissions.index', ['q' => 'update']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Permissions/Index')
                ->where('filters.q', 'update')
                ->has('permissions.data', fn (Assert $data) => $data
                    ->where('0.key', 'tickets.ticket.update')
                )
            );

        // Search by name substring 'directory'
        $resp = $this->get(route('admin.rbac.permissions.index', ['q' => 'directory']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Permissions/Index')
                ->where('filters.q', 'directory')
                ->has('permissions.data', fn (Assert $data) => $data
                    ->where('0.key', 'directory.profile.view')
                )
            );

        // Search by description substring 'Create'
        $resp = $this->get(route('admin.rbac.permissions.index', ['q' => 'Create']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Permissions/Index')
                ->where('filters.q', 'Create')
                ->has('permissions.data', fn (Assert $data) => $data
                    ->where('0.key', 'tickets.ticket.create')
                )
            );
    }
}
