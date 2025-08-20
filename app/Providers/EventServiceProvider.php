<?php

namespace App\Providers;

use App\Models\UserPermissionOverride;
use App\Models\Role as AppRole;
use App\Models\Permission as AppPermission;
use App\Services\PermissionService;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Listen to Spatie Permission events (requires 'events_enabled' => true)
        Event::listen(\Spatie\Permission\Events\RoleAttached::class, function ($event) {
            $this->flushFromEvent($event);
        });
        Event::listen(\Spatie\Permission\Events\RoleDetached::class, function ($event) {
            $this->flushFromEvent($event);
        });
        Event::listen(\Spatie\Permission\Events\PermissionAttached::class, function ($event) {
            $this->flushFromEvent($event);
        });
        Event::listen(\Spatie\Permission\Events\PermissionDetached::class, function ($event) {
            $this->flushFromEvent($event);
        });

        // Invalidate cache when overrides change
        UserPermissionOverride::created(function (UserPermissionOverride $override) {
            app(PermissionService::class)->flushUserCache($override->user_id);
        });
        UserPermissionOverride::updated(function (UserPermissionOverride $override) {
            app(PermissionService::class)->flushUserCache($override->user_id);
        });
        UserPermissionOverride::deleted(function (UserPermissionOverride $override) {
            app(PermissionService::class)->flushUserCache($override->user_id);
        });

        // Invalidate cache when roles themselves change in a way that could affect users
        AppRole::updated(function (AppRole $role) {
            $this->flushUsersWithRole($role);
        });
        // Use deleting to capture user ids before the role is removed
        AppRole::deleting(function (AppRole $role) {
            $this->flushUsersWithRole($role);
        });

        // Invalidate cache when permissions change (rename/metadata) or are deleted
        AppPermission::updated(function (AppPermission $permission) {
            $this->flushUsersWithPermission($permission);
        });
        AppPermission::deleting(function (AppPermission $permission) {
            $this->flushUsersWithPermission($permission);
        });
    }

    private function flushFromEvent(object $event): void
    {
        $ps = app(PermissionService::class);

        // If the event affects a specific user/model, flush that user.
        if (is_object($event) && property_exists($event, 'model')) {
            $model = $event->model;
            if ($model instanceof \App\Models\User) {
                $ps->flushUserCache((int) $model->getKey());
                return;
            }

            // If the model is a Role, flush all users with that role
            if ($model instanceof \Spatie\Permission\Models\Role || $model instanceof \App\Models\Role) {
                try {
                    $userIds = $model->users()->pluck('id')->all();
                    foreach ($userIds as $uid) {
                        $ps->flushUserCache((int) $uid);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                return;
            }
        }

        // Some events may expose 'user'
        if (is_object($event) && property_exists($event, 'user') && $event->user && method_exists($event->user, 'getKey')) {
            $ps->flushUserCache((int) $event->user->getKey());
        }
    }

    /**
     * Flush caches for all users who have the given role.
     */
    private function flushUsersWithRole(AppRole $role): void
    {
        $ps = app(PermissionService::class);
        try {
            $userIds = $role->users()->pluck('id')->all();
            foreach ($userIds as $uid) {
                $ps->flushUserCache((int) $uid);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Flush caches for all users who have the given permission directly or via a role.
     */
    private function flushUsersWithPermission(AppPermission $permission): void
    {
        $ps = app(PermissionService::class);
        $ids = [];
        try {
            // Users with this permission directly
            if (method_exists($permission, 'users')) {
                $ids = array_merge($ids, $permission->users()->pluck('id')->all());
            }
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            // Users with roles that include this permission
            if (method_exists($permission, 'roles')) {
                $permission->loadMissing('roles');
                foreach ($permission->roles as $role) {
                    try {
                        $ids = array_merge($ids, $role->users()->pluck('id')->all());
                    } catch (\Throwable $e) {
                        // ignore per role
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $ids = array_unique(array_map('intval', $ids));
        foreach ($ids as $uid) {
            $ps->flushUserCache($uid);
        }
    }
}
