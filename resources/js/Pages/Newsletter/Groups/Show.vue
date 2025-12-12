<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  group: Object,
  subscribers: Object,
  subscribersOptions: Object,
  filters: Object,
});

const editForm = useForm({
  name: props.group.name || '',
  description: props.group.description || '',
  color: props.group.color || '#3b82f6',
  is_active: props.group.is_active ?? true,
  is_external: props.group.is_external ?? false,
});

function updateGroup() {
  editForm.put(route('newsletter.groups.update', props.group.id));
}

function deleteGroup() {
  if (confirm(`Delete group "${props.group.name}"?`)) {
    router.delete(route('newsletter.groups.destroy', props.group.id));
  }
}

// Add/remove subscribers
const addForm = useForm({
  subscriber_ids: [],
});

function addSubscribers() {
  if (!addForm.subscriber_ids.length) return;
  addForm.post(route('newsletter.groups.add-subscribers', props.group.id), {
    preserveScroll: true,
    onSuccess: () => {
      addForm.subscriber_ids = [];
      openAddModal.value = false;
    },
  });
}

function removeSubscriber(id) {
  router.post(route('newsletter.groups.remove-subscribers', props.group.id), {
    subscriber_ids: [id],
  });
}

// Modal + filters for available subscribers
const openAddModal = ref(false);
const search = ref(props.filters?.search || '');
const sort = ref(props.filters?.sort || 'created_at');
const direction = ref(props.filters?.direction || 'desc');

function applyFilters() {
  router.get(route('newsletter.groups.show', props.group.id), {
    search: search.value || undefined,
    sort: sort.value,
    direction: direction.value,
  }, { preserveState: true, replace: true, preserveScroll: true });
}

function goToOptionsPage(url) {
  if (!url) return;
  router.get(url, {}, { preserveState: true, replace: true, preserveScroll: true });
}

const currentOptionIds = computed(() => (props.subscribersOptions?.data || []).map(o => o.id));
const allPageSelected = computed({
  get() {
    const ids = currentOptionIds.value;
    return ids.length > 0 && ids.every(id => addForm.subscriber_ids.includes(id));
  },
  set(val) {
    if (val) {
      const merged = new Set([...addForm.subscriber_ids, ...currentOptionIds.value]);
      addForm.subscriber_ids = Array.from(merged);
    } else {
      addForm.subscriber_ids = addForm.subscriber_ids.filter(id => !currentOptionIds.value.includes(id));
    }
  }
});
</script>

