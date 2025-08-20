<?php

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOverridesIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed apps used in tests
        SubApp::firstOrCreate(['slug' => 'hub'], ['name' => 'Hub']);
        SubApp::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);

        // Admin user with global allow override for admin.rbac.overrides.manage
        $this->admin = User::factory()->create();
        $permManage = Permission::firstOrCreate(['name' => 'admin.rbac.overrides.manage', 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $permManage->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'RBAC admin',
        ]);
    }

    #[Test]
    public function filters_overrides_by_user_permission_and_app()
    {
        $this->actingAs($this->admin);

        $ticketsApp = SubApp::where('slug', 'tickets')->first();

        $alice = User::factory()->create(['name' => 'Alice Example']);
        $bob = User::factory()->create(['name' => 'Bob Person']);

        $permUpdate = Permission::firstOrCreate([
            'name' => 'tickets.ticket.update',
            'key' => 'tickets.ticket.update',
            'guard_name' => 'web',
        ], ['description' => 'Update tickets', 'is_mutable' => true]);

        $permCreate = Permission::firstOrCreate([
            'name' => 'tickets.ticket.create',
            'key' => 'tickets.ticket.create',
            'guard_name' => 'web',
        ], ['description' => 'Create tickets', 'is_mutable' => true]);

        // Seed some overrides (create earlier ones first)
        UserPermissionOverride::create([
            'user_id' => $alice->id,
            'permission_id' => $permCreate->id,
            'team_id' => $ticketsApp->id,
            'effect' => 'allow',
            'reason' => 'Initial allow',
        ]);

        // The target override for name search (created last so it appears first)
        $ovrAlice = UserPermissionOverride::create([
            'user_id' => $alice->id,
            'permission_id' => $permUpdate->id,
            'team_id' => $ticketsApp->id,
            'effect' => 'deny',
            'reason' => 'Urgent fix deny',
        ]);

        // The target override for permission key search (created last)
        $ovrBob = UserPermissionOverride::create([
            'user_id' => $bob->id,
            'permission_id' => $permUpdate->id,
            'team_id' => $ticketsApp->id,
            'effect' => 'allow',
            'reason' => 'Second allow',
        ]);

        // Search by user name 'Alice'
        $resp = $this->get(route('admin.rbac.overrides.index', ['q' => 'Alice']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Overrides/Index')
                ->where('filters.q', 'Alice')
                ->has('overrides.data.0')
                ->where('overrides.data.0.user.name', fn ($name) => str_contains($name, 'Alice'))
            );

        // Search by permission key substring 'ticket.update'
        $resp = $this->get(route('admin.rbac.overrides.index', ['q' => 'ticket.update'])) ;
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Overrides/Index')
                ->where('filters.q', 'ticket.update')
                ->has('overrides.data.0')
                ->where('overrides.data.0.permission.key', fn ($key) => str_contains($key, 'ticket.update'))
            );

        // Search by app slug 'tickets'
        $resp = $this->get(route('admin.rbac.overrides.index', ['q' => 'tickets'])) ;
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Overrides/Index')
                ->where('filters.q', 'tickets')
                ->has('overrides.data.0')
                ->where('overrides.data.0.app.slug', 'tickets')
            );
    }
}
