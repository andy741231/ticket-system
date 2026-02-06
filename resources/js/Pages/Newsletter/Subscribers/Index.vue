<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import {
  MagnifyingGlassIcon,
  PlusIcon,
  FunnelIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  TrashIcon,
  UserGroupIcon,
  EnvelopeIcon,
  CheckIcon,
  XMarkIcon,
  EyeIcon,
  PencilIcon,
  ExclamationTriangleIcon
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
  per_page: props.filters.per_page || 15,
});

const selectedSubscribers = ref([]);
const showBulkActions = computed(() => selectedSubscribers.value.length > 0);
const showAddModal = ref(false);
const showImportModal = ref(false);
const existingSubscriber = ref(null);
const showAddToGroupsModal = ref(false);

// Import errors passed via session flash (separate from validation error bag)
const page = usePage();
const importErrors = computed(() => page.props.import_errors || []);
const importReport = computed(() => page.props.import_report || null);
const addFormErrors = computed(() => page.props.errors || {});

const addForm = useForm({
  email: '',
  first_name: '',
  last_name: '',
  organization: '',
  status: 'active',
  groups: [],
});

const addToGroupsForm = useForm({
  groups: [],
});

const importForm = useForm({
  file: null,
  group_id: '',
});

const statusColors = {
  active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800',
  unsubscribed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800',
  bounced: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-800',
  pending: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border border-gray-200 dark:border-gray-700',
};

// Safely get a subscriber's display name with sensible fallbacks
function displayName(subscriber) {
  const nameFromParts = [subscriber.first_name, subscriber.last_name]
    .filter(Boolean)
    .join(' ')
    .trim();
  const name = subscriber.full_name || nameFromParts || subscriber.name || '';
  return name || subscriber.email || 'Unknown';
}

// Safely get the first initial for avatar circle
function initialOf(subscriber) {
  const base = displayName(subscriber);
  return base ? base.charAt(0).toUpperCase() : '?';
}

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
    preserveScroll: true,
  });
}

function findExistingSubscriber(email) {
  // Make a request to find the existing subscriber
  axios.get(route('newsletter.subscribers.find-by-email'), { params: { email } })
    .then(response => {
      if (response.data.subscriber) {
        existingSubscriber.value = response.data.subscriber;
        const existingGroups = response.data.subscriber.groups?.map(g => g.id) || [];
        const requestedGroups = Array.isArray(addForm.groups) ? addForm.groups : [];
        addToGroupsForm.groups = [...new Set([...existingGroups, ...requestedGroups])];
        addForm.clearErrors();
        showAddToGroupsModal.value = true;
      }
    })
    .catch(error => {
      console.error('Error finding subscriber:', error);
    });
}

function addToGroups() {
  if (!existingSubscriber.value) return;
  
  addToGroupsForm.post(route('newsletter.subscribers.add-to-groups', existingSubscriber.value.id), {
    onSuccess: () => {
      addToGroupsForm.reset();
      showAddToGroupsModal.value = false;
      showAddModal.value = false;
      existingSubscriber.value = null;
      addForm.reset();
    },
  });
}

function cancelAddToGroups() {
  showAddToGroupsModal.value = false;
  existingSubscriber.value = null;
  addToGroupsForm.reset();
}

function importSubscribers() {
  importForm.post(route('newsletter.subscribers.bulk-import'), {
    preserveScroll: true,
  });
}

function closeImportModal() {
  showImportModal.value = false;
  importForm.reset();
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
    router.delete(route('newsletter.subscribers.bulk-delete'), {
      data: {
        subscriber_ids: selectedSubscribers.value,
      },
      onSuccess: () => {
        selectedSubscribers.value = [];
      },
    });
  }
}

