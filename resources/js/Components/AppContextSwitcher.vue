<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const availableApps = computed(() => page.props.appContext?.availableApps ?? []);
const currentApp = computed(() => page.props.appContext?.currentApp ?? null);
const isOpen = ref(false);
const root = ref(null);

function navigateToApp(slug) {
  let url = '/';
  switch (slug) {
    case 'tickets':
      url = route('tickets.index');
      break;
    case 'directory':
      url = route('directory.index');
      break;
    case 'users':
      // Admin dashboard lives under /users/admin/dashboard
      url = route('admin.dashboard');
      break;
    default:
      // Fallback: try slug root; if route undefined, go home
      url = `/${slug}`;
  }
  isOpen.value = false;
  router.visit(url);
}

function handleClickOutside(e) {
  if (!root.value) return;
  if (!root.value.contains(e.target)) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div ref="root" class="relative" @keydown.escape.prevent.stop="isOpen = false">
    <!-- Current App Badge / Trigger -->
    <button
      type="button"
      class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
      :title="currentApp ? `Current app: ${currentApp.name}` : 'Select app'"
      @click.stop="isOpen = !isOpen"
      aria-haspopup="listbox"
      :aria-expanded="isOpen"
    >
      <span
        class="inline-flex h-2.5 w-2.5 rounded-full"
        :class="{
          'bg-uh-red': currentApp?.slug === 'users',
          'bg-uh-teal': currentApp?.slug === 'tickets',
          'bg-uh-gold': currentApp?.slug === 'directory',
          'bg-gray-400': !currentApp
        }"
      />
      <span class="truncate min-w-[5rem] text-left">
        {{ currentApp ? currentApp.name : 'Select App' }}
      </span>
      <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.25 8.29a.75.75 0 01-.02-1.08z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Dropdown -->
    <div
      v-show="isOpen"
      class="absolute right-0 z-40 mt-2 w-56 rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
      role="listbox"
    >
      <div class="max-h-64 overflow-y-auto py-1">
        <button
          v-for="app in availableApps"
          :key="app.id"
          type="button"
          class="flex w-full items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
          :class="{ 'bg-gray-50 dark:bg-gray-700': currentApp && currentApp.slug === app.slug }"
          @click="navigateToApp(app.slug)"
        >
          <span class="truncate">{{ app.name }}</span>
          <span v-if="currentApp && currentApp.slug === app.slug" class="text-uh-teal text-xs">Current</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* No additional styles; rely on Tailwind */
</style>