<template>
  <Head :title="`Group: ${group.name}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Group: {{ group.name }}</h2>
        <div class="flex gap-2">
          <Link :href="route('newsletter.groups.index')"
                class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">Back</Link>
          <button @click="deleteGroup"
                  class="px-3 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700">Delete</button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Edit group -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Edit Group</h3>
          <form @submit.prevent="updateGroup" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
              <input v-model="editForm.name" type="text" required
                     class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
              <textarea v-model="editForm.description" rows="3"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
              <div class="mt-1 flex items-center gap-2">
                <input v-model="editForm.color" type="color" class="h-10 w-16 border border-gray-300 dark:border-gray-700 rounded-md" />
                <input v-model="editForm.color" type="text" class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm" />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <input id="is_active" v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500" />
              <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
            </div>
            <div class="flex items-center gap-2">
              <input id="is_external" v-model="editForm.is_external" type="checkbox" class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500" />
              <label for="is_external" class="text-sm text-gray-700 dark:text-gray-300">External (show on public preferences page)</label>
            </div>
            <div class="md:col-span-2 flex justify-end">
              <button type="submit" :disabled="editForm.processing"
                      class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 disabled:opacity-50">Save Changes</button>
            </div>
          </form>
        </div>

        <!-- Subscribers list -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between gap-4 flex-wrap">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Subscribers ({{ subscribers.total }})</h3>
              <button @click="openAddModal = true" class="px-3 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700">Add Subscribers</button>
            </div>
          </div>
          <div class="p-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="s in subscribers.data" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ s.email }}</td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ s.full_name || [s.first_name, s.last_name].filter(Boolean).join(' ') || s.name || '-' }}</td>
                  <td class="px-6 py-4 text-right text-sm">
                    <div class="flex justify-end gap-3">
                      <Link :href="route('newsletter.subscribers.show', s.id)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">View</Link>
                      <button @click="removeSubscriber(s.id)" class="text-red-600 hover:text-red-800 dark:text-red-400">Remove</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="subscribers.links" class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-end gap-1">
              <Link v-for="link in subscribers.links" :key="link.label" :href="link.url" v-html="link.label"
                    :class="`px-3 py-2 text-sm border rounded-md ${link.active ? 'bg-purple-600 text-white border-purple-600' : link.url ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' : 'bg-gray-100 dark:bg-gray-900 text-gray-400 border-gray-300 dark:border-gray-600 cursor-not-allowed'}`" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Subscribers Modal -->
    <div v-if="openAddModal" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/50" @click="openAddModal = false"></div>
      <div class="relative bg-white dark:bg-gray-800 w-full max-w-3xl mx-4 rounded-lg shadow-xl">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Add Subscribers to {{ group.name }}</h3>
          <button @click="openAddModal = false" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">✕</button>
        </div>
        <div class="p-4 space-y-4">
          <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[220px]">
              <label class="block text-sm text-gray-600 dark:text-gray-300">Search</label>
              <input v-model="search" @keyup.enter="applyFilters" type="text"
                     class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" placeholder="Search email or name" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 dark:text-gray-300">Sort</label>
              <select v-model="sort" class="mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                <option value="created_at">Created</option>
                <option value="email">Email</option>
                <option value="name">Name</option>
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-600 dark:text-gray-300">Direction</label>
              <select v-model="direction" class="mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                <option value="desc">Desc</option>
                <option value="asc">Asc</option>
              </select>
            </div>
            <button @click="applyFilters" class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Apply</button>
          </div>

          <!-- Selection Controls -->
          <div class="flex items-center justify-between bg-uh-cream/20 dark:bg-gray-700/30 rounded-lg p-3">
            <label class="flex items-center gap-3 text-sm font-medium text-uh-slate dark:text-gray-300 cursor-pointer">
              <input 
                type="checkbox" 
                :checked="allPageSelected" 
                @change="allPageSelected = $event.target.checked" 
                class="rounded border-uh-gray/30 text-uh-red shadow-sm focus:ring-uh-red/20"
              >
              Select all on this page
            </label>
            <div class="text-sm font-medium text-uh-red">
              {{ addForm.subscriber_ids.length }} selected
            </div>
          </div>

          <div class="max-h-80 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-4 py-2"></th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="opt in subscribersOptions.data" :key="opt.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-4 py-2">
                    <input type="checkbox" v-model="addForm.subscriber_ids" :value="opt.id" class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500" />
                  </td>
                  <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ opt.email }}</td>
                  <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ [opt.first_name, opt.last_name].filter(Boolean).join(' ') || '-' }}</td>
                </tr>
                <tr v-if="!subscribersOptions.data?.length">
                  <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No available subscribers</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="subscribersOptions.links" class="flex items-center justify-end gap-1">
            <button
              v-for="link in subscribersOptions.links"
              :key="link.label"
              :disabled="!link.url"
              @click="goToOptionsPage(link.url)"
              v-html="link.label"
              :class="`px-3 py-2 text-sm border rounded-md ${link.active ? 'bg-purple-600 text-white border-purple-600' : link.url ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700' : 'bg-gray-100 dark:bg-gray-900 text-gray-400 border-gray-300 dark:border-gray-600 cursor-not-allowed'}`" />
          </div>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
          <button @click="openAddModal = false" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md">Cancel</button>
          <button @click="addSubscribers" :disabled="addForm.processing || !addForm.subscriber_ids.length" class="px-3 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 disabled:opacity-50">Add Selected</button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
