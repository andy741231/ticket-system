<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
  MagnifyingGlassIcon,
  PlusIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  PlayIcon,
  PauseIcon,
  StopIcon,
  DocumentDuplicateIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  campaigns: Object,
  filters: Object,
});

const searchForm = useForm({
  search: props.filters.search || '',
  status: props.filters.status || 'sent',
  per_page: Number(props.filters.per_page) || 25,
});

// Per-page options
const perPageOptions = [25, 50, 100, 150, 200, 250];

// Selection state
const selectedCampaigns = ref([]); // array of campaign IDs
const lastSelectedIndex = ref(null); // index within current page for shift-range selection

// Helpers derived from current page data
const idsOnPage = computed(() => props.campaigns?.data?.map(c => c.id) ?? []);
const statusById = computed(() => {
  const map = new Map();
  (props.campaigns?.data ?? []).forEach(c => map.set(c.id, c.status));
  return map;
});
const isSent = (id) => statusById.value.get(id) === 'sent';
const isDeletable = (id) => {
  const s = statusById.value.get(id);
  return s !== 'sent' && s !== 'sending';
};

// Current view helpers
const isSentView = computed(() => (searchForm.status || 'sent') === 'sent');
const isInProgressView = computed(() => (searchForm.status || 'sent') === 'in_progress');
// Subset for deletable items in current selection
const selectedDeletableIds = computed(() => selectedCampaigns.value.filter(id => isDeletable(id)));

// Select All (this page)
const allOnPageSelected = computed(() => idsOnPage.value.length > 0 && idsOnPage.value.every(id => selectedCampaigns.value.includes(id)));
function toggleSelectAllPage() {
  const pageIds = idsOnPage.value;
  if (pageIds.length === 0) return;
  if (allOnPageSelected.value) {
    selectedCampaigns.value = selectedCampaigns.value.filter(id => !pageIds.includes(id));
  } else {
    const set = new Set(selectedCampaigns.value);
    pageIds.forEach(id => set.add(id));
    selectedCampaigns.value = Array.from(set);
  }
}

