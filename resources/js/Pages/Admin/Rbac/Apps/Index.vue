<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useAppContext, useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
  apps: { type: Object, required: true }, // Laravel paginator
});

const { currentApp, currentAppSlug } = useAppContext();
const canManageRbacPermissions = useHasAny(['admin.rbac.permissions.manage']);

// Form state
const slug = ref('');
const name = ref('');

const submitting = ref(false);
const errors = ref({});
const page = usePage();

const submit = () => {
  if (!canManageRbacPermissions.value) return;
  submitting.value = true;
  errors.value = {};
  router.post(
    route('admin.rbac.apps.store'),
    { slug: slug.value.trim(), name: name.value.trim() },
    {
      preserveScroll: true,
      onError: (e) => {
        errors.value = e || {};
      },
      onFinish: () => {
        submitting.value = false;
      },
      onSuccess: () => {
        slug.value = '';
        name.value = '';
      },
    }
  );
};
</script>

<template>
  <Head title="Manage Apps" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Apps</h2>
        <div class="flex items-center gap-3">
          <div class="text-xs text-gray-600 dark:text-gray-300 flex items-center gap-1">
            <span>Context:</span>
            <span class="inline-flex items-center rounded-full border border-uh-gold bg-uh-cream px-2 py-0.5 text-uh-black">
              <span v-if="currentApp">{{ currentApp.name }}</span>
              <span v-else class="font-mono">{{ currentAppSlug || 'global' }}</span>
            </span>
          </div>
          <Link :href="route('admin.rbac.dashboard')" class="text-sm text-uh-teal hover:underline">Back to RBAC</Link>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <!-- Flash success -->
            <div
              v-if="page.props.flash && page.props.flash.success"
              class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-green-800 dark:border-green-700 dark:bg-green-900/30 dark:text-green-200"
              role="status"
            >
              {{ page.props.flash.success }}
            </div>

            <!-- Create App Form -->
            <fieldset class="mb-6" :disabled="!canManageRbacPermissions">
              <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Create New App</legend>
              <form @submit.prevent="submit" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                  <label for="slug" class="block text-xs font-medium text-gray-600 dark:text-gray-400">Slug</label>
                  <input
                    id="slug"
                    v-model="slug"
                    type="text"
                    placeholder="e.g. newsletter"
                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    aria-describedby="slug-help"
                  />
                  <p id="slug-help" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lowercase, numbers and dashes only. Must match URL prefix used for this app.</p>
                  <p v-if="errors.slug" class="mt-1 text-xs text-red-600">{{ errors.slug }}</p>
                </div>
                <div class="flex-1">
                  <label for="name" class="block text-xs font-medium text-gray-600 dark:text-gray-400">Name</label>
                  <input
                    id="name"
                    v-model="name"
                    type="text"
                    placeholder="e.g. Newsletter"
                    class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                  />
                  <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                </div>
                <div class="sm:w-auto">
                  <button
                    type="submit"
                    :disabled="!canManageRbacPermissions || submitting"
                    class="inline-flex items-center rounded-md bg-uh-teal px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-uh-teal/90 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {{ submitting ? 'Creating...' : 'Create App' }}
                  </button>
                </div>
              </form>
              <div v-if="!canManageRbacPermissions" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                You do not have permission to create apps. Contact an administrator.
              </div>
            </fieldset>

            <!-- Apps Table -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">ID</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Slug</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="a in apps.data" :key="a.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <td class="px-4 py-2">{{ a.id }}</td>
                    <td class="px-4 py-2 font-mono">{{ a.slug }}</td>
                    <td class="px-4 py-2">{{ a.name }}</td>
                  </tr>
                  <tr v-if="!apps.data || apps.data.length === 0">
                    <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No apps found.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex items-center justify-between" v-if="apps.links?.length">
              <div class="text-sm text-gray-500 dark:text-gray-400">Showing {{ apps.from }}-{{ apps.to }} of {{ apps.total }}</div>
              <div class="flex gap-1">
                <Link
                  v-for="l in apps.links"
                  :key="(l.url || '') + l.label"
                  :href="l.url || '#'"
                  preserve-scroll
                  class="px-3 py-1 rounded border text-sm"
                  :class="[
                    l.active ? 'bg-uh-teal text-white border-uh-teal' : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800',
                    !l.url && 'opacity-50 pointer-events-none',
                  ]"
                  v-html="l.label"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
