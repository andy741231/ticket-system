<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
  MagnifyingGlassIcon,
  PlusIcon,
  FunnelIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  TrashIcon,
  UserGroupIcon,
  CheckIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  subscribers: Object,
  groups: Array,
  filters: Object,
});

const searchForm = useForm({
  search: props.filters.search || '',
  status: props.filters.status || '',
  group_id: props.filters.group_id || '',
});

const selectedSubscribers = ref([]);
const showBulkActions = computed(() => selectedSubscribers.value.length > 0);
const showAddModal = ref(false);
const showImportModal = ref(false);

const addForm = useForm({
  email: '',
  name: '',
  first_name: '',
  last_name: '',
  status: 'active',
  groups: [],
});

const importForm = useForm({
  file: null,
  group_id: '',
});

const statusColors = {
  active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  unsubscribed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
  bounced: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  pending: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
};

function search() {
  searchForm.get(route('newsletter.subscribers.index'), {
    preserveState: true,
    replace: true,
  });
}

function clearFilters() {
  searchForm.reset();
  search();
}

function toggleSubscriber(subscriberId) {
  const index = selectedSubscribers.value.indexOf(subscriberId);
  if (index > -1) {
    selectedSubscribers.value.splice(index, 1);
  } else {
    selectedSubscribers.value.push(subscriberId);
  }
}

function toggleAll() {
  if (selectedSubscribers.value.length === props.subscribers.data.length) {
    selectedSubscribers.value = [];
  } else {
    selectedSubscribers.value = props.subscribers.data.map(s => s.id);
  }
}

function addSubscriber() {
  addForm.post(route('newsletter.subscribers.store'), {
    onSuccess: () => {
      addForm.reset();
      showAddModal.value = false;
    },
  });
}

function importSubscribers() {
  importForm.post(route('newsletter.subscribers.bulk-import'), {
    onSuccess: () => {
      importForm.reset();
      showImportModal.value = false;
    },
  });
}

function exportSubscribers() {
  const params = new URLSearchParams({
    status: searchForm.status,
    group_id: searchForm.group_id,
  });
  window.location.href = route('newsletter.subscribers.bulk-export') + '?' + params.toString();
}

function bulkDelete() {
  if (confirm('Are you sure you want to delete the selected subscribers?')) {
    router.post(route('newsletter.subscribers.bulk-delete'), {
      subscriber_ids: selectedSubscribers.value,
    }, {
      onSuccess: () => {
        selectedSubscribers.value = [];
      },
    });
  }
}

function bulkUpdateStatus(status) {
  router.post(route('newsletter.subscribers.bulk-update-status'), {
    subscriber_ids: selectedSubscribers.value,
    status: status,
  }, {
    onSuccess: () => {
      selectedSubscribers.value = [];
    },
  });
}

function deleteSubscriber(subscriber) {
  if (confirm(`Are you sure you want to delete ${subscriber.email}?`)) {
    router.delete(route('newsletter.subscribers.destroy', subscriber.id));
  }
}
</script>

