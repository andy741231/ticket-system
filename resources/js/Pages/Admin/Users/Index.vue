<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth.user.roles?.includes('admin') || false);

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
</script>

<template>
    <Head title="Manage Users" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                </h2>
                <Link v-if="isSuperAdmin" :href="route('admin.users.create')" as="button">
                    <PrimaryButton>
                        Create New User
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            User
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Roles
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Created
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="users.data.length === 0">
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                            No users found.
                                        </td>
                                    </tr>
                                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                                                    <span class="text-indigo-600 dark:text-indigo-300 font-medium">
                                                        {{ user.name.charAt(0) }}
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ user.name }}
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ user.email }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-1">
                                                <span 
                                                    v-for="role in user.roles" 
                                                    :key="role.id"
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                    :class="{
                                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': role.name === 'admin',
                                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': role.name === 'user',
                                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': !['admin', 'user'].includes(role.name)
                                                    }"
                                                >
                                                    {{ role.name }}
                                                </span>
                                                <span 
                                                    v-if="user.roles.length === 0"
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                                                >
                                                    No roles assigned
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(user.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end space-x-2">
                                                <Link 
                                                    :href="route('admin.users.show', user.id)" 
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                    title="View"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </Link>
                                                <Link 
                                                    v-if="isSuperAdmin"
                                                    :href="route('admin.users.edit', user.id)" 
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                    title="Edit"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div v-if="users.links.length > 3" class="mt-4 px-6 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <span class="font-medium">{{ users.from }}</span> to <span class="font-medium">{{ users.to }}</span> of <span class="font-medium">{{ users.total }}</span> users
                                </div>
                                <div class="flex space-x-2">
                                    <template v-for="(link, index) in users.links" :key="index">
                                        <Link 
                                            v-if="link.url"
                                            :href="link.url"
                                            v-html="link.label"
                                            :class="{
                                                'px-4 py-2 text-sm font-medium rounded-md': true,
                                                'bg-indigo-600 text-white': link.active,
                                                'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700': !link.active && link.url,
                                                'text-gray-400 dark:text-gray-500 cursor-not-allowed': !link.url,
                                            }"
                                            preserve-scroll
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
