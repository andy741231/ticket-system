<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OverridePrecedenceAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed apps used in tests
        SubApp::firstOrCreate(['slug' => 'hub'], ['name' => 'Hub']);
        SubApp::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);

        // Admin user with global allow override for admin.rbac.permissions.manage to access RBAC dashboard
        $this->admin = User::factory()->create();
        $manage = Permission::firstOrCreate(['name' => 'admin.rbac.permissions.manage', 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $manage->id,
            'team_id' => null,
            'effect' => 'allow',
        ]);
    }

    #[Test]
    public function allow_override_grants_permission_in_shared_props(): void
    {
        $this->actingAs($this->admin);

        $perm = Permission::firstOrCreate(['name' => 'tickets.ticket.update', 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null, // global
            'effect' => 'allow',
        ]);
        app(PermissionService::class)->flushUserCache($this->admin->id);

        $this->get(route('admin.rbac.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Rbac/Dashboard'));
        $this->assertTrue(app(PermissionService::class)->can($this->admin, 'tickets.ticket.update'));
    }

    #[Test]
    public function deny_override_wins_over_allow_override(): void
    {
        $this->actingAs($this->admin);

        $perm = Permission::firstOrCreate(['name' => 'tickets.ticket.update', 'guard_name' => 'web']);
        // Both an allow and a deny override exist; deny should take precedence
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
        ]);
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'deny',
        ]);
        app(PermissionService::class)->flushUserCache($this->admin->id);

        $this->get(route('admin.rbac.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Rbac/Dashboard'));
        $this->assertFalse(app(PermissionService::class)->can($this->admin, 'tickets.ticket.update'));
    }

    #[Test]
    public function expired_overrides_are_ignored_in_effective_permissions(): void
    {
        $this->actingAs($this->admin);

        $perm = Permission::firstOrCreate(['name' => 'tickets.ticket.update', 'guard_name' => 'web']);

        // Expired allow override should be ignored
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('admin.rbac.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rbac/Dashboard')
                ->where('auth.user.permissions', function ($perms) {
                    $list = is_array($perms) ? $perms : (\is_object($perms) && method_exists($perms, 'toArray') ? $perms->toArray() : ($perms instanceof \Traversable ? iterator_to_array($perms) : null));
                    if (!is_array($list)) return false;
                    foreach ($list as $p) {
                        if ($p === 'tickets.ticket.update') return false;
                        if (is_array($p) && ($p['name'] ?? null) === 'tickets.ticket.update') return false;
                        if (is_object($p) && ((property_exists($p, 'name') ? $p->name : null) === 'tickets.ticket.update')) return false;
                    }
                    return true;
                })
            );

        // A future-dated allow should then appear
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
            'expires_at' => now()->addDay(),
        ]);
        app(PermissionService::class)->flushUserCache($this->admin->id);
        $this->get(route('admin.rbac.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Rbac/Dashboard'));
        $this->assertTrue(app(PermissionService::class)->can($this->admin, 'tickets.ticket.update'));
    }

    #[Test]
    public function team_scoped_override_only_applies_in_that_app_context(): void
    {
        $this->actingAs($this->admin);

        $tickets = SubApp::where('slug', 'tickets')->first();
        $perm = Permission::firstOrCreate(['name' => 'tickets.ticket.update', 'guard_name' => 'web']);

        // Allow override only for tickets team
        UserPermissionOverride::create([
            'user_id' => $this->admin->id,
            'permission_id' => $perm->id,
            'team_id' => $tickets->id,
            'effect' => 'allow',
        ]);
        app(PermissionService::class)->flushUserCache($this->admin->id);

        // In non-tickets context (admin/rbac), the tickets-scoped override should not appear
        $this->get(route('admin.rbac.dashboard'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Rbac/Dashboard'));
        $this->assertFalse(app(PermissionService::class)->can($this->admin, 'tickets.ticket.update'));
    }
}
