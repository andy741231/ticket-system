<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
  ArrowLeftIcon, 
  TrashIcon, 
  PencilIcon,
  EnvelopeIcon,
  UserIcon,
  BuildingOfficeIcon,
  TagIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  subscriber: Object,
  analytics: Object,
  groups: Array,
});

const statusColors = {
  active: 'bg-uh-green/10 text-uh-green border border-uh-green/20',
  unsubscribed: 'bg-uh-red/10 text-uh-red border border-uh-red/20',
  bounced: 'bg-uh-gold/10 text-uh-ocher border border-uh-gold/20',
  pending: 'bg-uh-gray/10 text-uh-slate border border-uh-gray/20',
};

const editForm = useForm({
  email: props.subscriber.email || '',
  name: props.subscriber.name || '',
  first_name: props.subscriber.first_name || '',
  last_name: props.subscriber.last_name || '',
  organization: props.subscriber.organization || '',
  status: props.subscriber.status || 'active',
  groups: (props.subscriber.groups || []).map(g => g.id),
});

function updateSubscriber() {
  editForm.put(route('newsletter.subscribers.update', props.subscriber.id));
}

function deleteSubscriber() {
  if (confirm(`Delete ${props.subscriber.email}? This cannot be undone.`)) {
    router.delete(route('newsletter.subscribers.destroy', props.subscriber.id));
  }
}

function displayName(subscriber) {
  const nameFromParts = [subscriber.first_name, subscriber.last_name].filter(Boolean).join(' ').trim();
  const name = subscriber.full_name || nameFromParts || subscriber.name || '';
  return name || subscriber.email || 'Unknown';
}

function formatDate(dateString) {
  return dateString ? new Date(dateString).toLocaleString() : '-';
}

function mapEventType(type) {
  switch (type) {
    case 'opened': return 'open';
    case 'clicked': return 'click';
    case 'unsubscribed': return 'unsubscribe';
    case 'bounced': return 'bounce';
    default: return type;
  }
}

const metrics = computed(() => ({
  total_campaigns: props.analytics?.total_campaigns ?? 0,
  total_opens: props.analytics?.total_opens ?? 0,
  total_clicks: props.analytics?.total_clicks ?? 0,
}));
</script>

