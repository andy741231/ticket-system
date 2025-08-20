<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppContext, useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
  stats: { type: Object, required: true },
});

const { currentApp, currentAppSlug } = useAppContext();
const canAccessUserAdmin = useHasAny(['hub.user.view', 'hub.user.manage']);
const canManageRoles = useHasAny(['admin.rbac.roles.manage']);
const canManagePermissions = useHasAny(['admin.rbac.permissions.manage']);
const canManageOverrides = useHasAny(['admin.rbac.overrides.manage']);
</script>

<template>
  <Head title="RBAC Admin" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
        RBAC Administration
      </h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <div class="mb-6 flex items-center justify-between">
              <div>
                <h3 class="text-lg font-medium">Overview</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Quick snapshot of RBAC entities.</p>
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <span>Context:</span>
                <span class="inline-flex items-center rounded-full border border-uh-gold bg-uh-cream px-2 py-0.5 text-xs text-uh-black">
                  <span v-if="currentApp">{{ currentApp.name }}</span>
                  <span v-else class="font-mono">{{ currentAppSlug || 'global' }}</span>
                </span>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Apps</div>
                <div class="mt-1 text-3xl font-semibold">{{ props.stats.apps }}</div>
              </div>
              <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Roles</div>
                <div class="mt-1 text-3xl font-semibold">{{ props.stats.roles }}</div>
              </div>
              <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Permissions</div>
                <div class="mt-1 text-3xl font-semibold">{{ props.stats.permissions }}</div>
              </div>
              <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Overrides</div>
                <div class="mt-1 text-3xl font-semibold">{{ props.stats.overrides }}</div>
              </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
              <Link
                v-if="canManageRoles"
                :href="route('admin.rbac.roles.index')"
                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-700 dark:hover:bg-gray-800"
              >
                Manage Roles
              </Link>
              <Link
                v-if="canManagePermissions"
                :href="route('admin.rbac.permissions.index')"
                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-700 dark:hover:bg-gray-800"
              >
                View Permissions
              </Link>
              <Link
                v-if="canManageOverrides"
                :href="route('admin.rbac.overrides.index')"
                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-700 dark:hover:bg-gray-800"
              >
                Permission Overrides
              </Link>
              <Link
                v-if="canAccessUserAdmin"
                :href="route('admin.dashboard')"
                class="inline-flex items-center rounded-md bg-uh-teal px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90 focus:outline-none focus:ring-2 focus:ring-uh-teal focus:ring-offset-2 dark:focus:ring-offset-gray-800"
              >
                Go to User Admin
              </Link>
              <Link
                :href="route('dashboard')"
                class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:ring-gray-700 dark:hover:bg-gray-800"
              >
                Back to Dashboard
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
