<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ClockIcon, UserGroupIcon, TicketIcon, ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/outline/index.js';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useHasAny } from '@/Extensions/useAuthz';

// Create reactive stats object
const stats = ref({
    open_tickets: 0,
    open_tickets_change: 0,
    in_progress_tickets: 0,
    total_users: 0,
});

// Loading state
const isLoading = ref(true);
const error = ref(null);

// Fetch stats from API
const fetchStats = async () => {
    try {
        isLoading.value = true;
        error.value = null;
        const response = await axios.get('/api/dashboard-stats');
        stats.value = response.data.stats;
        console.log('Fetched stats:', stats.value);
    } catch (err) {
        console.error('Error fetching stats:', err);
        error.value = 'Failed to load dashboard statistics. Please try again later.';
    } finally {
        isLoading.value = false;
    }
};

// Fetch stats when component is mounted
onMounted(() => {
    console.log('Dashboard mounted, fetching stats...');
    fetchStats();
});

// Permission-based UI gating
const canManageUsers = useHasAny(['hub.user.manage']);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            
        </template>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Welcome Banner -->
            <div class="bg-uh-red/80 text-white rounded-lg shadow-md mb-8 p-6">
                <h1 class="text-2xl font-bold mb-2">Welcome {{ $page.props.auth.user.name }},</h1>
                <p class="text-uh-cream">Manage and track all support tickets in one place</p>
                <!-- TODO: Add health related trivia -->
            </div>

            <!-- Action Buttons --> 
            <div class="flex space-x-4 mb-8">
                <Link
                    :href="route('tickets.index')"
                    class="inline-flex items-center px-4 py-2 bg-uh-teal text-white border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-uh-teal-dark focus:outline-none focus:border-uh-teal-dark focus:ring focus:ring-uh-teal-light disabled:opacity-25 transition"
                >
                    View Tickets
                </Link>
                <Link
                    v-if="canManageUsers"
                    :href="route('admin.users.index')"
                    class="inline-flex items-center px-4 py-2 bg-uh-brick text-white border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-uh-brick-dark focus:outline-none focus:border-uh-brick-dark focus:ring focus:ring-uh-brick-light disabled:opacity-25 transition"
                >
                    Manage Users
                </Link>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-5 mt-6 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Open Tickets -->
                <div class="bg-uh-slate/5 dark:bg-gray-600/50 text-uh-slate dark:text-uh-cream overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-uh-teal/10 p-3 rounded-md">
                                <TicketIcon class="h-6 w-6 text-uh-teal" aria-hidden="true" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium truncate">
                                        New tickets
                                    </dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold">{{ stats.open_tickets }}</div>
                                        <div v-if="stats.open_tickets_change !== 0" class="ml-2 flex items-baseline text-sm font-semibold" :class="stats.open_tickets_change >= 0 ? 'text-uh-green' : 'text-red-500'">
                                            {{ Math.abs(stats.open_tickets_change) }}%
                                            <ArrowUpIcon v-if="stats.open_tickets_change >= 0" class="h-4 w-4" />
                                            <ArrowDownIcon v-else class="h-4 w-4" />
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- In Progress -->
                <div class="bg-uh-slate/5 dark:bg-gray-600/50 text-uh-slate dark:text-uh-cream overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-uh-gold/10 p-3 rounded-md">
                                <ClockIcon class="h-6 w-6 text-uh-gold" aria-hidden="true" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium truncate">
                                        Tickets approved
                                    </dt>
                                    <dd class="text-2xl font-semibold">{{ stats.in_progress_tickets }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Users -->
                <div class="bg-uh-slate/5 dark:bg-gray-600/50 text-uh-slate dark:text-uh-cream overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-uh-red/10 p-3 rounded-md">
                                <UserGroupIcon class="h-6 w-6" aria-hidden="true" />
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium truncate">
                                        Total Users
                                    </dt>
                                    <dd class="text-2xl font-semibold">{{ stats.total_users }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="mt-8 p-6 text-center">
                <div class="inline-flex items-center px-4 py-2 font-semibold leading-6 text-sm shadow rounded-md text-white bg-uh-red transition ease-in-out duration-150 cursor-not-allowed">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading dashboard data...
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="mt-8 bg-red-50 border-l-4 border-red-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            {{ error }}
                            <button @click="fetchStats" class="ml-2 text-sm font-medium text-red-700 hover:text-red-600 underline">
                                Try again
                            </button>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <!--<div v-else class="mt-8 bg-uh-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-lg font-medium leading-6 text-uh-slate">Recent Activity</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-uh-slate">Your recent support tickets and activities will appear here.</p>
                </div>
            </div>-->
        </div>
    </AuthenticatedLayout>
</template>