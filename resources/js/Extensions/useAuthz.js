import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * useAuthz composable
 * - useCan(permissionKey: string, appSlug?: string)
 * - useAppContext()
 *
 * Notes:
 * - Backend shares effective permissions for the CURRENT app context only
 *   via HandleInertiaRequests ('auth.user.permissions').
 * - Super admin bypasses checks.
 * - If appSlug is provided and differs from current app slug, this returns false
 *   (client cannot evaluate cross-app without switching context on the server).
 */
export function useCan(permissionKey, appSlug) {
  const page = usePage();

  return computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;

    if (user.isSuperAdmin) return true;

    const currentSlug = page.props.appContext?.currentApp?.slug ?? null;
    if (appSlug && appSlug !== currentSlug) {
      return false;
    }

    // Prefer explicit booleans when provided by backend
    const explicit = explicitBooleanFor(user, permissionKey);
    if (explicit !== undefined) return explicit;

    // Fallback to effective permissions list (array of strings or objects)
    const permSet = normalizePermissionsToSet(user.permissions);
    return permSet.has(permissionKey);
  });
}

export function useHasAny(permissions = [], appSlug) {
  const page = usePage();
  return computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    if (user.isSuperAdmin) return true;

    const currentSlug = page.props.appContext?.currentApp?.slug ?? null;
    if (appSlug && appSlug !== currentSlug) return false;

    // If any explicit boolean is true, grant
    for (const key of permissions) {
      const explicit = explicitBooleanFor(user, key);
      if (explicit === true) return true;
    }

    // Otherwise, fallback to effective permissions
    const permSet = normalizePermissionsToSet(user.permissions);
    return permissions.some(p => permSet.has(p));
  });
}

export function useHasAll(permissions = [], appSlug) {
  const page = usePage();
  return computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    if (user.isSuperAdmin) return true;

    const currentSlug = page.props.appContext?.currentApp?.slug ?? null;
    if (appSlug && appSlug !== currentSlug) return false;

    // If any explicit boolean is false, deny
    for (const key of permissions) {
      const explicit = explicitBooleanFor(user, key);
      if (explicit === false) return false;
    }

    // Remove keys that are explicitly true and check remaining via fallback set
    const remaining = permissions.filter(p => explicitBooleanFor(user, p) !== true);
    const permSet = normalizePermissionsToSet(user.permissions);
    return remaining.every(p => permSet.has(p));
  });
}

export function useAppContext() {
  const page = usePage();
  const currentApp = computed(() => page.props.appContext?.currentApp ?? null);
  const currentAppSlug = computed(() => currentApp.value?.slug ?? null);
  const currentTeamId = computed(() => page.props.appContext?.teamId ?? null);

  return {
    currentApp,
    currentAppSlug,
    currentTeamId,
  };
}

// --- Internals ---

// Map permission keys to explicit boolean props provided by backend
// Note: keep some legacy aliases for backward compatibility during migration.
const EXPLICIT_BOOL_MAP = {
  // Hub (formerly Users) app
  'hub.user.manage': 'canManageUsers',
  // Legacy alias
  'users.user.manage': 'canManageUsers',
  // Cross-app Hub access (computed on backend against Hub app team id)
  'hub.app.access': 'canAccessUsersApp',
  // Legacy alias
  'users.app.access': 'canAccessUsersApp',
  // Cross-app access booleans for Tickets and Directory
  'tickets.app.access': 'canAccessTicketsApp',
  'directory.app.access': 'canAccessDirectoryApp',
  // Cross-app access boolean for Newsletter
  'newsletter.app.access': 'canAccessNewsletterApp',
  'newsletter.manage': 'canManageNewsletterApp',
  'tickets.ticket.update': 'canUpdateTickets',
  // RBAC admin explicit booleans (synthetic keys for clearer UI usage)
  'admin.rbac.roles.manage': 'canManageRoles',
  'admin.rbac.permissions.manage': 'canManagePermissions',
  'admin.rbac.overrides.manage': 'canManageOverrides',
};

function explicitBooleanFor(user, permissionKey) {
  const prop = EXPLICIT_BOOL_MAP[permissionKey];
  if (!prop) return undefined; // not explicitly provided
  const val = user?.[prop];
  return typeof val === 'boolean' ? val : Boolean(val);
}

function normalizePermissionsToSet(perms) {
  const set = new Set();
  if (!perms) return set;
  // perms can be: array of strings, array of objects { name }, or a collection-like
  const list = Array.isArray(perms)
    ? perms
    : (perms && typeof perms === 'object' && typeof perms.toArray === 'function')
      ? perms.toArray()
      : [];
  for (const p of list) {
    if (typeof p === 'string') set.add(p);
    else if (p && typeof p === 'object' && typeof p.name === 'string') set.add(p.name);
  }
  return set;
}
