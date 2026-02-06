<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
  PlusIcon,
  UserGroupIcon,
  TrashIcon,
  EyeIcon,
  TagIcon,
  SwatchIcon,
  XMarkIcon,
  ArrowPathIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  groups: Array,
});

const showCreateModal = ref(false);
const showMergeModal = ref(false);

const createForm = useForm({
  name: '',
  description: '',
  color: '#3b82f6', // Default blue-500
  is_active: true,
  is_external: false,
});

const mergeForm = useForm({
  target_group_id: '',
  source_group_ids: [],
});

function createGroup() {
  createForm.post(route('newsletter.groups.store'), {
    onSuccess: () => {
      createForm.reset();
      showCreateModal.value = false;
    },
  });
}

function deleteGroup(group) {
  if (confirm(`Are you sure you want to delete the group "${group.name}"? This will remove the group but not delete the subscribers.`)) {
    router.delete(route('newsletter.groups.destroy', group.id));
  }
}

function toggleSourceGroup(groupId) {
  // If checking a box, ensure we handle the array correctly
  if (mergeForm.source_group_ids.includes(groupId)) {
    mergeForm.source_group_ids = mergeForm.source_group_ids.filter(id => id !== groupId);
  } else {
    mergeForm.source_group_ids = [...mergeForm.source_group_ids, groupId];
  }
}

function mergeGroups() {
  mergeForm.post(route('newsletter.groups.merge'), {
    onSuccess: () => {
      mergeForm.reset();
      showMergeModal.value = false;
    },
  });
}
</script>

