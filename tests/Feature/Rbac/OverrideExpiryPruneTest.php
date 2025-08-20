<?php

namespace Tests\Feature\Rbac;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OverrideExpiryPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SubApp::firstOrCreate(['slug' => 'tickets'], ['name' => 'Tickets']);
    }

    #[Test]
    public function expired_overrides_are_not_applied_active_overrides_are_applied()
    {
        $tickets = SubApp::where('slug', 'tickets')->first();
        $user = User::factory()->create();

        $perm = Permission::create([
            'name' => 'tickets.ticket.view',
            'key' => 'tickets.ticket.view',
            'description' => 'View tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        // Expired allow override (global) should NOT grant access
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => null,
            'effect' => 'allow',
            'reason' => 'temp',
            'expires_at' => now()->subMinute(),
        ]);

        $svc = app(PermissionService::class);
        $this->assertFalse($svc->can($user, 'tickets.ticket.view', $tickets->id));

        // Active allow override should grant access
        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => $tickets->id,
            'effect' => 'allow',
            'reason' => 'active',
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($svc->can($user, 'tickets.ticket.view', $tickets->id));
    }

    #[Test]
    public function prune_command_deletes_expired_overrides_and_flushes_cache()
    {
        $tickets = SubApp::where('slug', 'tickets')->first();
        $user = User::factory()->create();

        $perm = Permission::create([
            'name' => 'tickets.ticket.edit',
            'key' => 'tickets.ticket.edit',
            'description' => 'Edit tickets',
            'guard_name' => 'web',
            'is_mutable' => true,
        ]);

        // Create an active allow override (future expiry) and prime cache to true
        $ovr = UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $perm->id,
            'team_id' => $tickets->id,
            'effect' => 'allow',
            'reason' => 'temp',
            'expires_at' => now()->addMinutes(10),
        ]);

        $svc = app(PermissionService::class);
        $this->assertTrue($svc->can($user, 'tickets.ticket.edit', $tickets->id));

        // Mark it expired without firing model events to avoid pre-flush
        DB::table('user_permission_overrides')->where('id', $ovr->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        // Dry-run should not delete
        $before = UserPermissionOverride::count();
        $this->artisan('rbac:prune-overrides', ['--dry-run' => true])->assertExitCode(0);
        $this->assertEquals($before, UserPermissionOverride::count());

        // Cached result still true because cache not flushed yet
        $this->assertTrue($svc->can($user, 'tickets.ticket.edit', $tickets->id));

        // Now actually prune; this should delete and flush cache via model deleted event
        $this->artisan('rbac:prune-overrides')->assertExitCode(0);
        $this->assertDatabaseMissing('user_permission_overrides', ['id' => $ovr->id]);

        // Cache should have been flushed; recomputed result should now be false
        $this->assertFalse($svc->can($user, 'tickets.ticket.edit', $tickets->id));
    }
}
