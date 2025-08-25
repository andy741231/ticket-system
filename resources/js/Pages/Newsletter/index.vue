<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
  EnvelopeIcon, 
  UserGroupIcon, 
  DocumentTextIcon,
  ChartBarIcon,
  PlusIcon
} from '@heroicons/vue/24/outline';

// Mock data for dashboard stats - will be replaced with real data from backend
const stats = [
  { name: 'Total Subscribers', value: '2,543', icon: UserGroupIcon, color: 'text-blue-600' },
  { name: 'Active Campaigns', value: '12', icon: EnvelopeIcon, color: 'text-green-600' },
  { name: 'Templates', value: '8', icon: DocumentTextIcon, color: 'text-purple-600' },
  { name: 'This Month Opens', value: '89.2%', icon: ChartBarIcon, color: 'text-orange-600' },
];

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
      <div class="flex justify-between items-center">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Newsletter Dashboard
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage your email campaigns, subscribers, and analytics
          </p>
        </div>
        <Link
          :href="route('newsletter.campaigns.create')"
          class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <PlusIcon class="w-4 h-4 mr-2" />
          New Campaign
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="stat in stats"
            :key="stat.name"
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6"
          >
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <component :is="stat.icon" :class="`w-8 h-8 ${stat.color}`" />
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                  {{ stat.name }}
                </p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                  {{ stat.value }}
                </p>
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
            <!-- Placeholder for recent campaigns - will be populated with real data -->
            <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
              <div class="flex items-center space-x-4">
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                  <EnvelopeIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    Weekly Newsletter #42
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    Sent to 2,543 subscribers • 2 hours ago
                  </p>
                </div>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium text-green-600 dark:text-green-400">
                  89.2% opened
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  23.4% clicked
                </p>
              </div>
            </div>

            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
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