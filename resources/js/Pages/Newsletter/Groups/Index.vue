<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { 
  ArrowLeftIcon,
  PlusIcon,
  UserGroupIcon,
  TrashIcon,
  EyeIcon,
  TagIcon,
  SwatchIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  groups: Array,
});

const createForm = useForm({
  name: '',
  description: '',
  color: '#C8102E',
  is_active: true,
});

function createGroup() {
  createForm.post(route('newsletter.groups.store'));
}

function deleteGroup(group) {
  if (confirm(`Delete group "${group.name}"?`)) {
    router.delete(route('newsletter.groups.destroy', group.id));
  }
}
</script>

<template>
  <Head title="Newsletter Groups" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Link 
            :href="route('newsletter.subscribers.index')"
            class="inline-flex items-center p-2 text-uh-gray hover:text-uh-slate bg-uh-cream/50 hover:bg-uh-cream rounded-lg transition-colors duration-200"
          >
            <ArrowLeftIcon class="w-5 h-5" />
          </Link>
          <div>
            <h2 class="text-2xl font-bold text-uh-slate dark:text-uh-white leading-tight">
              Newsletter Groups
            </h2>
            <p class="mt-1 text-sm text-uh-gray dark:text-uh-cream/70">
              Organize subscribers into targeted groups for campaigns
            </p>
          </div>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Create Group Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-white flex items-center gap-2">
                <PlusIcon class="w-5 h-5" />
                Create New Group
              </h3>
            </div>
            <div class="p-6">
              <form @submit.prevent="createGroup" class="space-y-5">
                <div>
                  <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Group Name</label>
                  <input 
                    v-model="createForm.name" 
                    type="text" 
                    required
                    placeholder="e.g., Newsletter Subscribers"
                    class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Description</label>
                  <textarea 
                    v-model="createForm.description" 
                    rows="3"
                    placeholder="Describe the purpose of this group..."
                    class="block w-full px-4 py-3 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate resize-none"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-3 flex items-center gap-2">
                    <SwatchIcon class="w-4 h-4" />
                    Group Color
                  </label>
                  <div class="flex items-center gap-3">
                    <input 
                      v-model="createForm.color" 
                      type="color" 
                      class="h-11 w-16 border border-uh-gray/20 dark:border-gray-600 rounded-lg cursor-pointer"
                    />
                    <input 
                      v-model="createForm.color" 
                      type="text" 
                      class="flex-1 h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                    />
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <input 
                    id="is_active" 
                    v-model="createForm.is_active" 
                    type="checkbox" 
                    class="rounded border-uh-gray/30 text-uh-red shadow-sm focus:ring-uh-red/20"
                  />
                  <label for="is_active" class="text-sm font-medium text-uh-slate dark:text-gray-300">Active Group</label>
                </div>
                <div class="pt-2">
                  <button 
                    type="submit" 
                    :disabled="createForm.processing"
                    class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-uh-red hover:bg-uh-brick text-white font-medium rounded-lg transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <PlusIcon class="w-4 h-4 mr-2" />
                    Create Group
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- Groups List -->
          <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
              <div class="px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-white flex items-center gap-2">
                    <UserGroupIcon class="w-5 h-5" />
                    All Groups ({{ groups.length }})
                  </h3>
                </div>
              </div>
              
              <div class="p-6">
                <div v-if="groups.length" class="grid grid-cols-1 gap-4">
                  <div 
                    v-for="g in groups" 
                    :key="g.id" 
                    class="group border border-uh-gray/10 dark:border-gray-600 rounded-lg p-5 hover:shadow-md hover:border-uh-red/20 transition-all duration-200"
                  >
                    <div class="flex items-start justify-between">
                      <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                          <div 
                            class="w-4 h-4 rounded-full border-2 border-white shadow-sm" 
                            :style="{ backgroundColor: g.color || '#C8102E' }"
                          ></div>
                          <Link 
                            :href="route('newsletter.groups.show', g.id)" 
                            class="text-lg font-semibold text-uh-slate dark:text-uh-white hover:text-uh-red transition-colors duration-200"
                          >
                            {{ g.name }}
                          </Link>
                          <span 
                            v-if="!g.is_active" 
                            class="inline-flex px-2 py-0.5 text-xs font-medium bg-uh-gray/10 text-uh-gray border border-uh-gray/20 rounded-full"
                          >
                            Inactive
                          </span>
                        </div>
                        <p 
                          v-if="g.description" 
                          class="text-sm text-uh-gray dark:text-gray-400 mb-3 line-clamp-2"
                        >
                          {{ g.description }}
                        </p>
                        <div class="flex items-center gap-4 text-xs text-uh-gray dark:text-gray-400">
                          <span class="flex items-center gap-1">
                            <UserGroupIcon class="w-3 h-3" />
                            {{ g.subscribers_count || 0 }} subscribers
                          </span>
                          <span v-if="g.created_at">
                            Created {{ new Date(g.created_at).toLocaleDateString() }}
                          </span>
                        </div>
                      </div>
                      
                      <!-- Actions -->
                      <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <Link 
                          :href="route('newsletter.groups.show', g.id)" 
                          class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-uh-teal hover:text-uh-green bg-uh-teal/10 hover:bg-uh-teal/20 border border-uh-teal/20 rounded-lg transition-colors duration-200"
                        >
                          <EyeIcon class="w-3 h-3 mr-1" />
                          View
                        </Link>
                        <button 
                          @click="deleteGroup(g)" 
                          class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-uh-red hover:text-uh-brick bg-uh-red/10 hover:bg-uh-red/20 border border-uh-red/20 rounded-lg transition-colors duration-200"
                        >
                          <TrashIcon class="w-3 h-3 mr-1" />
                          Delete
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Empty State -->
                <div v-else class="text-center py-12">
                  <div class="w-16 h-16 mx-auto mb-4 bg-uh-gray/10 rounded-full flex items-center justify-center">
                    <UserGroupIcon class="w-8 h-8 text-uh-gray" />
                  </div>
                  <h3 class="text-lg font-medium text-uh-slate dark:text-uh-white mb-2">No groups yet</h3>
                  <p class="text-uh-gray dark:text-gray-400 mb-4">Create your first group to organize subscribers</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
