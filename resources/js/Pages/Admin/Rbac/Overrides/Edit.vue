<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useHasAny } from '@/Extensions/useAuthz';
import ReadOnlyBanner from '@/Components/ReadOnlyBanner.vue';

const props = defineProps({
  override: { type: Object, required: true },
  users: { type: Array, required: true },
  permissions: { type: Array, required: true },
  apps: { type: Array, required: true },
});

const form = useForm({
  user_id: props.override.user_id,
  permission_id: props.override.permission_id,
  team_id: props.override.team_id ?? '',
  effect: props.override.effect,
  reason: props.override.reason ?? '',
  expires_at: props.override.expires_at ? formatDateLocal(props.override.expires_at) : '',
});

function formatDateLocal(iso) {
  // Convert ISO -> yyyy-MM-ddTHH:mm for datetime-local
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  const yyyy = d.getFullYear();
  const mm = pad(d.getMonth() + 1);
  const dd = pad(d.getDate());
  const hh = pad(d.getHours());
  const mi = pad(d.getMinutes());
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
}

function submit() {
  form.put(route('admin.rbac.overrides.update', props.override.id));
}

const canManageRbacOverrides = useHasAny(['admin.rbac.overrides.manage']);
const isReadOnly = computed(() => !canManageRbacOverrides.value);
</script>

<template>
  <Head title="Edit Override" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Edit Permission Override #{{ override.id }}</h2>
        <Link :href="route('admin.rbac.overrides.index')" class="text-sm text-uh-teal hover:underline">Back to Overrides</Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6 bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <ReadOnlyBanner v-if="isReadOnly" title="Read-only" message="You do not have permission to manage permission overrides." />
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
            <select v-model="form.user_id" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
            <div v-if="form.errors.user_id" class="mt-1 text-sm text-red-600">{{ form.errors.user_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Permission</label>
            <select v-model="form.permission_id" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
              <option v-for="p in permissions" :key="p.id" :value="p.id">{{ p.key }} — {{ p.name }}</option>
            </select>
            <div v-if="form.errors.permission_id" class="mt-1 text-sm text-red-600">{{ form.errors.permission_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">App (optional)</label>
            <select v-model="form.team_id" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
              <option value="">Global (no app)</option>
              <option v-for="a in apps" :key="a.id" :value="a.id">{{ a.slug }} — {{ a.name }}</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Global applies across all apps. Choose an app to scope this override.</p>
            <div v-if="form.errors.team_id" class="mt-1 text-sm text-red-600">{{ form.errors.team_id }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Effect</label>
            <select v-model="form.effect" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
              <option value="allow">Allow</option>
              <option value="deny">Deny</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Deny overrides block access even if a role grants it.</p>
            <div v-if="form.errors.effect" class="mt-1 text-sm text-red-600">{{ form.errors.effect }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expires At (optional)</label>
            <input type="datetime-local" v-model="form.expires_at" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank for a permanent override. Expired overrides are ignored and pruned.</p>
            <div v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600">{{ form.errors.expires_at }}</div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason (optional)</label>
            <textarea v-model="form.reason" rows="3" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Add context for audit logs (e.g., incident, temporary access, etc.).</p>
            <div v-if="form.errors.reason" class="mt-1 text-sm text-red-600">{{ form.errors.reason }}</div>
          </div>

          <div class="flex items-center gap-2">
            <button type="submit" :disabled="form.processing || isReadOnly || !form.user_id || !form.permission_id || !form.effect" class="inline-flex items-center rounded-md bg-uh-teal px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90 disabled:opacity-60">Save Changes</button>
            <Link :href="route('admin.rbac.overrides.index')" class="text-sm text-gray-700 hover:underline dark:text-gray-300">Cancel</Link>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