<template>
  <Head title="Newsletter Groups" />
  <AuthenticatedLayout>
    <template #header>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white leading-tight">
            Subscriber Groups
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Organize your audience into segments for targeted campaigns
          </p>
        </div>
        <div class="flex gap-2">
          <button
            @click="showMergeModal = true"
            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition ease-in-out duration-150"
          >
            <ArrowPathIcon class="w-4 h-4 mr-2" />
            Merge Groups
          </button>
          <button
            @click="showCreateModal = true"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-sm transition ease-in-out duration-150"
          >
            <PlusIcon class="w-4 h-4 mr-2" />
            Create Group
          </button>
        </div>
      </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Groups Grid -->
        <div v-if="groups.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <div 
            v-for="group in groups" 
            :key="group.id" 
            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-shadow duration-200"
          >
            <!-- Card Header (Color Strip) -->
            <div class="h-2 w-full" :style="{ backgroundColor: group.color || '#3b82f6' }"></div>
            
            <div class="p-5">
              <div class="flex justify-between items-start mb-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate" :title="group.name">
                  {{ group.name }}
                </h3>
                <div class="flex gap-1">
                   <!-- External Badge -->
                   <span v-if="group.is_external" title="Visible publicly" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                    Ext
                  </span>
                  <!-- Inactive Badge -->
                  <span v-if="!group.is_active" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    Inactive
                  </span>
                </div>
              </div>
              
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 h-10 line-clamp-2">
                {{ group.description || 'No description provided.' }}
              </p>
              
              <div class="flex items-center text-sm text-gray-600 dark:text-gray-300 mb-4">
                <UserGroupIcon class="w-4 h-4 mr-1.5 text-gray-400" />
                <span class="font-medium">{{ group.subscribers_count || 0 }}</span>
                <span class="ml-1">subscribers</span>
              </div>
              
              <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                 <span class="text-xs text-gray-400">
                    {{ new Date(group.created_at).toLocaleDateString() }}
                 </span>
                 <div class="flex gap-2">
                    <Link
                      :href="route('newsletter.groups.show', group.id)"
                      class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium"
                    >
                      View
                    </Link>
                    <button
                      @click="deleteGroup(group)"
                      class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium"
                    >
                      Delete
                    </button>
                 </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
          <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
            <UserGroupIcon class="w-8 h-8 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No groups yet</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
            Create your first group to start organizing your newsletter subscribers into segments.
          </p>
          <button
            @click="showCreateModal = true"
            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 shadow-sm transition ease-in-out duration-150"
          >
            <PlusIcon class="w-4 h-4 mr-2" />
            Create Group
          </button>
        </div>

      </div>
    </div>

    <!-- Create Group Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
          <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 sm:mx-0 sm:h-10 sm:w-10">
                <PlusIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" aria-hidden="true" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                  Create New Group
                </h3>
                <div class="mt-4">
                    <form @submit.prevent="createGroup" class="space-y-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group Name</label>
                          <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="e.g. Monthly Newsletter"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                          />
                          <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ createForm.errors.name }}
                          </p>
                        </div>
                        
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                          <textarea
                            v-model="createForm.description"
                            rows="3"
                            placeholder="What is this group for?"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm resize-none"
                          ></textarea>
                        </div>

                        <div>
                           <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                           <div class="mt-1 flex items-center gap-2">
                             <input
                               v-model="createForm.color"
                               type="color"
                               class="h-9 w-14 border border-gray-300 dark:border-gray-600 rounded cursor-pointer p-0.5 bg-white dark:bg-gray-700"
                             />
                             <input
                               v-model="createForm.color"
                               type="text"
                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                             />
                           </div>
                        </div>

                        <div class="space-y-3 pt-2">
                            <div class="flex items-start">
                              <div class="flex items-center h-5">
                                <input
                                  id="is_active"
                                  v-model="createForm.is_active"
                                  type="checkbox"
                                  class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded"
                                />
                              </div>
                              <div class="ml-3 text-sm">
                                <label for="is_active" class="font-medium text-gray-700 dark:text-gray-300">Active</label>
                                <p class="text-gray-500 dark:text-gray-400">Available for sending campaigns.</p>
                              </div>
                            </div>
                            
                            <div class="flex items-start">
                              <div class="flex items-center h-5">
                                <input
                                  id="is_external"
                                  v-model="createForm.is_external"
                                  type="checkbox"
                                  class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded"
                                />
                              </div>
                              <div class="ml-3 text-sm">
                                <label for="is_external" class="font-medium text-gray-700 dark:text-gray-300">Publicly Visible</label>
                                <p class="text-gray-500 dark:text-gray-400">Allow subscribers to join this group from their preferences page.</p>
                              </div>
                            </div>
                        </div>
                    </form>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="createGroup"
              :disabled="createForm.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ createForm.processing ? 'Creating...' : 'Create Group' }}
            </button>
            <button
              type="button"
              @click="showCreateModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Merge Groups Modal -->
    <div v-if="showMergeModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showMergeModal = false" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
          <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 dark:bg-orange-900 sm:mx-0 sm:h-10 sm:w-10">
                <ArrowPathIcon class="h-6 w-6 text-orange-600 dark:text-orange-400" aria-hidden="true" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                  Merge Groups
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Consolidate multiple groups into one. Source groups will be deleted after their subscribers are moved.
                  </p>
                </div>
                <div class="mt-4">
                    <form @submit.prevent="mergeGroups" class="space-y-4">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Group</label>
                          <select
                            v-model="mergeForm.target_group_id"
                            required
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-lg shadow-sm"
                          >
                            <option value="" disabled>Select destination group</option>
                            <option v-for="g in groups" :key="`target-${g.id}`" :value="g.id">
                              {{ g.name }}
                            </option>
                          </select>
                          <p v-if="mergeForm.errors.target_group_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ mergeForm.errors.target_group_id }}
                          </p>
                        </div>

                        <div>
                          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Source Groups (to delete)</label>
                          <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 max-h-48 overflow-y-auto bg-gray-50 dark:bg-gray-900/50">
                            <div v-if="groups.length <= 1" class="text-sm text-gray-500 text-center py-2">
                              Not enough groups to merge.
                            </div>
                            <div v-else class="space-y-2">
                                <label
                                  v-for="g in groups"
                                  :key="`source-${g.id}`"
                                  class="flex items-center gap-3 p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                                  :class="{ 'opacity-50 cursor-not-allowed': g.id === mergeForm.target_group_id }"
                                >
                                  <input
                                    type="checkbox"
                                    :value="g.id"
                                    :checked="mergeForm.source_group_ids.includes(g.id)"
                                    :disabled="g.id === mergeForm.target_group_id"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 disabled:opacity-50"
                                    @change="toggleSourceGroup(g.id)"
                                  />
                                  <span class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: g.color || '#3b82f6' }"></span>
                                    <span :class="{ 'line-through': mergeForm.source_group_ids.includes(g.id) }">{{ g.name }}</span>
                                  </span>
                                </label>
                            </div>
                          </div>
                          <p v-if="mergeForm.errors.source_group_ids" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ mergeForm.errors.source_group_ids }}
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
              @click="mergeGroups"
              :disabled="mergeForm.processing"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
            >
              {{ mergeForm.processing ? 'Merging...' : 'Merge Groups' }}
            </button>
            <button
              type="button"
              @click="showMergeModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>