<template>
  <Head title="Newsletter Subscribers" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Newsletter Subscribers
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage your email subscriber list and groups
          </p>
        </div>
        <div class="flex space-x-3">
          <button
            @click="showImportModal = true"
            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            <ArrowUpTrayIcon class="w-4 h-4 mr-2" />
            Import
          </button>
          <button
            @click="showAddModal = true"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            <PlusIcon class="w-4 h-4 mr-2" />
            Add Subscriber
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          
          <!-- Filters and Search -->
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row gap-4">
              <div class="flex-1">
                <div class="relative">
                  <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                  <input
                    v-model="searchForm.search"
                    @keyup.enter="search"
                    type="text"
                    placeholder="Search subscribers..."
                    class="pl-10 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  />
                </div>
              </div>
              
              <div class="flex gap-2">
                <select
                  v-model="searchForm.status"
                  @change="search"
                  class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                >
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="unsubscribed">Unsubscribed</option>
                  <option value="bounced">Bounced</option>
                  <option value="pending">Pending</option>
                </select>

                <select
                  v-model="searchForm.group_id"
                  @change="search"
                  class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                >
                  <option value="">All Groups</option>
                  <option v-for="group in groups" :key="group.id" :value="group.id">
                    {{ group.name }}
                  </option>
                </select>

                <button
                  @click="clearFilters"
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  Clear
                </button>

                <button
                  @click="exportSubscribers"
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  <ArrowDownTrayIcon class="w-4 h-4 mr-2 inline" />
                  Export
                </button>
              </div>
            </div>
          </div>

          <!-- Bulk Actions -->
          <div v-if="showBulkActions" class="p-4 bg-blue-50 dark:bg-blue-900/20 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <span class="text-sm text-blue-700 dark:text-blue-300">
                {{ selectedSubscribers.length }} subscriber(s) selected
              </span>
              <div class="flex gap-2">
                <button
                  @click="bulkUpdateStatus('active')"
                  class="px-3 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200"
                >
                  Mark Active
                </button>
                <button
                  @click="bulkUpdateStatus('unsubscribed')"
                  class="px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200"
                >
                  Unsubscribe
                </button>
                <button
                  @click="bulkDelete"
                  class="px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>

          <!-- Subscribers Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left">
                    <input
                      type="checkbox"
                      :checked="selectedSubscribers.length === subscribers.data.length && subscribers.data.length > 0"
                      @change="toggleAll"
                      class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                    />
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Subscriber
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Groups
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Subscribed
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="subscriber in subscribers.data" :key="subscriber.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4">
                    <input
                      type="checkbox"
                      :checked="selectedSubscribers.includes(subscriber.id)"
                      @change="toggleSubscriber(subscriber.id)"
                      class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                    />
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ subscriber.full_name.charAt(0).toUpperCase() }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                          {{ subscriber.full_name }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                          {{ subscriber.email }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusColors[subscriber.status]}`">
                      {{ subscriber.status }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1">
                      <span
                        v-for="group in subscriber.groups"
                        :key="group.id"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                      >
                        {{ group.name }}
                      </span>
                      <span v-if="subscriber.groups.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                        No groups
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ subscriber.subscribed_at ? new Date(subscriber.subscribed_at).toLocaleDateString() : '-' }}
                  </td>
                  <td class="px-6 py-4 text-right text-sm font-medium">
                    <div class="flex justify-end gap-2">
                      <Link
                        :href="route('newsletter.subscribers.show', subscriber.id)"
                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                      >
                        View
                      </Link>
                      <button
                        @click="deleteSubscriber(subscriber)"
                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="subscribers.links" class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ subscribers.from }} to {{ subscribers.to }} of {{ subscribers.total }} results
              </div>
              <div class="flex gap-1">
                <Link
                  v-for="link in subscribers.links"
                  :key="link.label"
                  :href="link.url"
                  v-html="link.label"
                  :class="`px-3 py-2 text-sm border rounded-md ${
                    link.active
                      ? 'bg-blue-600 text-white border-blue-600'
                      : link.url
                      ? 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                      : 'bg-gray-100 dark:bg-gray-900 text-gray-400 border-gray-300 dark:border-gray-600 cursor-not-allowed'
                  }`"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Subscriber Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showAddModal = false"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            Add New Subscriber
          </h3>
          <form @submit.prevent="addSubscriber" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
              <input
                v-model="addForm.email"
                type="email"
                required
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
              />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                <input
                  v-model="addForm.first_name"
                  type="text"
                  class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                <input
                  v-model="addForm.last_name"
                  type="text"
                  class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
              <select
                v-model="addForm.status"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
              >
                <option value="active">Active</option>
                <option value="pending">Pending</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Groups</label>
              <div class="mt-2 space-y-2 max-h-32 overflow-y-auto">
                <label v-for="group in groups" :key="group.id" class="flex items-center">
                  <input
                    v-model="addForm.groups"
                    :value="group.id"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                  />
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ group.name }}</span>
                </label>
              </div>
            </div>
            <div class="flex justify-end gap-3">
              <button
                type="button"
                @click="showAddModal = false"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="addForm.processing"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                Add Subscriber
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showImportModal = false"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            Import Subscribers
          </h3>
          <form @submit.prevent="importSubscribers" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">CSV File</label>
              <input
                @input="importForm.file = $event.target.files[0]"
                type="file"
                accept=".csv,.txt"
                required
                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                CSV format: Email, Name, First Name, Last Name
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add to Group (Optional)</label>
              <select
                v-model="importForm.group_id"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
              >
                <option value="">No group</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">
                  {{ group.name }}
                </option>
              </select>
            </div>
            <div class="flex justify-end gap-3">
              <button
                type="button"
                @click="showImportModal = false"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="importForm.processing"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                Import
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