const statusColors = {
  draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
  scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  sending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  sent: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  paused: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

function search() {
  searchForm.get(route('newsletter.campaigns.index'), {
    preserveState: true,
    replace: true,
  });
}

function clearFilters() {
  searchForm.search = '';
  search();
}

function setView(view) {
  const current = searchForm.status || 'sent';
  if (current === view) return;
  // Clear selection on view change
  selectedCampaigns.value = [];
  lastSelectedIndex.value = null;
  searchForm.status = view;
  search();
}

function sendCampaign(campaign) {
  if (confirm(`Are you sure you want to send "${campaign.name}" to ${campaign.total_recipients} recipients?`)) {
    router.post(route('newsletter.campaigns.send', campaign.id));
  }
}

function pauseCampaign(campaign) {
  if (confirm(`Are you sure you want to pause "${campaign.name}"?`)) {
    router.post(route('newsletter.campaigns.pause', campaign.id));
  }
}

function resumeCampaign(campaign) {
  if (confirm(`Are you sure you want to resume sending "${campaign.name}"?`)) {
    router.post(route('newsletter.campaigns.resume', campaign.id));
  }
}

function cancelCampaign(campaign) {
  if (confirm(`Are you sure you want to cancel "${campaign.name}"? This action cannot be undone.`)) {
    router.post(route('newsletter.campaigns.cancel', campaign.id));
  }
}

function duplicateCampaign(campaign) {
  router.post(route('newsletter.campaigns.duplicate', campaign.id));
}

function deleteCampaign(campaign) {
  if (confirm(`Are you sure you want to delete "${campaign.name}"? This action cannot be undone.`)) {
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

function toggleCampaignSelection(campaignId, options = { replace: false }) {
  const idx = selectedCampaigns.value.indexOf(campaignId);
  if (options.replace) {
    selectedCampaigns.value = idx > -1 ? [campaignId] : [campaignId];
  } else if (idx > -1) {
    selectedCampaigns.value.splice(idx, 1);
  } else {
    selectedCampaigns.value.push(campaignId);
  }
}

function onRowSelect(campaignId, event, rowIndex) {
  const { shiftKey, metaKey, ctrlKey } = event;
  const useMeta = metaKey || ctrlKey;
  if (shiftKey && lastSelectedIndex.value !== null) {
    // range selection
    const start = Math.min(lastSelectedIndex.value, rowIndex);
    const end = Math.max(lastSelectedIndex.value, rowIndex);
    const rangeIds = idsOnPage.value.slice(start, end + 1);
    const current = new Set(selectedCampaigns.value);
    // If meta also held, union; otherwise replace selection with range
    if (useMeta) {
      rangeIds.forEach(id => current.add(id));
      selectedCampaigns.value = Array.from(current);
    } else {
      selectedCampaigns.value = Array.from(rangeIds);
    }
  } else if (useMeta) {
    toggleCampaignSelection(campaignId);
    lastSelectedIndex.value = rowIndex;
  } else {
    // regular click: select only this row
    toggleCampaignSelection(campaignId, { replace: true });
    lastSelectedIndex.value = rowIndex;
  }
}

function clearSelection() {
  selectedCampaigns.value = [];
  lastSelectedIndex.value = null;
}

function sendToTimeCapsule() {
  if (!isSentView.value || selectedCampaigns.value.length === 0) return;
  const campaignCount = selectedCampaigns.value.length;
  const message = campaignCount === 1 
    ? 'Are you sure you want to send this campaign to the Time Capsule?' 
    : `Are you sure you want to send ${campaignCount} campaigns to the Time Capsule?`;
    
  if (confirm(message)) {
    router.post(route('newsletter.campaigns.timecapsule.store'), {
      campaign_ids: selectedCampaigns.value
    }, {
      onSuccess: () => {
        selectedCampaigns.value = [];
      }
    });
  }
}

function deleteSelected() {
  if (!isInProgressView.value || selectedDeletableIds.value.length === 0) return;
  const count = selectedDeletableIds.value.length;
  const message = count === 1
    ? 'Are you sure you want to delete the selected campaign? This action cannot be undone.'
    : `Are you sure you want to delete ${count} selected campaigns? This action cannot be undone.`;
  if (!confirm(message)) return;

  // Fire deletes sequentially to avoid overwhelming server; ignore failures per item
  const ids = [...selectedDeletableIds.value];
  let processed = 0;
  const next = () => {
    const id = ids.shift();
    if (id === undefined) {
      selectedCampaigns.value = [];
      return;
    }
    router.delete(route('newsletter.campaigns.destroy', id), {
      preserveScroll: true,
      onFinish: () => {
        processed++;
        next();
      }
    });
  };
  next();
}
</script>

<template>
  <Head title="Newsletter Campaigns" />
  <AuthenticatedLayout>
    <template #header>
      
    </template>

    <div class="">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
        <div class="flex flex-wrap gap-2 items-center">
          <!-- Bulk actions -->
          <button
            v-if="isSentView && selectedCampaigns.length > 0"
            @click="sendToTimeCapsule"
            class="inline-flex items-center px-3 py-2 mb-2 bg-orange-600 border border-transparent rounded-md font-medium text-xs text-white uppercase tracking-widest hover:bg-orange-700 focus:bg-orange-700 active:bg-orange-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            <TrashIcon class="w-4 h-4 mr-2" />
            Send to Time Capsule ({{ selectedCampaigns.length }})
          </button>

          <button
            v-if="isInProgressView && selectedDeletableIds.length > 0"
            @click="deleteSelected"
            class="inline-flex items-center px-3 py-2 mb-2 bg-red-600 border border-transparent rounded-md font-medium text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
          >
            <TrashIcon class="w-4 h-4 mr-2" />
            Delete ({{ selectedDeletableIds.length }})
          </button>

          <button
            v-if="selectedCampaigns.length > 0"
            @click="clearSelection"
            class="inline-flex items-center px-3 py-2 mb-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-medium text-xs text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none transition"
          >
            Clear selection
          </button>
        </div>
        <Link
          :href="route('newsletter.campaigns.create')"
          class="inline-flex items-center px-4 py-2 mb-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <PlusIcon class="w-4 h-4 mr-2" />
          New Campaign
        </Link>
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
                    placeholder="Search campaigns..."
                    class="pl-10 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  />
                </div>
              </div>
              
              <div class="flex gap-2 items-center">
                <!-- View toggle -->
                <div class="inline-flex rounded-md shadow-sm" role="group" aria-label="View toggle">
                  <button type="button"
                          :class="isSentView
                            ? 'px-3 py-2 text-xs font-medium text-white bg-blue-600 border border-blue-600 rounded-l-md'
                            : 'px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-l-md hover:bg-gray-50 dark:hover:bg-gray-700'"
                          @click="setView('sent')">
                    Sent
                  </button>
                  <button type="button"
                          :class="isInProgressView
                            ? 'px-3 py-2 text-xs font-medium text-white bg-blue-600 border border-blue-600 rounded-r-md -ml-px'
                            : 'px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-r-md -ml-px hover:bg-gray-50 dark:hover:bg-gray-700'"
                          @click="setView('in_progress')">
                    In Progress
                  </button>
                </div>

                <!-- Per-page selector -->
                <select
                  v-model.number="searchForm.per_page"
                  @change="search"
                  class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                >
                  <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }} / page</option>
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
                    <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-300 select-none">
                      <input type="checkbox" :checked="allOnPageSelected" @change="toggleSelectAllPage" class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded mr-2">
                    </label>
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
                <tr
                  v-for="(campaign, rowIndex) in campaigns.data"
                  :key="campaign.id"
                  class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                  @click="onRowSelect(campaign.id, $event, rowIndex)"
                >
                  <td class="px-6 py-4" @click.stop>
                    <input
                      type="checkbox"
                      :checked="selectedCampaigns.includes(campaign.id)"
                      @change="onRowSelect(campaign.id, $event, rowIndex)"
                      class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded"
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
                  <td class="px-6 py-4 text-right text-sm font-medium" @click.stop>
                    <div class="flex justify-end gap-2">
                      <!-- View/Analytics -->
                      <Link
                        :href="route('newsletter.campaigns.show', campaign.id)"
                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                        title="View Details"
                      >
                        <EyeIcon class="w-4 h-4" />
                      </Link>

                      <!-- Edit (only for draft/scheduled) -->
                      <Link
                        v-if="['draft', 'scheduled'].includes(campaign.status)"
                        :href="route('newsletter.campaigns.edit', campaign.id)"
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                        title="Edit Campaign"
                      >
                        <PencilIcon class="w-4 h-4" />
                      </Link>

                      <!-- Send (only for draft) -->
                      <button
                        v-if="campaign.status === 'draft'"
                        @click="sendCampaign(campaign)"
                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                        title="Send Campaign"
                      >
                        <PlayIcon class="w-4 h-4" />
                      </button>

                      <!-- Pause (only for sending) -->
                      <button
                        v-if="campaign.status === 'sending'"
                        @click="pauseCampaign(campaign)"
                        class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                        title="Pause Campaign"
                      >
                        <PauseIcon class="w-4 h-4" />
                      </button>

                      <!-- Resume (only for paused) -->
                      <button
                        v-if="campaign.status === 'paused'"
                        @click="resumeCampaign(campaign)"
                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                        title="Resume Campaign"
                      >
                        <PlayIcon class="w-4 h-4" />
                      </button>

                      <!-- Cancel (for scheduled/sending/paused) -->
                      <button
                        v-if="['scheduled', 'sending', 'paused'].includes(campaign.status)"
                        @click="cancelCampaign(campaign)"
                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                        title="Cancel Campaign"
                      >
                        <StopIcon class="w-4 h-4" />
                      </button>

                      <!-- Duplicate -->
                      <button
                        @click="duplicateCampaign(campaign)"
                        class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                        title="Duplicate Campaign"
                      >
                        <DocumentDuplicateIcon class="w-4 h-4" />
                      </button>

                      <!-- Delete (only for draft/cancelled) - kept for quick per-item action -->
                      <button
                        v-if="['draft', 'cancelled'].includes(campaign.status)"
                        @click="deleteCampaign(campaign)"
                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                        title="Delete Campaign"
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
              No campaigns found
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">
              Get started by creating your first email campaign
            </p>
            <Link
              :href="route('newsletter.campaigns.create')"
              class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
              <PlusIcon class="w-4 h-4 mr-2" />
              Create Campaign
            </Link>
          </div>

          <!-- Pagination -->
          <div v-if="campaigns.links" class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ campaigns.from }} to {{ campaigns.to }} of {{ campaigns.total }} results
              </div>
              <div class="flex gap-1">
                <template v-for="(link, index) in campaigns.links" :key="index">
                  <Link
                    v-if="link.url"
                    :href="link.url"
                    v-html="link.label"
                    :class="`px-3 py-2 text-sm border rounded-md ${
                      link.active
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                    }`"
                  />
                  <span
                    v-else
                    v-html="link.label"
                    class="px-3 py-2 text-sm border rounded-md bg-gray-100 dark:bg-gray-900 text-gray-400 border-gray-300 dark:border-gray-600 cursor-not-allowed"
                    aria-disabled="true"
                  />
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
