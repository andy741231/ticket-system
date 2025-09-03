<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  campaigns: Object,
});

const campaignsList = computed(() => props.campaigns?.data || []);
const selectedCampaignId = ref(campaignsList.value[0]?.id ?? null);
const selectedUrl = computed(() => selectedCampaignId.value ? route('newsletter.public.campaign.view', selectedCampaignId.value) + '?embed=1' : null);
const newsletterIframe = ref(null);

// Listen for messages from iframe
onMounted(() => {
  window.addEventListener('message', handleIframeMessage);
});

onUnmounted(() => {
  window.removeEventListener('message', handleIframeMessage);
});

function handleIframeMessage(event) {
  if (event.data.type === 'setHeight' && newsletterIframe.value && 
      event.source === newsletterIframe.value.contentWindow) {
    newsletterIframe.value.style.height = `${event.data.height}px`;
  }
}

function adjustIframeHeight() {
  if (newsletterIframe.value && newsletterIframe.value.contentWindow) {
    // Request the content height from the iframe
    newsletterIframe.value.contentWindow.postMessage({ type: 'getHeight' }, '*');
  }
}

function formatDate(dateString) {
  if (!dateString) return null;
  const d = new Date(dateString);
  return d.toLocaleString(undefined, {
    month: 'short', day: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
}
</script>

<template>
  <Head title="Newsletter Archive" />
  <AuthenticatedLayout>
    <template #header>
      <div>
      
      </div>
    </template>

    <div class="">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left: Archive Sidebar -->
          <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Archives</h3>
              </div>
              <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[calc(100vh-20rem)] overflow-y-auto">
                <button
                  v-for="campaign in campaigns.data.slice(0, 10)"
                  :key="campaign.id"
                  type="button"
                  @click="selectedCampaignId = campaign.id"
                  class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                  :class="selectedCampaignId === campaign.id ? 'bg-gray-50 dark:bg-gray-700' : ''"
                >
                  <p class="font-medium text-gray-900 dark:text-gray-100">{{ campaign.name }}</p>
                  <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                    {{ campaign.subject }}
                    <template v-if="campaign.sent_at">· {{ formatDate(campaign.sent_at) }}</template>
                  </p>
                </button>
              </div>

              <div v-if="campaigns.data.length === 0" class="p-6 text-center text-gray-600 dark:text-gray-400">
                No past newsletters found.
              </div>

              <!-- View Archive Button -->
              <div class="p-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                <a 
                  :href="route('newsletter.public.archive')"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors duration-200"
                >
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-1M14 6h6m0 0v6m0-6L10 16" />
                  </svg>
                  View Full Archive
                </a>
              </div>
            </div>
          </div>

          <!-- Right: Latest Newsletter Preview -->
          <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Latest Newsletter</h3>
              </div>
              <div class="p-0">
                <iframe
                  v-if="selectedUrl"
                  :src="selectedUrl"
                  ref="newsletterIframe"
                  class="w-full bg-white dark:bg-gray-800"
                  style="min-height: 400px;"
                  @load="adjustIframeHeight"
                ></iframe>
                <div v-else class="p-6 text-gray-600 dark:text-gray-400">
                  Select a newsletter to preview.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
