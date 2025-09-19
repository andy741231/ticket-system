<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
  ChartBarIcon,
  EyeIcon,
  CursorArrowRaysIcon,
  UserMinusIcon,
  ExclamationTriangleIcon,
  CalendarIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  overview: Object,
  campaigns: Array,
  topPerformers: Array,
  recentActivity: Array,
  dateRange: String,
});

const selectedPeriod = ref(props.dateRange || '30');

// Safe default to avoid accessing length of undefined
const recentActivitySafe = computed(() => props.recentActivity ?? []);

const overviewStats = computed(() => [
  {
    name: 'Total Campaigns',
    value: props.overview.total_campaigns,
    icon: ChartBarIcon,
    color: 'text-blue-600',
    bgColor: 'bg-blue-100 dark:bg-blue-900/20',
  },
  {
    name: 'Total Opens',
    value: props.overview.total_opens.toLocaleString(),
    icon: EyeIcon,
    color: 'text-green-600',
    bgColor: 'bg-green-100 dark:bg-green-900/20',
    change: props.overview.opens_change,
  },
  {
    name: 'Total Clicks',
    value: props.overview.total_clicks.toLocaleString(),
    icon: CursorArrowRaysIcon,
    color: 'text-purple-600',
    bgColor: 'bg-purple-100 dark:bg-purple-900/20',
    change: props.overview.clicks_change,
  },
  {
    name: 'Unsubscribes',
    value: props.overview.total_unsubscribes.toLocaleString(),
    icon: UserMinusIcon,
    color: 'text-red-600',
    bgColor: 'bg-red-100 dark:bg-gray-900',
    change: props.overview.unsubscribes_change,
  },
]);

function formatPercentage(value) {
  return `${value}%`;
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString();
}

function getChangeIcon(change) {
  if (change > 0) return ArrowTrendingUpIcon;
  if (change < 0) return ArrowTrendingDownIcon;
  return null;
}

function getChangeColor(change) {
  if (change > 0) return 'text-green-600';
  if (change < 0) return 'text-red-600';
  return 'text-gray-500';
}

function changePeriod(period) {
  selectedPeriod.value = period;
  // In a real app, this would trigger a new request
  window.location.href = route('newsletter.analytics', { period });
}
</script>

