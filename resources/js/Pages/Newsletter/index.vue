<template>
    <AuthenticatedLayout>
        <Head title="Newsletter" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Latest News
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Subscribers</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats?.subscribers ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Campaigns</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats?.campaigns ?? 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Groups</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats?.groups ?? 0 }}</div>
                    </div>
                </div>

                <!-- Latest Campaigns -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium">Latest Campaigns</h3>
                        </div>
                        <div v-if="(latestCampaigns?.length || 0) > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div v-for="c in latestCampaigns" :key="c.id" class="py-3 flex items-center justify-between">
                                <div>
                                    <div class="font-medium">{{ c.name || c.subject }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(c.created_at).toLocaleString() }}</div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full"
                                      :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': c.status === 'sent',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': c.status === 'scheduled',
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': c.status !== 'sent' && c.status !== 'scheduled'
                                      }">
                                    {{ c.status }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-8">
                            <font-awesome-icon :icon="['fas', 'newspaper']" class="h-16 w-16 text-gray-400 mx-auto mb-4" />
                            <p class="text-gray-500 dark:text-gray-400">No campaigns yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    </template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    latestCampaigns: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ subscribers: 0, campaigns: 0, groups: 0 }) },
});
</script>
