<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import {
  PencilIcon,
  PlayIcon,
  PauseIcon,
  StopIcon,
  DocumentDuplicateIcon,
  EyeIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  campaign: Object,
  analytics: Object,
  recentEvents: Array,
});

const showPreview = ref(false);

// Loading state for actions to prevent duplicate submissions and provide UX feedback
const isLoading = ref({
  send: false,
  pause: false,
  resume: false,
  cancel: false,
  duplicate: false,
});

// Deliveries (scheduled sends) state
const deliveries = ref({
  data: [],
  meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
  counts: {},
});
const deliveryFilters = ref({ status: '', search: '', page: 1, per_page: 15 });
const isLoadingDeliveries = ref(false);
const selected = ref(new Set());

// Safe default to avoid accessing length of undefined
const recentEventsSafe = computed(() => props.recentEvents ?? []);

const statusColors = {
  draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
  scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  sending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  sent: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  paused: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

// If campaign finished processing but all deliveries failed, present as "failed"
const statusLabel = computed(() => {
  if (
    props.campaign.status === 'sent' &&
    props.campaign.sent_count === 0 &&
    props.campaign.failed_count > 0
  ) {
    return 'failed';
  }
  return props.campaign.status;
});

const statusClass = computed(() => {
  const key = statusLabel.value;
  // Map failed to red styling reusing cancelled palette for consistency
  const map = {
    ...statusColors,
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
  };
  return map[key] ?? statusColors.draft;
});

const progressPercentage = computed(() => {
  if (props.campaign.total_recipients === 0) return 0;
  return Math.round((props.campaign.sent_count / props.campaign.total_recipients) * 100);
});

// Ensure analytics fields are always numeric to avoid toLocaleString on undefined
const metrics = computed(() => ({
  opens: props.analytics?.opens ?? 0,
  clicks: props.analytics?.clicks ?? 0,
  unsubscribes: props.analytics?.unsubscribes ?? 0,
  bounces: props.analytics?.bounces ?? 0,
}));

function sendCampaign() {
  if (!confirm(`Are you sure you want to send "${props.campaign.name}" to ${props.campaign.total_recipients} recipients?`)) return;
  isLoading.value.send = true;
  router.post(route('newsletter.campaigns.send', props.campaign.id), {}, {
    preserveScroll: true,
    onSuccess: () => { reloadCampaign(); },
    onError: () => alert('Failed to start sending. Please check the campaign and try again.'),
    onFinish: () => { isLoading.value.send = false; },
  });
}

function pauseCampaign() {
  if (!confirm(`Are you sure you want to pause "${props.campaign.name}"?`)) return;
  isLoading.value.pause = true;
  router.post(route('newsletter.campaigns.pause', props.campaign.id), {}, {
    preserveScroll: true,
    onSuccess: () => { reloadCampaign(); },
    onError: () => alert('Failed to pause campaign.'),
    onFinish: () => { isLoading.value.pause = false; },
  });
}

function resumeCampaign() {
  if (!confirm(`Are you sure you want to resume sending "${props.campaign.name}"?`)) return;
  isLoading.value.resume = true;
  router.post(route('newsletter.campaigns.resume', props.campaign.id), {}, {
    preserveScroll: true,
    onSuccess: () => { reloadCampaign(); },
    onError: () => alert('Failed to resume campaign.'),
    onFinish: () => { isLoading.value.resume = false; },
  });
}

function cancelCampaign() {
  if (!confirm(`Are you sure you want to cancel "${props.campaign.name}"? This action cannot be undone.`)) return;
  isLoading.value.cancel = true;
  router.post(route('newsletter.campaigns.cancel', props.campaign.id), {}, {
    preserveScroll: true,
    onSuccess: () => { reloadCampaign(); },
    onError: () => alert('Failed to cancel campaign.'),
    onFinish: () => { isLoading.value.cancel = false; },
  });
}

function duplicateCampaign() {
  isLoading.value.duplicate = true;
  router.post(route('newsletter.campaigns.duplicate', props.campaign.id), {}, {
    preserveScroll: true,
    onError: () => alert('Failed to duplicate campaign.'),
    onFinish: () => { isLoading.value.duplicate = false; },
  });
}

function formatDate(dateString) {
  return dateString ? new Date(dateString).toLocaleString() : '-';
}

function getEventIcon(eventType) {
  switch (eventType) {
    case 'open': return '👁️';
    case 'click': return '🔗';
    case 'unsubscribe': return '❌';
    case 'bounce': return '⚠️';
    default: return '📧';
  }
}

// --- Deliveries (scheduled sends) helpers ---
async function fetchDeliveries() {
  try {
    isLoadingDeliveries.value = true;
    selected.value.clear();
    const params = {
      status: deliveryFilters.value.status || undefined,
      search: deliveryFilters.value.search || undefined,
      page: deliveryFilters.value.page,
      per_page: deliveryFilters.value.per_page,
    };
    const { data } = await axios.get(route('newsletter.campaigns.scheduled-sends', props.campaign.id), { params });
    // Expecting { data: [], meta: {...}, counts: {...} }
    deliveries.value = data;
  } catch (e) {
    // no-op; UI remains as-is
  } finally {
    isLoadingDeliveries.value = false;
  }
}

function toggleSelectAll(ev) {
  const checked = ev.target.checked;
  selected.value.clear();
  if (checked) {
    deliveries.value.data.forEach(item => selected.value.add(item.id));
  }
}

function toggleOne(id, ev) {
  if (ev.target.checked) selected.value.add(id); else selected.value.delete(id);
}

async function retrySelected(autoResume = true) {
  if (selected.value.size === 0) return;
  try {
    await axios.post(route('newsletter.campaigns.retry-scheduled-sends', props.campaign.id), {
      ids: Array.from(selected.value),
      auto_resume: !!autoResume,
    });
    await fetchDeliveries();
    reloadCampaign();
  } catch (e) {
    alert('Retry failed: ' + (e.response?.data?.message || e.message));
  }
}

async function processPending(autoResume = true) {
  try {
    await axios.post(route('newsletter.campaigns.process-pending', props.campaign.id), {
      auto_resume: !!autoResume,
    });
    setTimeout(() => { fetchDeliveries(); reloadCampaign(); }, 1200);
  } catch (e) {
    alert('Failed to start processing: ' + (e.response?.data?.message || e.message));
  }
}

// --- Real-time polling for status/metrics while sending or paused ---
let pollTimer = null;

function reloadCampaign() {
  router.reload({
    only: ['campaign', 'analytics', 'recentEvents'],
    preserveScroll: true,
  });
}

function startPolling() {
  if (pollTimer) return;
  pollTimer = setInterval(() => {
    reloadCampaign();
    fetchDeliveries();
  }, 4000);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

onMounted(() => {
  if (['sending', 'paused'].includes(props.campaign.status)) {
    startPolling();
  }
  fetchDeliveries();
});

watch(() => props.campaign.status, (newStatus) => {
  if (['sending', 'paused'].includes(newStatus)) {
    startPolling();
  } else {
    stopPolling();
  }
});

onBeforeUnmount(() => {
  stopPolling();
});

watch(deliveryFilters, () => {
  // Reset to first page when filters (except page) change
  deliveryFilters.value.page = deliveryFilters.value.page || 1;
  fetchDeliveries();
}, { deep: true });
</script>

<template>
  <Head :title="campaign.name" />
  <AuthenticatedLayout>
    <template #header>
      
    </template>

    <div class="">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex justify-between items-start">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              {{ campaign.name }}
            </h2>
            <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusClass}`">
              {{ statusLabel }}
            </span>
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ campaign.subject }}
          </p>
        </div>
        
        <div class="flex gap-2">
          <button
            @click="showPreview = !showPreview"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            <EyeIcon class="w-4 h-4 mr-2" />
            {{ showPreview ? 'Hide' : 'Preview' }}
          </button>

          <Link
            v-if="['draft', 'scheduled'].includes(campaign.status)"
            :href="route('newsletter.campaigns.edit', campaign.id)"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            <PencilIcon class="w-4 h-4 mr-2" />
            Edit
          </Link>

          <button
            v-if="campaign.status === 'draft'"
            @click="sendCampaign"
            :disabled="isLoading.send"
            :aria-busy="isLoading.send"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <PlayIcon class="w-4 h-4 mr-2" />
            {{ isLoading.send ? 'Sending...' : 'Send Now' }}
          </button>

          <button
            v-if="campaign.status === 'sending'"
            @click="pauseCampaign"
            :disabled="isLoading.pause"
            :aria-busy="isLoading.pause"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-yellow-600 border border-transparent rounded-md hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <PauseIcon class="w-4 h-4 mr-2" />
            {{ isLoading.pause ? 'Pausing...' : 'Pause' }}
          </button>

          <button
            v-if="campaign.status === 'paused'"
            @click="resumeCampaign"
            :disabled="isLoading.resume"
            :aria-busy="isLoading.resume"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <PlayIcon class="w-4 h-4 mr-2" />
            {{ isLoading.resume ? 'Resuming...' : 'Resume' }}
          </button>

          <button
            v-if="['scheduled', 'sending', 'paused'].includes(campaign.status)"
            @click="cancelCampaign"
            :disabled="isLoading.cancel"
            :aria-busy="isLoading.cancel"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <StopIcon class="w-4 h-4 mr-2" />
            {{ isLoading.cancel ? 'Cancelling...' : 'Cancel' }}
          </button>

          <button
            @click="duplicateCampaign"
            :disabled="isLoading.duplicate"
            :aria-busy="isLoading.duplicate"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <DocumentDuplicateIcon class="w-4 h-4 mr-2" />
            {{ isLoading.duplicate ? 'Duplicating...' : 'Duplicate' }}
          </button>
        </div>
      </div>
        
        <!-- Campaign Overview -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

          
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                {{ campaign.total_recipients.toLocaleString() }}
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Recipients</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">
                {{ campaign.sent_count.toLocaleString() }}
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Sent</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600">
                {{ campaign.open_rate }}%
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Open Rate</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-purple-600">
                {{ campaign.click_rate }}%
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Click Rate</div>
            </div>
          </div>

          <!-- All failed notice with error details -->
          <div
            v-if="campaign.status === 'sent' && campaign.sent_count === 0 && campaign.failed_count > 0"
            class="mt-4 p-3 rounded-md bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
          >
            <div class="font-semibold mb-2">All delivery attempts failed. No emails were delivered.</div>
            <div v-if="campaign.last_error" class="mt-2 p-2 bg-white dark:bg-gray-800 rounded border border-red-200 dark:border-red-800 overflow-auto max-h-40">
              <div class="font-medium mb-1">Error Details:</div>
              <pre class="text-xs whitespace-pre-wrap">{{ campaign.last_error }}</pre>
            </div>
            <div v-else class="text-sm mt-2">
              No specific error details available. Please check your mail configuration and server logs.
            </div>
            <div class="mt-3 text-sm">
              Common issues:
              <ul class="list-disc pl-5 mt-1 space-y-1">
                <li>Mail server configuration</li>
                <li>API keys or SMTP credentials</li>
                <li>Email sending limits</li>
                <li>DNS settings (SPF, DKIM, DMARC)</li>
              </ul>
            </div>
          </div>

          <div v-if="campaign.status === 'sending' && campaign.total_recipients > 0" class="mt-6">
            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
              <span>Sending Progress</span>
              <span>{{ progressPercentage }}%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
              <div 
                class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                :style="`width: ${progressPercentage}%`"
              ></div>
            </div>
          </div>
        </div>

        <!-- Campaign Details and Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Campaign Details
            </h3>
            <dl class="space-y-3">
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Subject Line</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ campaign.subject }}</dd>
              </div>
              <div v-if="campaign.preview_text">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Preview Text</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ campaign.preview_text }}</dd>
              </div>
              <div v-if="campaign.template">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Template</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ campaign.template.name }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(campaign.created_at) }}</dd>
              </div>
              <div v-if="campaign.scheduled_at">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Scheduled</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(campaign.scheduled_at) }}</dd>
              </div>
              <div v-if="campaign.sent_at">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Sent</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ formatDate(campaign.sent_at) }}</dd>
              </div>
            </dl>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Performance Metrics
            </h3>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Opens</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ metrics.opens.toLocaleString() }} ({{ campaign.open_rate }}%)
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Clicks</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ metrics.clicks.toLocaleString() }} ({{ campaign.click_rate }}%)
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Unsubscribes</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ metrics.unsubscribes.toLocaleString() }} ({{ campaign.unsubscribe_rate }}%)
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Bounces</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ metrics.bounces.toLocaleString() }} ({{ campaign.bounce_rate }}%)
                </span>
              </div>
              <div v-if="campaign.failed_count > 0" class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Failed</span>
                <span class="text-sm font-medium text-red-600">
                  {{ campaign.failed_count.toLocaleString() }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Email Preview -->
        <div v-if="showPreview" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Email Preview
            </h3>
          </div>
          <div class="p-6">
            <div class="max-w-2xl mx-auto border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
              <div class="bg-gray-50 dark:bg-gray-900 p-4 border-b border-gray-200 dark:border-gray-600">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  Subject: {{ campaign.subject }}
                </div>
                <div v-if="campaign.preview_text" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  {{ campaign.preview_text }}
                </div>
              </div>
              <div class="bg-white dark:bg-gray-800 p-6">
                <div v-html="campaign.html_content" class="prose dark:prose-invert max-w-none"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Deliveries (Scheduled Sends) -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Deliveries</h3>
            <div class="flex items-center gap-2">
              <button
                @click="processPending(true)"
                class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
                title="Process pending sends (auto-resume if needed)"
              >Process Pending</button>
              <button
                :disabled="selected.size === 0"
                @click="retrySelected(true)"
                class="px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                title="Retry selected (auto-resume if paused)"
              >Retry Selected</button>
            </div>
          </div>
          <div class="p-6 space-y-4">
            <div class="flex flex-col md:flex-row gap-3 md:items-end">
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 dark:text-gray-300">Status</label>
                <select v-model="deliveryFilters.status" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md">
                  <option value="">All</option>
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="sent">Sent</option>
                  <option value="failed">Failed</option>
                  <option value="skipped">Skipped</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="flex-1">
                <input v-model="deliveryFilters.search" type="text" placeholder="Search email..." class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md" />
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 dark:text-gray-300">Per page</label>
                <select v-model.number="deliveryFilters.per_page" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md">
                  <option :value="10">10</option>
                  <option :value="15">15</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                </select>
              </div>
            </div>

            <div class="text-sm text-gray-600 dark:text-gray-400">
              <span class="mr-3" v-for="(count, s) in deliveries.counts" :key="s">{{ s }}: <strong>{{ count }}</strong></span>
            </div>

            <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th class="px-4 py-2">
                      <input type="checkbox" @change="toggleSelectAll" :checked="selected.size>0 && selected.size===deliveries.data.length" />
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Error</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sent</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-if="isLoadingDeliveries">
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Loading...</td>
                  </tr>
                  <tr v-else-if="deliveries.data.length === 0">
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No results</td>
                  </tr>
                  <tr v-for="row in deliveries.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-4 py-2">
                      <input type="checkbox" :checked="selected.has(row.id)" @change="(e)=>toggleOne(row.id,e)" />
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100">{{ row.subscriber?.email || '-' }}</td>
                    <td class="px-6 py-3 text-sm">
                      <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                        :class="{
                          'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': row.status==='pending',
                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': row.status==='processing',
                          'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': row.status==='sent',
                          'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': row.status==='failed' || row.status==='cancelled',
                          'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': row.status==='skipped',
                        }"
                      >{{ row.status }}</span>
                    </td>
                    <td class="px-6 py-3 text-xs text-red-600 max-w-xs truncate" :title="row.error_message || ''">{{ row.error_message || '' }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ row.scheduled_at ? new Date(row.scheduled_at).toLocaleString() : '-' }}</td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ row.sent_at ? new Date(row.sent_at).toLocaleString() : '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
              <div>
                Showing page {{ deliveries.meta.current_page }} of {{ deliveries.meta.last_page }} ({{ deliveries.meta.total }} total)
              </div>
              <div class="flex gap-2">
                <button
                  class="px-3 py-1 border rounded disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="deliveries.meta.current_page <= 1"
                  @click="deliveryFilters.page = deliveries.meta.current_page - 1"
                >Prev</button>
                <button
                  class="px-3 py-1 border rounded disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="deliveries.meta.current_page >= deliveries.meta.last_page"
                  @click="deliveryFilters.page = deliveries.meta.current_page + 1"
                >Next</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Recent Activity
            </h3>
          </div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-if="recentEventsSafe.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
              No activity yet
            </div>
            <div v-for="event in recentEventsSafe" :key="event.id" class="p-6 flex items-center gap-4">
              <div class="text-2xl">{{ getEventIcon(event.event_type) }}</div>
              <div class="flex-1">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ event.subscriber.email }} {{ event.event_type === 'open' ? 'opened' : event.event_type === 'click' ? 'clicked' : event.event_type }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatDate(event.created_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
