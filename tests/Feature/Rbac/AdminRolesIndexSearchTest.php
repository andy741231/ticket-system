<?php

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

class AdminRolesIndexSearchTest extends TestCase
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
    public function filters_roles_by_name_and_app_slug()
    {
        $hubApp = SubApp::where('slug', 'hub')->first();
        $ticketsApp = SubApp::where('slug', 'tickets')->first();

        $global = Role::create([
            'name' => 'Global Admin',
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => 'global-admin',
            'description' => 'Global admin',
            'is_mutable' => true,
        ]);

        $hubRole = Role::create([
            'name' => 'Hub Moderator',
            'guard_name' => 'web',
            'team_id' => $hubApp->id,
            'slug' => 'hub-moderator',
            'description' => 'Hub mod',
            'is_mutable' => true,
        ]);

        $ticketsRole = Role::create([
            'name' => 'Tickets Agent',
            'guard_name' => 'web',
            'team_id' => $ticketsApp->id,
            'slug' => 'tickets-agent',
            'description' => 'Tickets agent',
            'is_mutable' => true,
        ]);

        $this->actingAs($this->admin);

        // Search by name
        $resp = $this->get(route('admin.rbac.roles.index', ['q' => 'Global']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Roles/Index')
                ->where('filters.q', 'Global')
                ->has('roles.data', fn (Assert $data) => $data
                    ->where('0.name', 'Global Admin')
                )
            );

        // Search by app slug 'tickets'
        $resp = $this->get(route('admin.rbac.roles.index', ['q' => 'tickets']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Roles/Index')
                ->where('filters.q', 'tickets')
                ->has('roles.data', fn (Assert $data) => $data
                    ->where('0.slug', 'tickets-agent')
                )
            );

        // Search by app slug 'hub' returns the hub role, not tickets
        $resp = $this->get(route('admin.rbac.roles.index', ['q' => 'hub']));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Roles/Index')
                ->where('filters.q', 'hub')
                ->has('roles.data', fn (Assert $data) => $data
                    ->where('0.slug', 'hub-moderator')
                )
            );
    }
}
