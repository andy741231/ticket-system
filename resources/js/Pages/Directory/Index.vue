<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import OrganizationChartExport from '@/Components/Directory/OrganizationChartExport.vue';
import { useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
  teams: Array,
  query: String,
  group: String,
  program: String,
  availablePrograms: Array,
  availableGroups: Array,
  availableLogos: Array,
});

const searchForm = useForm({
  query: props.query,
  group: props.group ?? 'default',
  program: props.program ?? '',
});

// Only Directory admins can add staff
// Require the explicit admin-level permission
const isDirectoryAdmin = useHasAny([
  'directory.profile.manage',
]);

const search = () => {
    searchForm.get(route('directory.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

let debounceTimer = null;
watch(() => searchForm.query, () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(search, 300);
});

// Trigger search when group changes
watch(() => searchForm.group, () => {
  search();
});

// Trigger search when program changes
watch(() => searchForm.program, () => {
  search();
});

// Export modal state
const showExportModal = ref(false);

const getDisplayTitle = (team, selectedProgram) => {
  if (!selectedProgram) {
    return team.title;
  }

  if (!team.affiliate_programs || team.affiliate_programs.length === 0) {
    return team.title;
  }

  const matchedAffiliate = team.affiliate_programs.find(
    (affiliate) => affiliate.program === selectedProgram,
  );

  return matchedAffiliate ? matchedAffiliate.title : team.title;
};
</script>

<template>
  <Head title="Directory" />
  <AuthenticatedLayout>
    <template #header></template>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="flex justify-between items-center mb-4">
          <div>
            <SecondaryButton @click="showExportModal = true">
              <font-awesome-icon icon="download" class="h-5 w-5 mr-1" />
              Export as Image
            </SecondaryButton>
          </div>
          <Link v-if="isDirectoryAdmin" :href="route('directory.create')" as="button">
              <PrimaryButton>
                  <font-awesome-icon icon="plus" class="h-5 w-5 mr-1" />
                  Add Team Member
              </PrimaryButton>
          </Link>
       </div>
      <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
        <div class="flex items-center space-x-3">
          <input
            v-model="searchForm.query"
            type="text"
            placeholder="Search people..."
            class="w-full rounded-md border-gray-300 focus:border-uh-teal focus:ring-uh-teal
                   dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
          <select
            v-model="searchForm.group"
            class="rounded-md border-gray-300 focus:border-uh-teal focus:ring-uh-teal dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            title="Filter by Group"
          >
            <option value="default">UHPH Team</option>
            <option value="">All</option>
            <option
              v-for="groupOption in availableGroups"
              :key="groupOption"
              :value="groupOption"
            >
              {{ groupOption }}
            </option>
          </select>
          <select
            v-model="searchForm.program"
            class="rounded-md border-gray-300 focus:border-uh-teal focus:ring-uh-teal dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            title="Filter by Program"
          >
            <option value="">All Programs</option>
            <option v-for="prog in availablePrograms" :key="prog" :value="prog">
              {{ prog }}
            </option>
          </select>
        </div>
        <div class="mt-6">
          <div v-if="teams.length" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 auto-rows-fr">
            <div v-for="team in teams" :key="team.id" class="flex">
              <div class="block bg-white dark:bg-gray-700 rounded-lg shadow overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col w-full">
                <div class="text-center flex-1 flex flex-col">
                  <div class="h-48 bg-white dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                    <Link class="rounded-full" :href="route('directory.show', team.id)">
                      <img v-if="team.img" 
                           style="width:190px; height:190px;" 
                           class="h-full w-full mt-5 p-2 rounded-full object-cover" 
                           :src="team.img" 
                           :alt="team.name">
                      <svg v-else 
                           class="w-16 h-16 text-gray-400" 
                           fill="none" 
                           stroke="currentColor" 
                           viewBox="0 0 24 24" 
                           xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" 
                              stroke-linejoin="round" 
                              stroke-width="2" 
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                        </path>
                      </svg>
                    </Link>
                  </div>
                  <div class="p-4 flex-grow flex flex-col justify-center">
                    <Link :href="route('directory.show', team.id)">
                      <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                        {{ team.name }} 
                        <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ team.degree }}</span>
                      </h3>
                    </Link>
                    <p class="text-sm text-uh-teal dark:text-uh-teal-light mt-1">
                      {{ getDisplayTitle(team, searchForm.program) }}
                    </p>
                    <div class="flex justify-center space-x-4 mt-2">
                      <a :href="`mailto:${team.email}`" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                      </a>
                      <a :href="`https://teams.microsoft.com/l/chat/0/0?users=${team.email}`" target="_blank" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                      </a>
                      <a :href="`https://teams.microsoft.com/l/call/0/0?users=${team.email}`" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-gray-600 dark:text-gray-300">
            No results found.
          </div>
        </div>
      </div>
    </div>
    
    <!-- Export Modal -->
    <OrganizationChartExport
      :show="showExportModal"
      :teams="teams"
      :current-filter="group"
      :available-logos="availableLogos"
      @close="showExportModal = false"
    />
  </AuthenticatedLayout>
</template>