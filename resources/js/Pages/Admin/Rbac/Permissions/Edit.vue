<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppContext, useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
  permission: { type: Object, required: true },
});

const { currentApp, currentAppSlug } = useAppContext();
const canManageRbacPermissions = useHasAny(['admin.rbac.permissions.manage']);
const isReadOnly = computed(() => !canManageRbacPermissions || !props.permission.is_mutable);

const form = useForm({
  name: props.permission.name || '',
  key: props.permission.key || '',
  description: props.permission.description || '',
  guard_name: props.permission.guard_name || 'web',
  is_mutable: props.permission.is_mutable ?? true,
});

function submit() {
  if (isReadOnly.value) return;
  form.put(route('admin.rbac.permissions.update', props.permission.id));
}
</script>

<template>
  <Head title="Edit Permission" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Edit Permission</h2>
        <div class="flex items-center gap-3">
          <div class="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1">
            <span>Context:</span>
            <span class="inline-flex items-center rounded-full border border-uh-gold bg-uh-cream px-2 py-0.5 text-uh-black">
              <span v-if="currentApp">{{ currentApp.name }}</span>
              <span v-else class="font-mono">{{ currentAppSlug || 'global' }}</span>
            </span>
          </div>
          <Link :href="route('admin.rbac.permissions.index')" class="text-sm text-uh-teal hover:underline">Back to Permissions</Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6 bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div v-if="isReadOnly" class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900">
            <span v-if="!canManageRbacPermissions">Read-only: insufficient permission.</span>
            <span v-else-if="!props.permission.is_mutable">Protected: this permission is immutable.</span>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
              <input v-model="form.name" type="text" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900" />
              <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Key (optional)</label>
              <input v-model="form.key" type="text" :disabled="isReadOnly" class="mt-1 block w-full rounded-md border-gray-300 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 font-mono" />
              <div v-if="form.errors.key" class="mt-1 text-sm text-red-600">{{ form.errors.key }}</div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            <input id="is_mutable" type="checkbox" v-model="form.is_mutable" :disabled="!canManageRbacPermissions || !props.permission.is_mutable" class="h-4 w-4" />
            <label for="is_mutable" class="text-sm text-gray-700 dark:text-gray-300">Mutable</label>
          </div>

          <div class="flex items-center gap-2">
            <button type="submit" :disabled="form.processing || isReadOnly" class="inline-flex items-center rounded-md bg-uh-teal px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90 disabled:opacity-60">Save Changes</button>
            <Link :href="route('admin.rbac.permissions.index')" class="text-sm text-gray-700 hover:underline dark:text-gray-300">Cancel</Link>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
