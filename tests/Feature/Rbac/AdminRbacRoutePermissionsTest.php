<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRbacRoutePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function grantOverride(User $user, string $permName, ?int $teamId = null): void
    {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => $teamId,
            'effect' => 'allow',
            'reason' => 'Test override',
        ]);
    }

    #[Test]
    public function rbac_admin_routes_forbidden_without_permissions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admin.rbac.roles.index'))->assertStatus(403);
        $this->get(route('admin.rbac.permissions.index'))->assertStatus(403);
        $this->get(route('admin.rbac.overrides.index'))->assertStatus(403);
    }

    #[Test]
    public function roles_routes_require_roles_manage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Without permission -> 403
        $this->get(route('admin.rbac.roles.index'))->assertStatus(403);

        // With granular permission -> 200
        $this->grantOverride($user, 'admin.rbac.roles.manage');
        $this->get(route('admin.rbac.roles.index'))->assertStatus(200);

        // Other areas still 403
        $this->get(route('admin.rbac.permissions.index'))->assertStatus(403);
        $this->get(route('admin.rbac.overrides.index'))->assertStatus(403);
    }

    #[Test]
    public function permissions_routes_require_permissions_manage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Without permission -> 403
        $this->get(route('admin.rbac.permissions.index'))->assertStatus(403);

        // With granular permission -> 200
        $this->grantOverride($user, 'admin.rbac.permissions.manage');
        $this->get(route('admin.rbac.permissions.index'))->assertStatus(200);

        // Other areas still 403
        $this->get(route('admin.rbac.roles.index'))->assertStatus(403);
        $this->get(route('admin.rbac.overrides.index'))->assertStatus(403);
    }

    #[Test]
    public function overrides_routes_require_overrides_manage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Without permission -> 403
        $this->get(route('admin.rbac.overrides.index'))->assertStatus(403);

        // With granular permission -> 200
        $this->grantOverride($user, 'admin.rbac.overrides.manage');
        $this->get(route('admin.rbac.overrides.index'))->assertStatus(200);

        // Other areas still 403
        $this->get(route('admin.rbac.roles.index'))->assertStatus(403);
        $this->get(route('admin.rbac.permissions.index'))->assertStatus(403);
    }

    #[Test]
    public function legacy_users_manage_fallback_no_longer_allows_rbac_routes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->grantOverride($user, 'users.user.manage');

        $this->get(route('admin.rbac.roles.index'))->assertStatus(403);
        $this->get(route('admin.rbac.permissions.index'))->assertStatus(403);
        $this->get(route('admin.rbac.overrides.index'))->assertStatus(403);
    }
}
