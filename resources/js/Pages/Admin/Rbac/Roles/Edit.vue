<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppContext, useHasAny } from '@/Extensions/useAuthz';
import ReadOnlyBanner from '@/Components/ReadOnlyBanner.vue';

const props = defineProps({
  role: { type: Object, required: true },
  apps: { type: Array, required: true },
  available_permissions: { type: Object, required: true },
  assigned_permissions: { type: Array, required: true },
  filters: { type: Object, required: true },
});

const { currentApp, currentAppSlug } = useAppContext();
const canManageRbacRoles = useHasAny(['admin.rbac.roles.manage']);
const isReadOnly = computed(() => !canManageRbacRoles || !props.role.is_mutable);

const form = useForm({
  name: props.role.name || '',
  guard_name: props.role.guard_name || 'web',
  team_id: props.role.team_id ?? '',
  slug: props.role.slug || '',
  description: props.role.description || '',
  is_mutable: props.role.is_mutable ?? true,
});

function submit() {
  if (isReadOnly.value) return;
  form.put(route('admin.rbac.roles.update', props.role.id));
}

const search = ref(props.filters?.q || '');

function submitPermissionSearch() {
  if (isReadOnly.value) return;
  router.get(route('admin.rbac.roles.edit', props.role.id), { q: search.value }, { preserveScroll: true, preserveState: true });
}

function attachPermission(permissionId) {
  if (!canManageRbacRoles || !props.role.is_mutable) return;
  router.post(route('admin.rbac.roles.permissions.attach', [props.role.id, permissionId]), {}, { preserveScroll: true });
}

function detachPermission(permissionId) {
  if (!canManageRbacRoles || !props.role.is_mutable) return;
  if (!confirm('Detach this permission from the role?')) return;
  router.delete(route('admin.rbac.roles.permissions.detach', [props.role.id, permissionId]), { preserveScroll: true });
}
</script>

<template>
  <Head title="Edit Role" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Edit Role</h2>
        <div class="flex items-center gap-3">
          <div class="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1">
            <span>Context:</span>
            <span class="inline-flex items-center rounded-full border border-uh-gold bg-uh-cream px-2 py-0.5 text-uh-black">
              <span v-if="currentApp">{{ currentApp.name }}</span>
              <span v-else class="font-mono">{{ currentAppSlug || 'global' }}</span>
            </span>
          </div>
          <Link :href="route('admin.rbac.roles.index')" class="text-sm text-uh-teal hover:underline">Back to Roles</Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6 bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <ReadOnlyBanner
            v-if="isReadOnly"
            title="Read-only"
            :message="!canManageRbacRoles ? 'You do not have permission to manage roles.' : 'Protected: this role is immutable.'"
          />
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
            <input v-model="form.name" type="text" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">App (optional)</label>
            <select v-model="form.team_id" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
              <option value="">Global</option>
              <option v-for="a in apps" :key="a.id" :value="a.id">{{ a.slug }} — {{ a.name }}</option>
            </select>
            <div v-if="form.errors.team_id" class="mt-1 text-sm text-red-600">{{ form.errors.team_id }}</div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Slug (optional)</label>
              <input v-model="form.slug" type="text" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
              <div v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Guard</label>
              <input v-model="form.guard_name" type="text" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/50" />
              <div v-if="form.errors.guard_name" class="mt-1 text-sm text-red-600">{{ form.errors.guard_name }}</div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</label>
            <textarea v-model="form.description" rows="3" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
            <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
          </div>

          <div class="flex items-center gap-2">
            <input id="is_mutable" type="checkbox" v-model="form.is_mutable" :disabled="!canManageRbacRoles || !props.role.is_mutable" class="h-4 w-4" />
            <label for="is_mutable" class="text-sm text-gray-700 dark:text-gray-300">Mutable</label>
          </div>

          <div class="flex items-center gap-2">
            <button type="submit" :disabled="form.processing || isReadOnly" class="inline-flex items-center rounded-md bg-uh-teal px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90 disabled:opacity-60">Save Changes</button>
            <Link :href="route('admin.rbac.roles.index')" class="text-sm text-gray-700 hover:underline dark:text-gray-300">Cancel</Link>
          </div>
        </form>

        <!-- Permissions Panel -->
        <div class="mt-6 space-y-4 bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Permissions</h3>
            <span v-if="!canManageRbacRoles" class="text-xs text-gray-500">Read-only: insufficient permission</span>
            <span v-else-if="!props.role.is_mutable" class="text-xs text-gray-500">Protected: role is immutable</span>
          </div>

          <!-- Assigned Permissions -->
          <div>
            <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Assigned</h4>
            <div v-if="assigned_permissions.length" class="flex flex-wrap gap-2">
              <span v-for="ap in assigned_permissions" :key="ap.id" class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900">
                <span class="font-mono">{{ ap.key }}</span>
                <button
                  v-if="canManageRbacRoles && props.role.is_mutable"
                  type="button"
                  class="text-red-600 hover:underline"
                  @click="detachPermission(ap.id)"
                >Detach</button>
              </span>
            </div>
            <div v-else class="text-sm text-gray-500 dark:text-gray-400">No permissions assigned.</div>
          </div>

          <hr class="my-2 border-gray-200 dark:border-gray-700" />

          <!-- Available Permissions -->
          <div class="space-y-3">
            <div class="flex items-center gap-2">
              <input v-model="search" @keyup.enter="submitPermissionSearch" type="text" placeholder="Search permissions (name or key)" :disabled="isReadOnly" class="block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
              <button type="button" :disabled="isReadOnly" class="rounded-md bg-uh-teal px-3 py-2 text-sm text-white disabled:opacity-60" @click="submitPermissionSearch">Search</button>
            </div>

            <div class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Key</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                    <th class="px-4 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="p in available_permissions.data" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <td class="px-4 py-2 font-mono">{{ p.key }}</td>
                    <td class="px-4 py-2">{{ p.name }}</td>
                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ p.description || '-' }}</td>
                    <td class="px-4 py-2 text-right">
                      <button
                        type="button"
                        class="text-sm text-uh-teal hover:underline disabled:opacity-50"
                        :disabled="!canManageRbacRoles || !props.role.is_mutable"
                        @click="attachPermission(p.id)"
                      >Attach</button>
                    </td>
                  </tr>
                  <tr v-if="!available_permissions.data || available_permissions.data.length === 0">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No permissions found.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div v-if="available_permissions.links && available_permissions.links.length > 1" class="flex flex-wrap items-center gap-2 pt-2">
              <Link v-for="l in available_permissions.links" :key="l.url + (l.label || '')" :href="l.url || '#'" v-html="l.label" :class="['text-sm', l.active ? 'font-semibold text-uh-teal' : 'text-gray-600 hover:underline dark:text-gray-300']" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
