<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUiGatingOverridesTest extends TestCase
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
        $perm = Permission::firstOrCreate(['name' => 'admin.rbac.overrides.manage', 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'RBAC admin',
        ]);
    }

    #[Test]
    public function overrides_index_requires_manage_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admin.rbac.overrides.index'))->assertStatus(403);
    }

    #[Test]
    public function overrides_index_exposes_expected_props_for_admin(): void
    {
        $this->actingAs($this->admin);

        $resp = $this->get(route('admin.rbac.overrides.index'));
        $resp->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Overrides/Index')
                ->has('overrides.data')
                ->has('filters.q')
            );
    }

    #[Test]
    public function overrides_create_requires_manage_permission_and_admin_sees_lists(): void
    {
        // Non-admin blocked
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('admin.rbac.overrides.create'))->assertStatus(403);

        // Admin allowed, sees lists for users/permissions/apps
        $this->actingAs($this->admin);
        $this->get(route('admin.rbac.overrides.create'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Overrides/Create')
                ->has('users')
                ->has('permissions')
                ->has('apps')
            );
    }

    #[Test]
    public function overrides_edit_requires_manage_permission_and_admin_sees_payload(): void
    {
        $tickets = SubApp::where('slug', 'tickets')->first();
        $targetUser = User::factory()->create(['name' => 'Alice']);
        $permUpdate = Permission::firstOrCreate([
            'name' => 'tickets.ticket.update',
            'key' => 'tickets.ticket.update',
            'guard_name' => 'web',
        ], ['description' => 'Update tickets', 'is_mutable' => true]);

        $ovr = UserPermissionOverride::create([
            'user_id' => $targetUser->id,
            'permission_id' => $permUpdate->id,
            'team_id' => $tickets->id,
            'effect' => 'allow',
            'reason' => 'Temp allow',
        ]);

        // Non-admin blocked
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('admin.rbac.overrides.edit', $ovr->id))->assertStatus(403);

        // Admin allowed
        $this->actingAs($this->admin);
        $this->get(route('admin.rbac.overrides.edit', $ovr->id))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Overrides/Edit')
                ->where('override.id', $ovr->id)
                ->has('users')
                ->has('permissions')
                ->has('apps')
            );
    }
}
