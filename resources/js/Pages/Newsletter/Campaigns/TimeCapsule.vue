<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  TrashIcon,
  ChartBarIcon,
  ArrowUturnLeftIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  campaigns: Object,
  filters: Object,
});

const searchForm = useForm({
  search: props.filters.search || '',
  status: props.filters.status || '',
});

// Time Capsule functionality
const selectedCampaigns = ref([]);
const showRestoreButton = computed(() => selectedCampaigns.value.length > 0);

const statusColors = {
  draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
  scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  sending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  sent: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  paused: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

function search() {
  searchForm.get(route('newsletter.campaigns.timecapsule'), {
    preserveState: true,
    replace: true,
  });
}

function clearFilters() {
  searchForm.reset();
  search();
}

function restoreCampaign(campaign) {
  if (confirm(`Are you sure you want to restore "${campaign.name}" from the Time Capsule?`)) {
    router.post(route('newsletter.campaigns.timecapsule.restore', campaign.id));
  }
}

function deleteCampaign(campaign) {
  if (confirm(`Are you sure you want to permanently delete "${campaign.name}"? This action cannot be undone.`)) {
    router.delete(route('newsletter.campaigns.destroy', campaign.id));
  }
}

function formatDate(dateString) {
  if (!dateString) return '-';
  let input = String(dateString);
  const sqlNoTz = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
  if (sqlNoTz.test(input)) {
    input = input.replace(' ', 'T') + 'Z';
  }
  const d = new Date(input);
  if (isNaN(d.getTime())) return dateString;
  return d.toLocaleString();
}

function getProgressPercentage(campaign) {
  if (campaign.total_recipients === 0) return 0;
  return Math.round((campaign.sent_count / campaign.total_recipients) * 100);
}

function toggleCampaignSelection(campaignId) {
  const index = selectedCampaigns.value.indexOf(campaignId);
  if (index > -1) {
    selectedCampaigns.value.splice(index, 1);
  } else {
    selectedCampaigns.value.push(campaignId);
  }
}

function restoreSelectedCampaigns() {
  if (selectedCampaigns.value.length === 0) return;
  
  const campaignCount = selectedCampaigns.value.length;
  const message = campaignCount === 1 
    ? 'Are you sure you want to restore this campaign from the Time Capsule?' 
    : `Are you sure you want to restore ${campaignCount} campaigns from the Time Capsule?`;
    
  if (confirm(message)) {
    selectedCampaigns.value.forEach(campaignId => {
      router.post(route('newsletter.campaigns.timecapsule.restore', campaignId), {}, {
        preserveState: true,
        onSuccess: () => {
          selectedCampaigns.value = [];
        }
      });
    });
  }
}
</script>

<template>
  <Head title="Time Capsule - Newsletter Campaigns" />
  <AuthenticatedLayout>
    <template #header>
      
    </template>

    <div class="">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Time Capsule</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Archived campaigns that are hidden from regular views and public archive
            </p>
          </div>
          <div>
            <button
              v-if="showRestoreButton"
              @click="restoreSelectedCampaigns"
              class="inline-flex items-center px-4 py-2 mb-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
              <ArrowUturnLeftIcon class="w-4 h-4 mr-2" />
              Restore ({{ selectedCampaigns.length }})
            </button>
          </div>
        </div>

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
                    placeholder="Search archived campaigns..."
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
                  <option value="draft">Draft</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="sending">Sending</option>
                  <option value="sent">Sent</option>
                  <option value="paused">Paused</option>
                  <option value="cancelled">Cancelled</option>
                </select>

                <button
                  @click="clearFilters"
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  Clear
                </button>
              </div>
            </div>
          </div>

          <!-- Campaigns Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">
                    <span class="sr-only">Select</span>
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Campaign
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Recipients
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Performance
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Date
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="campaign in campaigns.data" :key="campaign.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4">
                    <input
                      type="checkbox"
                      :checked="selectedCampaigns.includes(campaign.id)"
                      @change="toggleCampaignSelection(campaign.id)"
                      class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                    />
                  </td>
                  <td class="px-6 py-4">
                    <div>
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ campaign.name }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ campaign.subject }}
                      </div>
                      <div v-if="campaign.template" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Template: {{ campaign.template.name }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-col gap-2">
                      <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusColors[campaign.status]}`">
                        {{ campaign.status }}
                      </span>
                      <div v-if="campaign.status === 'sending' && campaign.total_recipients > 0" class="w-full bg-gray-200 rounded-full h-2">
                        <div 
                          class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                          :style="`width: ${getProgressPercentage(campaign)}%`"
                        ></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 dark:text-gray-100">
                      {{ campaign.total_recipients.toLocaleString() }}
                    </div>
                    <div v-if="campaign.sent_count > 0" class="text-xs text-gray-500 dark:text-gray-400">
                      {{ campaign.sent_count.toLocaleString() }} sent
                      <span v-if="campaign.failed_count > 0" class="text-red-600">
                        • {{ campaign.failed_count }} failed
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div v-if="campaign.status === 'sent'" class="text-sm">
                      <div class="text-gray-900 dark:text-gray-100">
                        {{ campaign.open_rate }}% opened
                      </div>
                      <div class="text-gray-500 dark:text-gray-400">
                        {{ campaign.click_rate }}% clicked
                      </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                      -
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                    <div v-if="campaign.sent_at">
                      Sent: {{ formatDate(campaign.sent_at) }}
                    </div>
                    <div v-else-if="campaign.scheduled_at">
                      Scheduled: {{ formatDate(campaign.scheduled_at) }}
                    </div>
                    <div v-else>
                      Created: {{ formatDate(campaign.created_at) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right text-sm font-medium">
                    <div class="flex justify-end gap-2">
                      <!-- View/Analytics -->
                      <Link
                        :href="route('newsletter.campaigns.show', campaign.id)"
                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                        title="View Details"
                      >
                        <EyeIcon class="w-4 h-4" />
                      </Link>

                      <!-- Restore -->
                      <button
                        @click="restoreCampaign(campaign)"
                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                        title="Restore from Time Capsule"
                      >
                        <ArrowUturnLeftIcon class="w-4 h-4" />
                      </button>

                      <!-- Delete Permanently -->
                      <button
                        @click="deleteCampaign(campaign)"
                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                        title="Delete Permanently"
                      >
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty State -->
          <div v-if="campaigns.data.length === 0" class="text-center py-12">
            <ChartBarIcon class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
              No archived campaigns found
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">
              Campaigns moved to the Time Capsule will appear here
            </p>
            <Link
              :href="route('newsletter.campaigns.index')"
              class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
              View Active Campaigns
            </Link>
          </div>

          <!-- Pagination -->
          <div v-if="campaigns.links" class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ campaigns.from }} to {{ campaigns.to }} of {{ campaigns.total }} results
              </div>
              <div class="flex gap-1">
                <Link
                  v-for="link in campaigns.links"
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
  </AuthenticatedLayout>
</template>
