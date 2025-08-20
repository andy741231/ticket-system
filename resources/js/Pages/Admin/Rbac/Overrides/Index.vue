<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
  overrides: { type: Object, required: true }, // Laravel paginator
  filters: { type: Object, default: () => ({ q: '' }) },
});

// Search
const q = ref(props.filters?.q ?? '');
const submitSearch = () => {
  router.get(route('admin.rbac.overrides.index'), { q: q.value }, { preserveState: true, replace: true });
};

// Delete modal state/handlers
const showDelete = ref(false);
const deleteId = ref(null);
const openDelete = (id) => { deleteId.value = id; showDelete.value = true; };
const closeDelete = () => { showDelete.value = false; deleteId.value = null; };
const confirmDelete = () => {
  if (!deleteId.value) return;
  router.delete(route('admin.rbac.overrides.destroy', deleteId.value), {
    preserveScroll: true,
    onFinish: closeDelete,
  });
};

// Styling helpers
const effectBadgeClass = (effect) => effect === 'deny'
  ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
  : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300';

const expiryMeta = (expires_at) => {
  if (!expires_at) return { label: 'No expiry', cls: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' };
  const d = new Date(expires_at);
  const diffMs = d.getTime() - Date.now();
  if (diffMs <= 0) return { label: 'Expired', cls: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' };
  const days = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
  if (days <= 7) return { label: `Expiring in ${days}d`, cls: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' };
  return { label: 'Active', cls: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' };
};

const canManageRbacOverrides = useHasAny(['admin.rbac.overrides.manage']);
</script>

<template>
  <Head title="Overrides" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Permission Overrides</h2>
        <div class="flex gap-2">
          <Link v-if="canManageRbacOverrides" :href="route('admin.rbac.overrides.create')" class="inline-flex items-center rounded-md bg-uh-teal px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90">Create Override</Link>
          <button v-else type="button" disabled class="inline-flex items-center rounded-md bg-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 cursor-not-allowed" title="Read-only: insufficient permission">Create Override</button>
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
              <input v-model="q" type="search" placeholder="Search overrides by user, permission, app, reason, effect..." class="w-full sm:w-96 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
              <button type="submit" class="rounded-md border px-3 py-1.5 text-sm dark:border-gray-700">Search</button>
            </form>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Permission</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">App</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Effect</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Expires</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Reason</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="o in overrides.data" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <td class="px-4 py-2">{{ o.user?.name }}</td>
                    <td class="px-4 py-2 font-mono">{{ o.permission?.key }}</td>
                    <td class="px-4 py-2">
                      <span v-if="o.app?.slug" class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                        {{ o.app.slug }}
                      </span>
                      <span v-else class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Global</span>
                    </td>
                    <td class="px-4 py-2">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium uppercase" :class="effectBadgeClass(o.effect)">{{ o.effect }}</span>
                    </td>
                    <td class="px-4 py-2">
                      <div class="flex items-center gap-2">
                        <span>{{ o.expires_at ? new Date(o.expires_at).toLocaleString() : '—' }}</span>
                        <span v-if="o.expires_at" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="expiryMeta(o.expires_at).cls">{{ expiryMeta(o.expires_at).label }}</span>
                      </div>
                    </td>
                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ o.reason || '-' }}</td>
                    <td class="px-4 py-2 text-right">
                      <div class="flex justify-end gap-2">
                        <Link :href="route('admin.rbac.overrides.edit', o.id)" class="text-sm text-uh-teal hover:underline">Edit</Link>
                        <button v-if="canManageRbacOverrides" @click="openDelete(o.id)" type="button" class="text-sm text-red-600 hover:underline">Delete</button>
                        <button v-else type="button" disabled class="text-sm text-gray-400 cursor-not-allowed" title="Read-only: insufficient permission">Delete</button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!overrides.data || overrides.data.length === 0">
                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No overrides found.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex items-center justify-between" v-if="overrides.links?.length">
              <div class="text-sm text-gray-500 dark:text-gray-400">Showing {{ overrides.from }}-{{ overrides.to }} of {{ overrides.total }}</div>
              <div class="flex gap-1">
                <Link v-for="l in overrides.links" :key="l.url + l.label" :href="l.url || '#'" preserve-scroll class="px-3 py-1 rounded border text-sm"
                      :class="[l.active ? 'bg-uh-teal text-white border-uh-teal' : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800', !l.url && 'opacity-50 pointer-events-none']"
                      v-html="l.label" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <Modal :show="showDelete" @close="closeDelete">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delete this override?</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">This action cannot be undone. The user's permission cache will be flushed automatically.</p>
        <div class="mt-6 flex justify-end gap-3">
          <button type="button" class="rounded-md border px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 dark:border-gray-700" @click="closeDelete">Cancel</button>
          <button type="button" class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-red-700" @click="confirmDelete">Delete</button>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
