<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\App as SubApp;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SyncRbacPermissions extends Command
{
    /**
     * Canonical per-app permission definitions and default role grants.
     * Kept in sync with RbacSeeder / ProductionRbacSeeder.
     * This is the single source of truth for what each role should have.
     */
    private const DEFINITIONS = [
        'hub' => [
            'permissions' => [
                'hub.user.view',
                'hub.user.create',
                'hub.user.update',
                'hub.user.delete',
                'hub.user.manage',
            ],
            'user_role_perms' => [
                'hub.user.view',
            ],
        ],
        'tickets' => [
            'permissions' => [
                'tickets.ticket.view',
                'tickets.ticket.create',
                'tickets.ticket.update',
                'tickets.ticket.delete',
                'tickets.ticket.manage',
                'tickets.file.upload',
            ],
            'user_role_perms' => [
                'tickets.ticket.view',
                'tickets.ticket.create',
            ],
        ],
        'directory' => [
            'permissions' => [
                'directory.app.access',
                'directory.profile.view',
                'directory.profile.update',
                'directory.user.lookup',
                'directory.profile.manage',
            ],
            'user_role_perms' => [
                'directory.profile.view',
            ],
        ],
        'newsletter' => [
            'permissions' => [
                'newsletter.app.access',
                'newsletter.manage',
            ],
            'user_role_perms' => [
                // no default perms for newsletter user role
            ],
        ],
        'docs' => [
            'permissions' => [
                'docs.app.access',
                'docs.document.view',
                'docs.document.create',
                'docs.document.update',
                'docs.document.delete',
                'docs.document.manage',
                'docs.flagword.manage',
            ],
            'user_role_perms' => [
                'docs.app.access',
                'docs.document.view',
                'docs.document.create',
            ],
        ],
    ];

    private const RBAC_ADMIN_PERMS = [
        'admin.rbac.roles.manage',
        'admin.rbac.permissions.manage',
        'admin.rbac.overrides.manage',
    ];

    protected $signature = 'rbac:sync-permissions {--dry-run : Show what would change without modifying}';

    protected $description = 'Re-sync all role-permission assignments from canonical definitions (idempotent, production-safe)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Clear Spatie's permission cache so lookups see fresh data
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // Ensure all apps exist
        $this->ensureApps($dryRun);

        // Ensure all permissions exist
        $this->ensurePermissions($dryRun);

        // Sync role-permission assignments
        $this->syncRolePermissions($dryRun);

        // Flush per-user permission cache for all users so effective perms are recomputed
        if (!$dryRun) {
            $this->flushUserCaches();
        }

        $this->info($dryRun ? '[DRY-RUN] No changes were made.' : 'RBAC permissions synced successfully.');
        return self::SUCCESS;
    }

    private function ensureApps(bool $dryRun): void
    {
        $apps = [
            ['slug' => 'hub', 'name' => 'Hub'],
            ['slug' => 'tickets', 'name' => 'Tickets'],
            ['slug' => 'directory', 'name' => 'Directory'],
            ['slug' => 'newsletter', 'name' => 'Newsletter'],
            ['slug' => 'docs', 'name' => 'Document Reviewer'],
        ];

        foreach ($apps as $app) {
            $exists = DB::table('apps')->where('slug', $app['slug'])->exists();
            if (!$exists) {
                if ($dryRun) {
                    $this->line("  [DRY-RUN] Would create app: {$app['slug']} ({$app['name']})");
                } else {
                    DB::table('apps')->insert([
                        'slug' => $app['slug'],
                        'name' => $app['name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->line("  Created app: {$app['slug']}");
                }
            }
        }
    }

    private function ensurePermissions(bool $dryRun): void
    {
        $allPerms = [];
        foreach (self::DEFINITIONS as $def) {
            foreach ($def['permissions'] as $p) {
                $allPerms[$p] = $p;
            }
        }
        foreach (self::RBAC_ADMIN_PERMS as $p) {
            $allPerms[$p] = $p;
        }

        $created = 0;
        foreach ($allPerms as $permName) {
            $p = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['description' => ucfirst(str_replace(['.', '_'], [' ', ' '], $permName))]
            );
            if ($p->wasRecentlyCreated) {
                $created++;
            }
            // Ensure description is filled
            if (empty($p->description)) {
                if (!$dryRun) {
                    $p->description = ucfirst(str_replace(['.', '_'], [' ', ' '], $permName));
                    $p->save();
                }
            }
        }

        if ($created > 0) {
            $this->line("  Created {$created} missing permission(s).");
        }

        // Clear cache again after creating permissions
        if (!$dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function syncRolePermissions(bool $dryRun): void
    {
        $apps = DB::table('apps')->get();
        $changes = 0;

        foreach ($apps as $app) {
            $slug = $app->slug;
            $teamId = $app->id;

            $adminRole = Role::where('slug', 'admin')
                ->where('guard_name', 'web')
                ->where('team_id', $teamId)
                ->first();

            $userRole = Role::where('slug', 'user')
                ->where('guard_name', 'web')
                ->where('team_id', $teamId)
                ->first();

            if (!$adminRole || !$userRole) {
                $this->warn("  Skipping '{$slug}': admin or user role not found (team_id={$teamId}).");
                continue;
            }

            $appPerms = self::DEFINITIONS[$slug]['permissions'] ?? [];
            $userPerms = self::DEFINITIONS[$slug]['user_role_perms'] ?? [];

            // Admin role gets all app permissions
            if (!empty($appPerms)) {
                $changes += $this->syncRole($adminRole, $appPerms, $dryRun, $slug . ' admin');
            }

            // Hub admin also gets RBAC admin permissions
            if ($slug === 'hub') {
                $changes += $this->syncRole($adminRole, self::RBAC_ADMIN_PERMS, $dryRun, $slug . ' admin (RBAC)', true);
            }

            // User role gets default user permissions
            if (!empty($userPerms)) {
                $changes += $this->syncRole($userRole, $userPerms, $dryRun, $slug . ' user');
            }
        }

        if ($changes === 0) {
            $this->line('  All role-permission assignments are already up to date.');
        } else {
            $this->line("  Applied {$changes} role-permission sync operation(s).");
        }
    }

    /**
     * Sync permissions for a role, using direct DB operations as a fallback
     * if Spatie's syncPermissions fails due to cache issues.
     */
    private function syncRole(Role $role, array $permNames, bool $dryRun, string $label, bool $isAdditive = false): int
    {
        // Get permission IDs directly from DB (bypasses Spatie cache)
        $permIds = Permission::whereIn('name', $permNames)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        if (count($permIds) !== count($permNames)) {
            $missing = array_diff($permNames, Permission::whereIn('name', $permNames)->where('guard_name', 'web')->pluck('name')->all());
            $this->warn("  [{$label}] Missing permissions: " . implode(', ', $missing));
        }

        if ($dryRun) {
            $current = DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->all();
            $wouldAdd = array_diff($permIds, $current);
            $wouldRemove = $isAdditive ? [] : array_diff($current, $permIds);
            if (!empty($wouldAdd) || !empty($wouldRemove)) {
                $this->line("  [DRY-RUN] [{$label}] Would add " . count($wouldAdd) . ", remove " . count($wouldRemove) . " permission(s).");
                return 1;
            }
            return 0;
        }

        if ($isAdditive) {
            // For additive sync (e.g., RBAC admin perms on hub admin), just insert missing rows
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->all();
            $toAdd = array_diff($permIds, $existing);
            if (!empty($toAdd)) {
                $rows = array_map(fn($pid) => ['permission_id' => $pid, 'role_id' => $role->id], array_values($toAdd));
                DB::table('role_has_permissions')->insert($rows);
                $this->line("  [{$label}] Added " . count($toAdd) . " permission(s).");
                return 1;
            }
            return 0;
        }

        // Full sync: replace all permissions for this role
        // Use direct DB to bypass any Spatie cache issues
        DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
        if (!empty($permIds)) {
            $rows = array_map(fn($pid) => ['permission_id' => $pid, 'role_id' => $role->id], $permIds);
            DB::table('role_has_permissions')->insert($rows);
        }

        $this->line("  [{$label}] Synced " . count($permIds) . " permission(s).");
        return 1;
    }

    /**
     * Flush the per-user permission cache for all users so that
     * effective permissions are recomputed on the next request.
     */
    private function flushUserCaches(): void
    {
        /** @var PermissionService $permService */
        $permService = app(PermissionService::class);
        $count = 0;
        $errors = 0;
        DB::table('users')->select('id')->orderBy('id')->chunk(200, function ($users) use ($permService, &$count, &$errors) {
            foreach ($users as $u) {
                try {
                    $permService->flushUserCache($u->id);
                    $count++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        });

        if ($count > 0) {
            $this->line("  Flushed permission cache for {$count} user(s).");
        }
        if ($errors > 0) {
            $this->warn("  Could not flush cache for {$errors} user(s) — cache store may be unwritable. Cached permissions will expire naturally within 60 seconds.");
        }
    }
}