<template>
  <Head title="Newsletter Analytics" />
  <AuthenticatedLayout>
    <template #header>
      
    </template>

    <div class="">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex justify-between items-center">
        <div>
          
        </div>
        
        <div class="flex gap-2">
          <select
            :value="selectedPeriod"
            @change="changePeriod($event.target.value)"
            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
          >
            <option value="7">Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="365">Last year</option>
          </select>
        </div>
      </div>
        
        <!-- Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="stat in overviewStats"
            :key="stat.name"
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6"
          >
            <div class="flex items-center">
              <div :class="`p-3 rounded-lg ${stat.bgColor}`">
                <component :is="stat.icon" :class="`w-6 h-6 ${stat.color}`" />
              </div>
              <div class="ml-4 flex-1">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                  {{ stat.name }}
                </p>
                <div class="flex items-center gap-2">
                  <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ stat.value }}
                  </p>
                  <div v-if="stat.change !== undefined" class="flex items-center text-sm">
                    <component
                      v-if="getChangeIcon(stat.change)"
                      :is="getChangeIcon(stat.change)"
                      :class="`w-4 h-4 ${getChangeColor(stat.change)}`"
                    />
                    <span :class="getChangeColor(stat.change)">
                      {{ Math.abs(stat.change) }}%
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Average Performance Rates
            </h3>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Open Rate</span>
                <div class="flex items-center gap-2">
                  <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div 
                      class="bg-green-600 h-2 rounded-full"
                      :style="`width: ${Math.min(overview.avg_open_rate, 100)}%`"
                    ></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-12">
                    {{ formatPercentage(overview.avg_open_rate) }}
                  </span>
                </div>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Click Rate</span>
                <div class="flex items-center gap-2">
                  <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div 
                      class="bg-purple-600 h-2 rounded-full"
                      :style="`width: ${Math.min(overview.avg_click_rate, 100)}%`"
                    ></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-12">
                    {{ formatPercentage(overview.avg_click_rate) }}
                  </span>
                </div>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Unsubscribe Rate</span>
                <div class="flex items-center gap-2">
                  <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div 
                      class="bg-red-600 h-2 rounded-full"
                      :style="`width: ${Math.min(overview.avg_unsubscribe_rate * 10, 100)}%`"
                    ></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-12">
                    {{ formatPercentage(overview.avg_unsubscribe_rate) }}
                  </span>
                </div>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Bounce Rate</span>
                <div class="flex items-center gap-2">
                  <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div 
                      class="bg-yellow-600 h-2 rounded-full"
                      :style="`width: ${Math.min(overview.avg_bounce_rate * 5, 100)}%`"
                    ></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-12">
                    {{ formatPercentage(overview.avg_bounce_rate) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Subscriber Growth
            </h3>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Total Subscribers</span>
                <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                  {{ overview.total_subscribers.toLocaleString() }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">New Subscribers</span>
                <div class="flex items-center gap-2">
                  <span class="text-lg font-semibold text-green-600">
                    +{{ overview.new_subscribers.toLocaleString() }}
                  </span>
                  <ArrowTrendingUpIcon class="w-4 h-4 text-green-600" />
                </div>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600 dark:text-gray-400">Unsubscribed</span>
                <div class="flex items-center gap-2">
                  <span class="text-lg font-semibold text-red-600">
                    -{{ overview.lost_subscribers.toLocaleString() }}
                  </span>
                  <ArrowTrendingDownIcon class="w-4 h-4 text-red-600" />
                </div>
              </div>
              <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="flex justify-between items-center">
                  <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Net Growth</span>
                  <span :class="`text-lg font-bold ${overview.net_growth >= 0 ? 'text-green-600' : 'text-red-600'}`">
                    {{ overview.net_growth >= 0 ? '+' : '' }}{{ overview.net_growth.toLocaleString() }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Performing Campaigns -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Top Performing Campaigns
            </h3>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Campaign
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Recipients
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Open Rate
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Click Rate
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Sent Date
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="campaign in topPerformers" :key="campaign.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td class="px-6 py-4">
                    <div>
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ campaign.name }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ campaign.subject }}
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                    {{ campaign.total_recipients.toLocaleString() }}
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center">
                      <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                        <div 
                          class="bg-green-600 h-2 rounded-full"
                          :style="`width: ${Math.min(campaign.open_rate, 100)}%`"
                        ></div>
                      </div>
                      <span class="text-sm text-gray-900 dark:text-gray-100">
                        {{ formatPercentage(campaign.open_rate) }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center">
                      <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                        <div 
                          class="bg-purple-600 h-2 rounded-full"
                          :style="`width: ${Math.min(campaign.click_rate, 100)}%`"
                        ></div>
                      </div>
                      <span class="text-sm text-gray-900 dark:text-gray-100">
                        {{ formatPercentage(campaign.click_rate) }}
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(campaign.sent_at) }}
                  </td>
                  <td class="px-6 py-4 text-right text-sm font-medium">
                    <Link
                      :href="route('newsletter.campaigns.show', campaign.id)"
                      class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                      View Details
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <div v-if="topPerformers.length === 0" class="text-center py-8">
            <ChartBarIcon class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <p class="text-gray-500 dark:text-gray-400">No campaigns found for this period</p>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Recent Activity
            </h3>
          </div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
            <div v-if="recentActivitySafe.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
              No recent activity
            </div>
            <div v-for="activity in recentActivitySafe" :key="activity.id" class="p-4 flex items-center gap-4">
              <div class="flex-shrink-0">
                <div v-if="activity.event_type === 'open'" class="w-8 h-8 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center">
                  <EyeIcon class="w-4 h-4 text-green-600" />
                </div>
                <div v-else-if="activity.event_type === 'click'" class="w-8 h-8 bg-purple-100 dark:bg-purple-900/20 rounded-full flex items-center justify-center">
                  <CursorArrowRaysIcon class="w-4 h-4 text-purple-600" />
                </div>
                <div v-else-if="activity.event_type === 'unsubscribe'" class="w-8 h-8 bg-red-100 dark:bg-gray-900 rounded-full flex items-center justify-center">
                  <UserMinusIcon class="w-4 h-4 text-red-600" />
                </div>
                <div v-else class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900/20 rounded-full flex items-center justify-center">
                  <ExclamationTriangleIcon class="w-4 h-4 text-yellow-600" />
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ activity.subscriber.email }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ activity.event_type === 'open' ? 'Opened' : activity.event_type === 'click' ? 'Clicked link in' : activity.event_type === 'unsubscribe' ? 'Unsubscribed from' : 'Bounced from' }}
                  "{{ activity.campaign.name }}"
                </p>
              </div>
              <div class="flex-shrink-0 text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(activity.created_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
