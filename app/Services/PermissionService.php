<?php

namespace App\Services;

use App\Models\UserPermissionOverride;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    public function __construct(
        private ?CacheRepository $cache = null,
    ) {
        $this->cache = $this->cache ?: Cache::store();
    }

    /**
    * Check if a user can perform a permission within an optional team (app) context.
    * - Super admin bypasses all checks.
    * - Deny overrides take precedence, then allow overrides, then role-based permissions.
    */
    public function can(\App\Models\User $user, string $permissionName, ?int $teamId = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // default to version 0 so that first flush moves to v1 and invalidates
        $ver = (int) ($this->cache->get($this->versionKey($user->id)) ?: 0);
        $key = $this->key("can", $user->id, $teamId, $permissionName, $ver);

        return (bool) $this->cache->remember($key, now()->addSeconds(60), function () use ($user, $permissionName, $teamId) {
            $perm = Permission::query()->where('name', $permissionName)->where('guard_name', 'web')->first();

            // If the permission doesn't exist, deny (and avoid hasPermissionTo throwing)
            if (!$perm) {
                if (config('app.debug')) {
                    logger()->debug('[PermissionService] permission not found', [
                        'user_id' => $user->id,
                        'permission' => $permissionName,
                        'teamId' => $teamId,
                    ]);
                }
                return false;
            }

            // Evaluate overrides (deny first, then allow). Consider team-specific and global (NULL) overrides.
            $override = UserPermissionOverride::query()
                ->active()
                ->where('user_id', $user->id)
                ->where('permission_id', $perm->id)
                ->where(function ($q) use ($teamId) {
                    $q->where('team_id', $teamId)
                      ->orWhereNull('team_id');
                })
                ->orderByRaw("CASE effect WHEN 'deny' THEN 0 WHEN 'allow' THEN 1 ELSE 2 END")
                ->first();

            if ($override) {
                $result = $override->effect === 'allow';
                if (config('app.debug')) {
                    logger()->debug('[PermissionService] override applied', [
                        'user_id' => $user->id,
                        'permission' => $permissionName,
                        'teamId' => $teamId,
                        'override_id' => $override->id,
                        'effect' => $override->effect,
                        'result' => $result,
                    ]);
                }
                return $result;
            }

            // Fallback to role-based permission within team context
            // Use a fresh user instance to avoid relation cache bleed across team contexts
            $res = $this->withTeamContext($teamId, function () use ($user, $permissionName) {
                $u = $user->fresh();
                if (method_exists($u, 'forgetCachedPermissions')) {
                    $u->forgetCachedPermissions();
                }
                return $u->hasPermissionTo($permissionName);
            });
            if (config('app.debug')) {
                logger()->debug('[PermissionService] role-based check', [
                    'user_id' => $user->id,
                    'permission' => $permissionName,
                    'teamId' => $teamId,
                    'result' => (bool) $res,
                ]);
            }
            return $res;
        });
    }

    /**
     * Get the full set of effective permission names for a user within an optional team context.
     * Applies overrides where deny removes and allow adds.
     * Returns a unique sorted array of permission names.
     */
    public function permissionsFor(\App\Models\User $user, ?int $teamId = null): array
    {
        if ($user->isSuperAdmin()) {
            // Super admins implicitly have all named permissions; to avoid scanning all permissions, return an empty array to signal 'all'.
            // Callers can treat super admin specially. For completeness, return all known permission names.
            $all = Permission::query()->pluck('name')->all();
            sort($all);
            return $all;
        }

        // default to version 0 so that first flush moves to v1 and invalidates
        $ver = (int) ($this->cache->get($this->versionKey($user->id)) ?: 0);
        $key = $this->key('perms', $user->id, $teamId, '*', $ver);

        return $this->cache->remember($key, now()->addSeconds(60), function () use ($user, $teamId) {
            // Base permissions from roles (respect team context)
            // Use a fresh user instance to avoid relation cache bleed across team contexts
            $base = $this->withTeamContext($teamId, function () use ($user) {
                $u = $user->fresh();
                if (method_exists($u, 'forgetCachedPermissions')) {
                    $u->forgetCachedPermissions();
                }
                return $u->getAllPermissions()->pluck('name')->all();
            });
            $set = array_fill_keys($base, true);

            // Apply overrides for this team and global
            $overrides = UserPermissionOverride::query()
                ->active()
                ->where('user_id', $user->id)
                ->where(function ($q) use ($teamId) {
                    $q->where('team_id', $teamId)
                      ->orWhereNull('team_id');
                })
                ->with('permission:id,name')
                ->get();

            foreach ($overrides as $ovr) {
                $name = $ovr->permission?->name;
                if (!$name) continue;
                if ($ovr->effect === 'deny') {
                    unset($set[$name]);
                } elseif ($ovr->effect === 'allow') {
                    $set[$name] = true;
                }
            }

            $names = array_keys($set);
            sort($names);
            return $names;
        });
    }

    /**
     * Invalidate cached permission results for a user.
     */
    public function flushUserCache(int $userId): void
    {
        $key = $this->versionKey($userId);
        try {
            // Some cache stores (e.g., file) on certain PHP builds can throw when writing
            // a "forever" TTL during increment due to epoch integer limits.
            $this->cache->increment($key);
        } catch (\Throwable $e) {
            // Fallback: manually bump the version and persist with a safe TTL
            // Keep TTL modest (e.g., 30 days) to avoid 32-bit epoch overflow
            $current = (int) ($this->cache->get($key) ?: 0);
            $next = $current + 1;
            // 30 days is sufficient to keep versioning stable between regular cache refreshes
            $this->cache->put($key, $next, now()->addDays(30));
        }
    }

    private function versionKey(int $userId): string
    {
        return "perm.ver:" . $userId;
    }

    private function key(string $prefix, int $userId, ?int $teamId, string $perm, int $ver): string
    {
        return implode(':', [
            'perm', $prefix, 'v'.$ver, $userId, ($teamId ?? 'global'), $perm,
        ]);
    }

    /**
     * Run a callback with the Spatie team context set to the provided team id, and restore previous value.
     */
    private function withTeamContext(?int $teamId, Closure $callback)
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = App::make(PermissionRegistrar::class);

        // Try to get current team id if the method exists; otherwise assume null
        $prev = null;
        if (method_exists($registrar, 'getPermissionsTeamId')) {
            try {
                $prev = $registrar->getPermissionsTeamId();
            } catch (\Throwable $e) {
                $prev = null;
            }
        }

        $registrar->setPermissionsTeamId($teamId);
        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($prev);
        }
    }
}
