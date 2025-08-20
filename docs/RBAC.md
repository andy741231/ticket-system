# RBAC Guide (Laravel + Vue 3 + Inertia)

This document summarizes the Role-Based Access Control (RBAC) architecture, implementation, and usage patterns in this project.

## Overview
- Backend: Laravel, Spatie Permission with Teams (app scoping), policies/middleware, PermissionService.
- Frontend: Vue 3 + Inertia, `useAuthz` composable, explicit Inertia booleans for common checks, gated UI with read-only states.
- Scoping: All permissions are evaluated under a current app (team) context.

## Permission Model
- Precedence (highest → lowest):
  1) Super Admin
  2) Deny override
  3) Allow override
  4) Role grant
  5) Default deny
- App (team) scoping: permissions and role assignments are evaluated under the current team (app) ID.

### Core Permission Keys
- Users app: `users.user.view|create|update|delete|manage`
- Tickets app: `tickets.ticket.view|create|update|delete|manage`, `tickets.file.upload`
- Directory app: `directory.profile.view|update`, `directory.user.lookup`
- RBAC admin: `admin.rbac.roles.manage`, `admin.rbac.permissions.manage`, `admin.rbac.overrides.manage`

## Backend Integration
- Inertia shared props (`app/Http/Middleware/HandleInertiaRequests.php`):
  - `auth.user.permissions` (effective for current app), `auth.user.isSuperAdmin`.
  - Explicit booleans (team-scoped):
    - `canManageUsers` → `users.user.manage`
    - `canManageRoles` → `admin.rbac.roles.manage`
    - `canManagePermissions` → `admin.rbac.permissions.manage`
    - `canManageOverrides` → `admin.rbac.overrides.manage`
- Route middleware (`routes/web.php`):
  - RBAC areas enforce granular permissions (no legacy fallback):
    - Roles: `admin.rbac.roles.manage`
    - Permissions: `admin.rbac.permissions.manage`
    - Overrides: `admin.rbac.overrides.manage`
    - RBAC Dashboard: any of the above (roles or permissions or overrides)

## Implementation Status
- RBAC Admin (routes + UI) is enforced exclusively via granular permissions:
  - Roles: `admin.rbac.roles.manage`
  - Permissions: `admin.rbac.permissions.manage`
  - Overrides: `admin.rbac.overrides.manage`
- User Admin navigation/CTAs are aligned with middleware and allow either `users.user.view` or `users.user.manage`.
- Legacy fallback `users.user.manage` is not used anywhere to gate RBAC Admin areas.
- `useAuthz` explicit boolean map includes granular RBAC keys and is consumed by pages and layouts.
- Documentation reflects granular-only model; tests assert granular permissions.

## Frontend Authorization
File: `resources/js/Extensions/useAuthz.js`

Exports:
- `useCan(permissionKey, appSlug?)` → `computed<boolean>`
- `useHasAny(permissionKeys[], appSlug?)` → `computed<boolean>`
- `useHasAll(permissionKeys[], appSlug?)` → `computed<boolean>`
- `useAppContext()` → `{ currentApp, currentAppSlug, currentTeamId }`

Explicit boolean mapping (preferentially used over string arrays):
- `users.user.manage` → `auth.user.canManageUsers`
- `admin.rbac.roles.manage` → `auth.user.canManageRoles`
- `admin.rbac.permissions.manage` → `auth.user.canManagePermissions`
- `admin.rbac.overrides.manage` → `auth.user.canManageOverrides`

Examples:
```vue
<script setup>
import { useHasAny } from '@/Extensions/useAuthz';
const canManageRoles = useHasAny(['admin.rbac.roles.manage']);
</script>

<template>
  <Link v-if="canManageRoles" :href="route('admin.rbac.roles.index')">Manage Roles</Link>
</template>
```

Disabled/read-only pattern:
```vue
<script setup>
import { useHasAny } from '@/Extensions/useAuthz';
const canManageOverrides = useHasAny(['admin.rbac.overrides.manage']);
</script>

<template>
  <div v-if="!canManageOverrides" class="mb-3 rounded border border-amber-300 bg-amber-50 p-3 text-amber-800">
    You do not have permission to modify overrides. Fields are read-only.
  </div>
  <input :disabled="!canManageOverrides" />
  <button :disabled="!canManageOverrides">Save</button>
</template>
```

## Seeders
- `database/seeders/RbacSeeder.php`
  - Seeds app-scoped roles (admin/user) per app and assigns base app permissions.
  - Seeds RBAC admin perms: `admin.rbac.roles.manage`, `admin.rbac.permissions.manage`, `admin.rbac.overrides.manage` and assigns them to the Users app’s Admin role.
- `database/seeders/AdminUserSeeder.php`: admin@example.com / 123
- `database/seeders/SuperAdminSeeder.php`: creates global `super_admin` role and user `mchan3` (password `123`), assigned under each app context.

## Manual Verification
- Start app (example): `php artisan serve --port=8001`
- Login as admin@example.com / 123
- RBAC Dashboard (`/admin/rbac`): links visible according to permissions.
- Overrides Create/Edit (`/admin/rbac/overrides/*`): banner + disabled fields when lacking `admin.rbac.overrides.manage`.
- Users Create/Edit: roles section read-only when lacking `admin.rbac.roles.manage`.
- Users Show: “Create Override” hidden when lacking `admin.rbac.overrides.manage`.

## Testing
- Run all tests: `php artisan test`
- Current state: granular middleware enforced with no legacy fallback; tests updated to assert granular permissions explicitly. All tests passing (74).

## Deployment Checklist
1) Caches
   - `php artisan cache:clear`
   - `php artisan config:cache`
   - `php artisan view:cache`
   - Note: Route caching is not recommended while route closures remain (e.g., some dashboard routes). Replace closures with controllers before using `php artisan route:cache`.
2) Frontend build
   - `npm ci`
   - `npm run build`
3) Seed/verify permissions (only if target env needs it)
   - `php artisan db:seed --class=RbacSeeder`
   - Ensure roles/permissions exist and map to expected app contexts.

## Adding New Permissions
1) Define a permission key (scoped by app when applicable).
2) Seed it in `RbacSeeder` and assign to appropriate roles.
3) If needed for frequent frontend checks, add an explicit boolean in `HandleInertiaRequests` and map it in `EXPLICIT_BOOL_MAP` in `useAuthz`.
4) Gate routes via `perm:` middleware and gate UI via `useHasAny/useCan`.
5) Add policy checks for sensitive server-side operations.

## Security Notes
- Do not rely solely on frontend gating. Always enforce permission checks in routes/controllers/policies.
- Consider caching and invalidation if you extend the PermissionService.

## Future Work
- Remove legacy route fallbacks after tests and roles are updated to the granular RBAC admin permissions.
- Add E2E tests covering read-only UI states.
