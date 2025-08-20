<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppContext, useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
  permissions: { type: Object, required: true }, // Laravel paginator
  filters: { type: Object, default: () => ({ q: '' }) },
});

const { currentApp, currentAppSlug } = useAppContext();
const canManageRbacPermissions = useHasAny(['admin.rbac.permissions.manage']);

const confirmAndDelete = (id, label) => {
  if (confirm(`Delete permission "${label}"? This cannot be undone.`)) {
    router.delete(route('admin.rbac.permissions.destroy', id), { preserveScroll: true });
  }
};

const q = ref(props.filters?.q ?? '');
const submitSearch = () => {
  router.get(route('admin.rbac.permissions.index'), { q: q.value }, { preserveState: true, replace: true });
};
</script>

<template>
  <Head title="Permissions" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Permissions</h2>
        <div class="flex items-center gap-3">
          <div class="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1">
            <span>Context:</span>
            <span class="inline-flex items-center rounded-full border border-uh-gold bg-uh-cream px-2 py-0.5 text-uh-black">
              <span v-if="currentApp">{{ currentApp.name }}</span>
              <span v-else class="font-mono">{{ currentAppSlug || 'global' }}</span>
            </span>
          </div>
          <Link v-if="canManageRbacPermissions" :href="route('admin.rbac.permissions.create')" class="inline-flex items-center rounded-md bg-uh-teal px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90">Create Permission</Link>
          <Link :href="route('admin.rbac.dashboard')" class="text-sm text-uh-teal hover:underline">Back to RBAC</Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <!-- Search -->
            <form @submit.prevent="submitSearch" class="mb-4 flex items-center gap-2">
              <input v-model="q" type="search" placeholder="Search permissions by key, name, description..." class="w-full sm:w-96 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
              <button type="submit" class="rounded-md border px-3 py-1.5 text-sm dark:border-gray-700">Search</button>
            </form>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">ID</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Key</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="p in permissions.data" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <td class="px-4 py-2">{{ p.id }}</td>
                    <td class="px-4 py-2 font-mono">{{ p.key }}</td>
                    <td class="px-4 py-2">{{ p.name }}</td>
                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ p.description || '-' }}</td>
                    <td class="px-4 py-2 text-right">
                      <div class="flex justify-end gap-2">
                        <Link :href="route('admin.rbac.permissions.edit', p.id)" class="text-sm text-uh-teal hover:underline">Edit</Link>
                        <button
                          v-if="canManageRbacPermissions && p.is_mutable"
                          @click="confirmAndDelete(p.id, p.key || p.name)"
                          type="button"
                          class="text-sm text-red-600 hover:underline"
                        >Delete</button>
                        <span
                          v-else-if="!p.is_mutable"
                          class="text-sm text-gray-400 cursor-not-allowed"
                          title="Protected: immutable permission"
                        >Protected</span>
                        <button
                          v-else
                          type="button"
                          disabled
                          class="text-sm text-gray-400 cursor-not-allowed"
                          title="Read-only: insufficient permission"
                        >Delete</button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!permissions.data || permissions.data.length === 0">
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No permissions found.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex items-center justify-between" v-if="permissions.links?.length">
              <div class="text-sm text-gray-500 dark:text-gray-400">Showing {{ permissions.from }}-{{ permissions.to }} of {{ permissions.total }}</div>
              <div class="flex gap-1">
                <Link v-for="l in permissions.links" :key="l.url + l.label" :href="l.url || '#'" preserve-scroll class="px-3 py-1 rounded border text-sm"
                      :class="[l.active ? 'bg-uh-teal text-white border-uh-teal' : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800', !l.url && 'opacity-50 pointer-events-none']"
                      v-html="l.label" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
