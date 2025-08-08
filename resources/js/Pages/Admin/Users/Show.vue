<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    tickets: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const formatDate = (dateString) => {
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

const getStatusBadgeClass = (status) => {
    switch (status.toLowerCase()) {
        case 'open':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'resolved':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
        case 'closed':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
        default:
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    }
};

const getPriorityBadgeClass = (priority) => {
    switch (priority.toLowerCase()) {
        case 'high':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        case 'low':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    }
};
</script>

<template>
    <Head :title="`User: ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    User: {{ user.name }}
                </h2>
                <div class="flex space-x-2">
                    <Link 
                        v-if="can.update" 
                        :href="route('admin.users.edit', user.id)" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 dark:hover:bg-blue-400 focus:bg-blue-700 dark:focus:bg-blue-400 active:bg-blue-800 dark:active:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Edit User
                    </Link>
                    <Link 
                        v-if="can.delete && $page.props.auth.user.id !== user.id" 
                        :href="route('admin.users.destroy', user.id)" 
                        method="delete"
                        as="button"
                        class="inline-flex items-center px-4 py-2 bg-red-600 dark:bg-red-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 dark:hover:bg-red-400 focus:bg-red-700 dark:focus:bg-red-400 active:bg-red-800 dark:active:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                        onclick="return confirm('Are you sure you want to delete this user?')"
                    >
                        Delete User
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- User Details -->
                    <div class="md:col-span-1">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex flex-col items-center">
                                    <div class="h-24 w-24 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-4xl font-bold text-indigo-600 dark:text-indigo-300 mb-4">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ user.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    
                                    <div class="mt-4 w-full">
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Roles</h4>
                                        <div class="space-y-1">
                                            <div 
                                                v-for="role in user.roles" 
                                                :key="role.id"
                                                class="px-2 py-1 text-xs font-medium rounded-full text-center"
                                                :class="{
                                                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': role.name === 'admin',
                                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': role.name === 'user',
                                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': !['admin', 'user'].includes(role.name)
                                                }"
                                            >
                                                {{ role.name }}
                                            </div>
                                            <div 
                                                v-if="user.roles.length === 0"
                                                class="text-sm text-gray-500 dark:text-gray-400 text-center"
                                            >
                                                No roles assigned
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-6 w-full">
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Account Details</h4>
                                        <dl class="space-y-2">
                                            <div class="flex justify-between">
                                                <dt class="text-sm text-gray-500 dark:text-gray-400">Member since</dt>
                                                <dd class="text-sm text-gray-900 dark:text-white">{{ formatDate(user.created_at) }}</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm text-gray-500 dark:text-gray-400">Last updated</dt>
                                                <dd class="text-sm text-gray-900 dark:text-white">{{ formatDate(user.updated_at) }}</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm text-gray-500 dark:text-gray-400">Email verified</dt>
                                                <dd class="text-sm text-gray-900 dark:text-white">
                                                    <span v-if="user.email_verified_at" class="text-green-600 dark:text-green-400">
                                                        {{ formatDate(user.email_verified_at) }}
                                                    </span>
                                                    <span v-else class="text-red-600 dark:text-red-400">
                                                        Not verified
                                                    </span>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Tickets -->
                    <div class="md:col-span-2">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Tickets</h3>
                                    <Link 
                                        :href="route('tickets.create', { user_id: user.id })" 
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        Create Ticket for User
                                    </Link>
                                </div>

                                <div v-if="tickets.length > 0" class="space-y-4">
                                    <div 
                                        v-for="ticket in tickets" 
                                        :key="ticket.id"
                                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1 min-w-0">
                                                <Link 
                                                    :href="route('tickets.show', ticket.id)" 
                                                    class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 truncate block"
                                                >
                                                    #{{ ticket.id }} - {{ ticket.title }}
                                                </Link>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                    {{ ticket.description.replace(/<[^>]*>?/gm, '').substring(0, 100) }}{{ ticket.description.length > 100 ? '...' : '' }}
                                                </p>
                                            </div>
                                            <div class="flex items-center space-x-2 ml-4">
                                                <span 
                                                    class="px-2 py-1 text-xs rounded-full"
                                                    :class="getStatusBadgeClass(ticket.status)"
                                                >
                                                    {{ ticket.status.replace('_', ' ') }}
                                                </span>
                                                <span 
                                                    class="px-2 py-1 text-xs rounded-full"
                                                    :class="getPriorityBadgeClass(ticket.priority)"
                                                >
                                                    {{ ticket.priority }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-2 flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                            <span>Created: {{ formatDate(ticket.created_at) }}</span>
                                            <span>Updated: {{ formatDate(ticket.updated_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tickets yet</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This user hasn't created any support tickets yet.</p>
                                    <div class="mt-6">
                                        <Link 
                                            :href="route('tickets.create', { user_id: user.id })" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                            </svg>
                                            Create Ticket
                                        </Link>
                                    </div>
                                </div>

                                <div v-if="tickets.length > 0" class="mt-6 text-center">
                                    <Link 
                                        :href="route('tickets.index', { user_id: user.id })" 
                                        class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300"
                                    >
                                        View all tickets by this user →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
