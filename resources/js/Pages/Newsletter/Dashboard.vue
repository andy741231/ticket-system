<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
  EnvelopeIcon, 
  UserGroupIcon, 
  DocumentTextIcon,
  ChartBarIcon,
  PlusIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  overview: {
    type: Object,
    default: () => ({}),
  },
  campaigns: {
    type: Array,
    default: () => [],
  },
  subscribers: {
    type: Object,
    default: () => ({}),
  },
  templates: {
    type: Array,
    default: () => [],
  },
});

// Dashboard stats computed from props - using same structure as Analytics
const dashboardStats = computed(() => {
  const activeCampaignsCount = props.campaigns?.filter(c => ['active', 'sending', 'scheduled'].includes(c.status))?.length || 0;
  const templatesCount = props.templates?.length || 0;
  
  return [
    { 
      name: 'Total Subscribers', 
      value: props.overview?.total_subscribers?.toLocaleString() || props.subscribers?.total?.toLocaleString() || '0', 
      icon: UserGroupIcon, 
      color: 'text-blue-600',
      change: props.overview?.subscribers_change || 0
    },
    { 
      name: 'Active Campaigns', 
      value: activeCampaignsCount.toString(), 
      icon: EnvelopeIcon, 
      color: 'text-green-600',
      change: props.overview?.campaigns_change || 0
    },
    { 
      name: 'Templates', 
      value: templatesCount.toString(), 
      icon: DocumentTextIcon, 
      color: 'text-purple-600' 
    },
    { 
      name: 'This Month Opens', 
      value: `${props.overview?.avg_open_rate || 0}%`, 
      icon: ChartBarIcon, 
      color: 'text-orange-600',
      change: props.overview?.opens_change || 0
    },
  ];
});

// Helper function to format time ago
function formatTimeAgo(dateString) {
  if (!dateString) return 'Unknown';
  const date = new Date(dateString);
  const now = new Date();
  const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));
  
  if (diffInHours < 1) return 'Just now';
  if (diffInHours < 24) return `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
  
  const diffInDays = Math.floor(diffInHours / 24);
  if (diffInDays < 7) return `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
  
  return date.toLocaleDateString();
}

const quickActions = [
  {
    name: 'Create Campaign',
    description: 'Design and send a new newsletter campaign',
    href: '/newsletter/campaigns/create',
    icon: EnvelopeIcon,
    color: 'bg-blue-500 hover:bg-blue-600'
  },
  {
    name: 'Manage Subscribers',
    description: 'Add, edit, or organize your subscriber lists',
    href: '/newsletter/subscribers',
    icon: UserGroupIcon,
    color: 'bg-green-500 hover:bg-green-600'
  },
  {
    name: 'Email Templates',
    description: 'Create and manage reusable email templates',
    href: '/newsletter/templates',
    icon: DocumentTextIcon,
    color: 'bg-purple-500 hover:bg-purple-600'
  },
  {
    name: 'Analytics',
    description: 'View detailed campaign performance metrics',
    href: '/newsletter/analytics',
    icon: ChartBarIcon,
    color: 'bg-orange-500 hover:bg-orange-600'
  },
];
</script>

<template>
  <Head title="Newsletter Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      
    </template>

    <div class="">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <div class="flex justify-between items-center">
        <div>
        
        </div>
        <Link
          :href="route('newsletter.campaigns.create')"
          class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <PlusIcon class="w-4 h-4 mr-2" />
          New Campaign
        </Link>
      </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="stat in dashboardStats"
            :key="stat.name"
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6"
          >
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <component :is="stat.icon" :class="`w-8 h-8 ${stat.color}`" />
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
                    <ArrowTrendingUpIcon v-if="stat.change > 0" class="w-4 h-4 text-green-600" />
                    <ArrowTrendingDownIcon v-else-if="stat.change < 0" class="w-4 h-4 text-red-600" />
                    <span :class="stat.change > 0 ? 'text-green-600' : stat.change < 0 ? 'text-red-600' : 'text-gray-500'">
                      {{ Math.abs(stat.change) }}%
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
            Quick Actions
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <Link
              v-for="action in quickActions"
              :key="action.name"
              :href="action.href"
              class="group relative bg-white dark:bg-gray-700 p-6 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 transition-colors duration-200"
            >
              <div>
                <span :class="`inline-flex p-3 rounded-lg text-white ${action.color} group-hover:scale-110 transition-transform duration-200`">
                  <component :is="action.icon" class="w-6 h-6" />
                </span>
              </div>
              <div class="mt-4">
                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors duration-200">
                  {{ action.name }}
                </h4>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                  {{ action.description }}
                </p>
              </div>
              <span class="absolute top-6 right-6 text-gray-300 dark:text-gray-600 group-hover:text-gray-400 dark:group-hover:text-gray-500 transition-colors duration-200">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
              </span>
            </Link>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Recent Activity
            </h3>
            <Link
              :href="route('newsletter.campaigns.index')"
              class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300"
            >
              View all campaigns →
            </Link>
          </div>
          
          <div class="space-y-4">
            <div v-if="campaigns.length > 0">
              <div 
                v-for="campaign in campaigns.slice(0, 3)" 
                :key="campaign.id"
                class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                <div class="flex items-center space-x-4">
                  <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                    <EnvelopeIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      {{ campaign.name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      Sent to {{ campaign.total_recipients?.toLocaleString() || 0 }} subscribers • {{ formatTimeAgo(campaign.sent_at) }}
                    </p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-green-600 dark:text-green-400">
                    {{ campaign.open_rate || 0 }}% opened
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ campaign.click_rate || 0 }}% clicked
                  </p>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
              <EnvelopeIcon class="w-12 h-12 mx-auto mb-4 opacity-50" />
              <p>No recent campaigns</p>
              <p class="text-sm mt-1">Create your first campaign to get started</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