<template>
  <Head :title="displayName(subscriber)" />
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
            <div class="flex items-center gap-3">
              <h2 class="text-2xl font-bold text-uh-slate dark:text-uh-white leading-tight">
                {{ displayName(subscriber) }}
              </h2>
              <span :class="`inline-flex px-3 py-1 text-sm font-medium rounded-full ${statusColors[subscriber.status]}`">
                {{ subscriber.status }}
              </span>
            </div>
            <p class="mt-1 text-uh-gray dark:text-gray-400 flex items-center gap-2">
              <EnvelopeIcon class="w-4 h-4" />
              {{ subscriber.email }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button 
            @click="deleteSubscriber"
            class="inline-flex items-center px-4 py-2.5 bg-uh-red hover:bg-uh-brick text-white font-medium rounded-lg transition-colors duration-200 shadow-sm"
          >
            <TrashIcon class="w-4 h-4 mr-2" />
            Delete
          </button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <!-- Profile Overview -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
          <div class="px-6 py-8">
            <div class="flex items-start gap-6">
              <!-- Avatar -->
              <div class="flex-shrink-0">
                <div class="h-20 w-20 rounded-full bg-gradient-to-br from-uh-red to-uh-brick flex items-center justify-center shadow-lg">
                  <span class="text-2xl font-bold text-white">
                    {{ displayName(subscriber).charAt(0).toUpperCase() }}
                  </span>
                </div>
              </div>
              
              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-3">
                    <div class="flex items-center gap-2 text-uh-gray dark:text-gray-400">
                      <UserIcon class="w-4 h-4" />
                      <span class="text-sm font-medium">Personal Info</span>
                    </div>
                    <div>
                      <p class="text-sm text-uh-gray dark:text-gray-400">Full Name</p>
                      <p class="font-medium text-uh-slate dark:text-uh-white">{{ displayName(subscriber) }}</p>
                    </div>
                    <div v-if="subscriber.organization">
                      <p class="text-sm text-uh-gray dark:text-gray-400">Organization</p>
                      <p class="font-medium text-uh-slate dark:text-uh-white flex items-center gap-2">
                        <BuildingOfficeIcon class="w-4 h-4" />
                        {{ subscriber.organization }}
                      </p>
                    </div>
                  </div>
                  
                  <div class="space-y-3">
                    <div class="flex items-center gap-2 text-uh-gray dark:text-gray-400">
                      <ChartBarIcon class="w-4 h-4" />
                      <span class="text-sm font-medium">Subscription Info</span>
                    </div>
                    <div>
                      <p class="text-sm text-uh-gray dark:text-gray-400">Status</p>
                      <span :class="`inline-flex px-3 py-1 text-sm font-medium rounded-full ${statusColors[subscriber.status]}`">
                        {{ subscriber.status }}
                      </span>
                    </div>
                    <div v-if="subscriber.subscribed_at">
                      <p class="text-sm text-uh-gray dark:text-gray-400">Subscribed</p>
                      <p class="font-medium text-uh-slate dark:text-uh-white">{{ formatDate(subscriber.subscribed_at) }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
          <div class="px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-white flex items-center gap-2">
              <PencilIcon class="w-5 h-5" />
              Edit Subscriber
            </h3>
          </div>
          <div class="p-6">
            <form @submit.prevent="updateSubscriber" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Email Address</label>
                <input 
                  v-model="editForm.email" 
                  type="email" 
                  required
                  class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Status</label>
                <select 
                  v-model="editForm.status"
                  class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                >
                  <option value="active">Active</option>
                  <option value="unsubscribed">Unsubscribed</option>
                  <option value="bounced">Bounced</option>
                  <option value="pending">Pending</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">First Name</label>
                <input 
                  v-model="editForm.first_name" 
                  type="text"
                  class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Last Name</label>
                <input 
                  v-model="editForm.last_name" 
                  type="text"
                  class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Organization</label>
                <input 
                  v-model="editForm.organization" 
                  type="text"
                  class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                />
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-3 flex items-center gap-2">
                  <TagIcon class="w-4 h-4" />
                  Groups
                </label>
                <div class="bg-uh-cream/30 dark:bg-gray-700/50 border border-uh-gray/20 dark:border-gray-600 rounded-lg p-4 max-h-40 overflow-y-auto">
                  <div class="space-y-3">
                    <label v-for="g in groups" :key="g.id" class="flex items-center gap-3 cursor-pointer hover:bg-white/50 dark:hover:bg-gray-600/50 p-2 rounded-md transition-colors duration-200">
                      <input
                        v-model="editForm.groups"
                        :value="g.id"
                        type="checkbox"
                        class="rounded border-uh-gray/30 text-uh-red shadow-sm focus:ring-uh-red/20"
                      />
                      <span class="text-sm font-medium text-uh-slate dark:text-gray-300">{{ g.name }}</span>
                    </label>
                    <div v-if="!groups.length" class="text-center py-4 text-uh-gray dark:text-gray-400">
                      No groups available
                    </div>
                  </div>
                </div>
              </div>

              <div class="md:col-span-2 flex justify-end pt-4">
                <button 
                  type="submit" 
                  :disabled="editForm.processing"
                  class="inline-flex items-center px-6 py-2.5 bg-uh-red hover:bg-uh-brick text-white font-medium rounded-lg transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <PencilIcon class="w-4 h-4 mr-2" />
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Analytics -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
          <div class="px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-white flex items-center gap-2">
              <ChartBarIcon class="w-5 h-5" />
              Activity & Analytics
            </h3>
          </div>
          <div class="p-6">
            <!-- Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
              <div class="bg-uh-cream/30 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-uh-slate dark:text-uh-white mb-1">{{ metrics.total_campaigns }}</div>
                <div class="text-sm font-medium text-uh-gray dark:text-gray-400">Campaigns Received</div>
              </div>
              <div class="bg-uh-teal/10 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-uh-teal mb-1">{{ metrics.total_opens }}</div>
                <div class="text-sm font-medium text-uh-gray dark:text-gray-400">Email Opens</div>
              </div>
              <div class="bg-uh-red/10 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-uh-red mb-1">{{ metrics.total_clicks }}</div>
                <div class="text-sm font-medium text-uh-gray dark:text-gray-400">Link Clicks</div>
              </div>
            </div>

            <!-- Recent Activity -->
            <div>
              <h4 class="text-sm font-semibold text-uh-slate dark:text-uh-white mb-4">Recent Activity</h4>
              <div v-if="!analytics?.recent_events || analytics.recent_events.length === 0"
                   class="text-center py-8 text-uh-gray dark:text-gray-400">
                <div class="w-12 h-12 mx-auto mb-3 bg-uh-gray/10 rounded-full flex items-center justify-center">
                  <ChartBarIcon class="w-6 h-6 text-uh-gray" />
                </div>
                <p>No recent activity</p>
              </div>
              <div v-else class="space-y-3">
                <div 
                  v-for="event in analytics.recent_events" 
                  :key="event.id" 
                  class="flex items-center justify-between p-3 bg-uh-cream/20 dark:bg-gray-700/30 rounded-lg"
                >
                  <div class="flex-1">
                    <p class="text-sm font-medium text-uh-slate dark:text-uh-white">
                      {{ mapEventType(event.event_type) }}
                      <span v-if="event.campaign" class="text-uh-gray dark:text-gray-400">"{{ event.campaign.name }}"</span>
                    </p>
                  </div>
                  <div class="text-xs text-uh-gray dark:text-gray-400 font-medium">
                    {{ formatDate(event.created_at) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