function bulkUpdateStatus(status) {
  router.put(route('newsletter.subscribers.bulk-update-status'), {
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
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight">
            Subscribers
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage your newsletter audience and segments
          </p>
        </div>
        
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('newsletter.groups.index')"
            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition ease-in-out duration-150"
          >
            <UserGroupIcon class="w-4 h-4 mr-2" />
            Groups
          </Link>
          <Link
            :href="route('newsletter.notification-emails.index')"
            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition ease-in-out duration-150"
          >
            <EnvelopeIcon class="w-4 h-4 mr-2" />
            Notifications
          </Link>
          <button
            @click="showImportModal = true"
            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition ease-in-out duration-150"
          >
            <ArrowUpTrayIcon class="w-4 h-4 mr-2" />
            Import
          </button>
          <button
            @click="showAddModal = true"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-sm transition ease-in-out duration-150"
          >
            <PlusIcon class="w-4 h-4 mr-2" />
            Add Subscriber
          </button>
        </div>
      </div>  
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Import Errors Alert -->
        <div v-if="importErrors.length" class="mb-6 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-4 border border-yellow-200 dark:border-yellow-800">
          <div class="flex">
            <div class="flex-shrink-0">
              <ExclamationTriangleIcon class="h-5 w-5 text-yellow-400" aria-hidden="true" />
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                Import completed with {{ importErrors.length }} issue(s)
              </h3>
              <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <ul class="list-disc pl-5 space-y-1 max-h-40 overflow-y-auto">
                  <li v-for="(err, idx) in importErrors" :key="idx">{{ err }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-gray-700">
          
          <!-- Filters and Search -->
          <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
            <div class="flex flex-col lg:flex-row gap-4 justify-between">
              <div class="flex-1 max-w-lg relative">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  v-model="searchForm.search"
                  @keyup.enter="search"
                  type="text"
                  placeholder="Search by name or email..."
                  class="pl-10 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                />
              </div>
              
              <div class="flex flex-wrap gap-2 items-center">
                <div class="flex items-center gap-2">
                    <FunnelIcon class="w-4 h-4 text-gray-400" />
                    <select
                      v-model="searchForm.status"
                      @change="search"
                      class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm text-sm py-2"
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
                      class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm text-sm py-2 max-w-[150px]"
                    >
                      <option value="">All Groups</option>
                      <option value="no_group">No Groups</option>
                      <option v-for="group in groups" :key="group.id" :value="group.id">
                        {{ group.name }}
                      </option>
                    </select>
                </div>

                <div class="h-6 w-px bg-gray-300 dark:bg-gray-700 mx-1 hidden sm:block"></div>

                <select
                  v-model="searchForm.per_page"
                  @change="search"
                  class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm text-sm py-2"
                >
                  <option :value="15">15 / page</option>
                  <option :value="25">25 / page</option>
                  <option :value="50">50 / page</option>
                  <option :value="100">100 / page</option>
                </select>

                <button
                  @click="clearFilters"
                  class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 bg-transparent hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
                  title="Clear Filters"
                >
                  Clear
                </button>
                
                <button
                  @click="exportSubscribers"
                  class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors border border-transparent hover:border-blue-200 dark:hover:border-blue-800"
                  title="Export Subscribers"
                >
                  <ArrowDownTrayIcon class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          <!-- Bulk Actions -->
          <div v-if="showBulkActions" class="px-6 py-3 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-100 dark:border-blue-800 flex items-center justify-between">
            <span class="text-sm font-medium text-blue-700 dark:text-blue-300 flex items-center gap-2">
              <CheckIcon class="w-4 h-4" />
              {{ selectedSubscribers.length }} subscriber(s) selected
            </span>
            <div class="flex gap-2">
              <button
                @click="bulkUpdateStatus('active')"
                class="px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-100 dark:bg-green-900/40 border border-green-200 dark:border-green-800 rounded-md hover:bg-green-200 dark:hover:bg-green-900/60 transition-colors"
              >
                Mark Active
              </button>
              <button
                @click="bulkUpdateStatus('unsubscribed')"
                class="px-3 py-1.5 text-xs font-medium text-orange-700 dark:text-orange-300 bg-orange-100 dark:bg-orange-900/40 border border-orange-200 dark:border-orange-800 rounded-md hover:bg-orange-200 dark:hover:bg-orange-900/60 transition-colors"
              >
                Unsubscribe
              </button>
              <button
                @click="bulkDelete"
                class="px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-md hover:bg-red-200 dark:hover:bg-red-900/60 transition-colors"
              >
                Delete
              </button>
            </div>
          </div>

          <!-- Subscribers Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left w-12">
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
                    Subscribed At
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="subscriber in subscribers.data" :key="subscriber.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
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
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-800 flex items-center justify-center border border-blue-200 dark:border-blue-700">
                          <span class="text-sm font-bold text-blue-700 dark:text-blue-300">
                            {{ initialOf(subscriber) }}
                          </span>
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                          {{ displayName(subscriber) }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                          {{ subscriber.email }}
                        </div>
                        <div v-if="subscriber.organization" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                          {{ subscriber.organization }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <span :class="`inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full ${statusColors[subscriber.status]}`">
                      {{ subscriber.status }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1.5">
                      <template v-if="subscriber.groups && subscriber.groups.length > 0">
                        <span
                          v-for="group in subscriber.groups"
                          :key="group.id"
                          class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border"
                          :style="{
                             backgroundColor: (group.color || '#3b82f6') + '20',
                             color: group.color || '#3b82f6',
                             borderColor: (group.color || '#3b82f6') + '40'
                          }"
                        >
                          {{ group.name }}
                        </span>
                      </template>
                      <span v-else class="text-xs text-gray-400 italic">
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
                        class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                        title="View Details"
                      >
                        <EyeIcon class="w-5 h-5" />
                      </Link>
                      <button
                        @click="deleteSubscriber(subscriber)"
                        class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                        title="Delete"
                      >
                        <TrashIcon class="w-5 h-5" />
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="subscribers.data.length === 0">
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center justify-center">
                            <UserGroupIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                            <p class="text-lg font-medium">No subscribers found</p>
                            <p class="text-sm mt-1">Try adjusting your search or filters</p>
                        </div>
                    </td>
                </tr>
              </tbody>
            </table>
          </div>

        <!-- Pagination -->
        <div v-if="subscribers.links && subscribers.data.length > 0" class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
              <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing {{ subscribers.from }} to {{ subscribers.to }} of {{ subscribers.total }} results
              </div>
              <div class="flex gap-1">
                <component
                  v-for="(link, i) in subscribers.links"
                  :key="i"
                  :is="link.url ? 'Link' : 'span'"
                  :href="link.url"
                  v-html="link.label"
                  :class="`px-3 py-1.5 text-sm border rounded-md transition-colors duration-200 ${
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
    <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddModal = false" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
          <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 sm:mx-0 sm:h-10 sm:w-10">
                <PlusIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" aria-hidden="true" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                  Add New Subscriber
                </h3>
                <div class="mt-4">
                    <form @submit.prevent="addSubscriber" class="space-y-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                          <input
                            v-model="addForm.email"
                            @blur="findExistingSubscriber(addForm.email)"
                            type="email"
                            required
                            :class="`mt-1 block w-full rounded-lg shadow-sm ${
                              addFormErrors.email
                                ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600'
                            }`"
                          />
                          <p v-if="addFormErrors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ addFormErrors.email }}
                          </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                          <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                            <input
                              v-model="addForm.first_name"
                              type="text"
                              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                            />
                          </div>
                          <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                            <input
                              v-model="addForm.last_name"
                              type="text"
                              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                            />
                          </div>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                          <input
                            v-model="addForm.organization"
                            type="text"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                          />
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                          <select
                            v-model="addForm.status"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                          >
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                          </select>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Groups</label>
                          <div class="mt-2 space-y-2 max-h-32 overflow-y-auto p-2 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <label v-for="group in groups" :key="group.id" class="flex items-center hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded cursor-pointer">
                              <input
                                v-model="addForm.groups"
                                :value="group.id"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                              />
                              <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ group.name }}</span>
                            </label>
                          </div>
                          <p v-if="addFormErrors.groups" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ addFormErrors.groups }}
                          </p>
                        </div>
                    </form>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="addSubscriber"
              :disabled="addForm.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ addForm.processing ? 'Adding...' : 'Add Subscriber' }}
            </button>
            <button
              type="button"
              @click="showAddModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add to Groups Modal for Existing Subscriber -->
    <div v-if="showAddToGroupsModal && existingSubscriber" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="cancelAddToGroups"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
          <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Add Existing Subscriber to Groups
              </h3>
              
              <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                <div class="text-sm text-blue-800 dark:text-blue-200">
                  <p class="font-medium">{{ existingSubscriber.email }}</p>
                  <p v-if="existingSubscriber.first_name || existingSubscriber.last_name" class="text-xs mt-1">
                    {{ displayName(existingSubscriber) }}
                  </p>
                  <p class="text-xs mt-2">This subscriber already exists. You can add them to additional groups.</p>
                </div>
              </div>
              
              <form @submit.prevent="addToGroups" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Groups</label>
                  <div class="mt-2 space-y-2 max-h-40 overflow-y-auto p-2 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <label v-for="group in groups" :key="group.id" class="flex items-center hover:bg-gray-50 dark:hover:bg-gray-700 p-1 rounded cursor-pointer">
                      <input
                        v-model="addToGroupsForm.groups"
                        :value="group.id"
                        type="checkbox"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                      />
                      <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ group.name }}</span>
                    </label>
                  </div>
                </div>
              </form>
          </div>
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
             <button
                type="button"
                @click="addToGroups"
                :disabled="addToGroupsForm.processing"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ addToGroupsForm.processing ? 'Adding...' : 'Add to Groups' }}
              </button>
              <button
                type="button"
                @click="cancelAddToGroups"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
              >
                Cancel
              </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showImportModal = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-xl shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
          <!-- Header -->
          <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 text-left">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                  Import Subscribers
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  Upload a CSV file to add multiple subscribers at once
                </p>
              </div>
              <button
                @click="closeImportModal"
                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
              >
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>
          </div>
          
          <!-- Content -->
          <div class="px-6 py-6 text-left">
            <!-- Import Report -->
            <div v-if="importReport" class="mb-6 space-y-4">
              <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <p class="text-base font-semibold text-blue-900 dark:text-blue-200 mb-3 flex items-center gap-2">
                  <CheckIcon class="w-5 h-5" />
                  Import Summary
                </p>
                <div class="grid grid-cols-2 gap-3">
                  <div v-if="importReport.imported > 0" class="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                    <span class="flex items-center justify-center w-6 h-6 bg-green-100 dark:bg-green-900/30 rounded-full text-green-600 dark:text-green-400">✓</span>
                    <span>{{ importReport.imported }} new subscriber(s)</span>
                  </div>
                  <div v-if="importReport.existing_added_to_group > 0" class="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                    <span class="flex items-center justify-center w-6 h-6 bg-green-100 dark:bg-green-900/30 rounded-full text-green-600 dark:text-green-400">✓</span>
                    <span>{{ importReport.existing_added_to_group }} existing added to group</span>
                  </div>
                  <div v-if="importReport.existing_no_change > 0" class="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                    <span class="flex items-center justify-center w-6 h-6 bg-yellow-100 dark:bg-yellow-900/30 rounded-full text-yellow-600 dark:text-yellow-400">⚠</span>
                    <span>{{ importReport.existing_no_change }} already existing</span>
                  </div>
                </div>
              </div>
              
              <div class="space-y-4">
                <!-- Successfully Processed Emails -->
                <div v-if="(importReport.added_emails && importReport.added_emails.length > 0) || (importReport.existing_added_emails && importReport.existing_added_emails.length > 0)" class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                  <p class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2 flex items-center gap-2">
                    <CheckIcon class="w-4 h-4" />
                    Successfully Processed
                  </p>
                  <div class="max-h-40 overflow-y-auto">
                    <ul class="text-xs text-green-700 dark:text-green-300 space-y-1">
                      <li v-for="email in [...(importReport.added_emails || []), ...(importReport.existing_added_emails || [])]" :key="email" class="truncate">{{ email }}</li>
                    </ul>
                  </div>
                </div>
                
                <!-- Already Existing (No Change) -->
                <div v-if="importReport.existing_no_change_emails && importReport.existing_no_change_emails.length > 0" class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                  <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2 flex items-center gap-2">
                    <XMarkIcon class="w-4 h-4" />
                    Already Existing (No Changes)
                  </p>
                  <div class="max-h-40 overflow-y-auto">
                    <ul class="text-xs text-yellow-700 dark:text-yellow-300 space-y-1">
                      <li v-for="email in importReport.existing_no_change_emails" :key="email" class="truncate">{{ email }}</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Import Form -->
            <form v-if="!importReport" @submit.prevent="importSubscribers" class="space-y-5">
              <!-- Template Download -->
              <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex items-start gap-3">
                  <ArrowDownTrayIcon class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                  <div class="flex-1">
                    <p class="text-sm font-medium text-blue-900 dark:text-blue-200">
                      Need a template?
                    </p>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                      Download our CSV template with the correct format and an example row
                    </p>
                    <a
                      :href="route('newsletter.subscribers.import-template')"
                      class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                    >
                      <ArrowDownTrayIcon class="w-4 h-4" />
                      Download Template
                    </a>
                  </div>
                </div>
              </div>

              <!-- File Upload -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Upload CSV File
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg hover:border-blue-400 dark:hover:border-blue-500 transition-colors">
                  <div class="space-y-1 text-center">
                    <ArrowUpTrayIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <div class="flex text-sm text-gray-600 dark:text-gray-400">
                      <label class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                        <span class="px-2">Upload a file</span>
                        <input
                          @input="importForm.file = $event.target.files[0]"
                          type="file"
                          accept=".csv,.txt"
                          required
                          class="sr-only"
                        />
                      </label>
                      <p class="pl-1">or drag and drop</p>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      CSV format: Email, First Name, Last Name, Organization
                    </p>
                  </div>
                </div>
                <p v-if="importForm.file" class="mt-2 text-sm text-green-600 dark:text-green-400">
                  Selected: {{ importForm.file.name }}
                </p>
              </div>

              <!-- Group Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Add to Group (Optional)
                </label>
                <select
                  v-model="importForm.group_id"
                  class="block w-full px-4 py-2.5 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                >
                  <option value="">No group</option>
                  <option v-for="group in groups" :key="group.id" :value="group.id">
                    {{ group.name }}
                  </option>
                </select>
              </div>
            </form>
          </div>
          
          <!-- Footer -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">
            <div class="flex justify-end gap-3">
              <button
                type="button"
                @click="closeImportModal"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                {{ importReport ? 'Close' : 'Cancel' }}
              </button>
              <button
                v-if="!importReport"
                @click="importSubscribers"
                :disabled="importForm.processing || !importForm.file"
                class="px-5 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <span v-if="importForm.processing" class="flex items-center gap-2">
                  <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Importing...
                </span>
                <span v-else class="flex items-center gap-2">
                  <ArrowUpTrayIcon class="w-4 h-4" />
                  Import Subscribers
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>