<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

const statusColors = {
  draft: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
  scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  sending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  sent: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  paused: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

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
  if (confirm(`Are you sure you want to send "${props.campaign.name}" to ${props.campaign.total_recipients} recipients?`)) {
    router.post(route('newsletter.campaigns.send', props.campaign.id));
  }
}

function pauseCampaign() {
  if (confirm(`Are you sure you want to pause "${props.campaign.name}"?`)) {
    router.post(route('newsletter.campaigns.pause', props.campaign.id));
  }
}

function resumeCampaign() {
  if (confirm(`Are you sure you want to resume sending "${props.campaign.name}"?`)) {
    router.post(route('newsletter.campaigns.resume', props.campaign.id));
  }
}

function cancelCampaign() {
  if (confirm(`Are you sure you want to cancel "${props.campaign.name}"? This action cannot be undone.`)) {
    router.post(route('newsletter.campaigns.cancel', props.campaign.id));
  }
}

function duplicateCampaign() {
  router.post(route('newsletter.campaigns.duplicate', props.campaign.id));
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
</script>

<template>
  <Head :title="campaign.name" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-start">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              {{ campaign.name }}
            </h2>
            <span :class="`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${statusColors[campaign.status]}`">
              {{ campaign.status }}
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
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700"
          >
            <PlayIcon class="w-4 h-4 mr-2" />
            Send Now
          </button>

          <button
            v-if="campaign.status === 'sending'"
            @click="pauseCampaign"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-yellow-600 border border-transparent rounded-md hover:bg-yellow-700"
          >
            <PauseIcon class="w-4 h-4 mr-2" />
            Pause
          </button>

          <button
            v-if="campaign.status === 'paused'"
            @click="resumeCampaign"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700"
          >
            <PlayIcon class="w-4 h-4 mr-2" />
            Resume
          </button>

          <button
            v-if="['scheduled', 'sending', 'paused'].includes(campaign.status)"
            @click="cancelCampaign"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700"
          >
            <StopIcon class="w-4 h-4 mr-2" />
            Cancel
          </button>

          <button
            @click="duplicateCampaign"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            <DocumentDuplicateIcon class="w-4 h-4 mr-2" />
            Duplicate
          </button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
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

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Recent Activity
            </h3>
          </div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-if="recentEvents.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
              No activity yet
            </div>
            <div v-for="event in recentEvents" :key="event.id" class="p-6 flex items-center gap-4">
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
