<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useHasAny } from '@/Extensions/useAuthz';
import RbacRoleBadge from '@/Components/Rbac/RbacRoleBadge.vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
});

const canManageUsers = useHasAny(['hub.user.manage']);

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
            
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="flex justify-between items-center mb-4">
          <div></div>
                <Link v-if="canManageUsers" :href="route('admin.users.create')" as="button">
                    <PrimaryButton>
                        <font-awesome-icon icon="plus" class="h-5 w-5 mr-1" />
                        New User
                    </PrimaryButton>
                </Link>
            </div>

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
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="(users?.data?.length || 0) === 0">
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                            No users found.
                                        </td>
                                    </tr>
                                     <tr v-for="user in (users?.data || [])" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link 
                                                :href="route('admin.users.show', user.id)" 
                                                class="block text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                title="View"
                                            >
                                                <div class="flex items-center">
                                                    <Avatar :user="user" size="md" :show-link="false" />
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                            {{ user.name }}
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            {{ user.email }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Link 
                                                :href="route('admin.users.show', user.id)" 
                                                class="block text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                title="View"
                                            >
                                                <div class="flex flex-wrap gap-1">
                                                    <RbacRoleBadge
                                                      v-for="role in user.roles"
                                                      :key="role.id ?? role.name"
                                                      :name="role.name"
                                                      size="sm"
                                                    />
                                                    <span
                                                      v-if="user.roles.length === 0"
                                                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                                                    >
                                                      No roles assigned
                                                    </span>
                                                </div>
                                            </Link>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <Link 
                                                :href="route('admin.users.show', user.id)" 
                                                class="block text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                title="View"
                                            >
                                                {{ formatDate(user.created_at) }}
                                            </Link>
                                        </td>
                                       
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div v-if="(users?.links?.length || 0) > 3" class="mt-4 px-6 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <span class="font-medium">{{ users?.from ?? 0 }}</span> to <span class="font-medium">{{ users?.to ?? 0 }}</span> of <span class="font-medium">{{ users?.total ?? 0 }}</span> users
                                </div>
                                <div class="flex space-x-2">
                                    <template v-for="(link, index) in (users?.links || [])" :key="index">
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
