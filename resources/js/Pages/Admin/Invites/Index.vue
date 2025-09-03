<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    invites: Object,
});

const resendInvite = (invite) => {
    if (confirm('Are you sure you want to resend this invitation?')) {
        router.post(route('admin.invites.resend', invite.id));
    }
};

const cancelInvite = (invite) => {
    if (confirm('Are you sure you want to cancel this invitation?')) {
        router.delete(route('admin.invites.destroy', invite.id));
    }
};

const getStatusBadge = (invite) => {
    if (invite.accepted_at) {
        return 'bg-uh-green/10 text-uh-green border-uh-green/20';
    }
    
    const expiresAt = new Date(invite.expires_at);
    const now = new Date();
    
    if (expiresAt < now) {
        return 'bg-uh-red/10 text-uh-red border-uh-red/20';
    }
    
    return 'bg-uh-gold/10 text-uh-ocher border-uh-gold/20';
};

const getStatusText = (invite) => {
    if (invite.accepted_at) {
        return 'Accepted';
    }
    
    const expiresAt = new Date(invite.expires_at);
    const now = new Date();
    
    if (expiresAt < now) {
        return 'Expired';
    }
    
    return 'Pending';
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manage Invitations" />

        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-uh-slate dark:text-uh-cream leading-tight">
                        Manage Invitations
                    </h2>
                    <p class="text-sm text-uh-gray dark:text-gray-400 mt-1">Send and track user invitations</p>
                </div>
                <Link :href="route('admin.invites.create')">
                    <PrimaryButton class="bg-uh-red hover:bg-uh-brick focus:bg-uh-chocolate active:bg-uh-chocolate focus:ring-uh-red">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Send Invitation
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-uh-gray/20">
                    <div class="p-6 text-uh-slate dark:text-uh-cream">
                        <div v-if="invites.data.length === 0" class="text-center py-12">
                            <div class="bg-uh-cream/10 dark:bg-gray-700/50 rounded-lg p-8 border border-uh-gray/20">
                                <svg class="mx-auto h-16 w-16 text-uh-gray dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-2M4 13h2m13-8l-4 4m0 0l-4-4m4 4V3" />
                                </svg>
                                <h3 class="mt-4 text-lg font-medium text-uh-slate dark:text-uh-cream">No invitations sent</h3>
                                <p class="mt-2 text-sm text-uh-gray dark:text-gray-400">Start building your team by sending your first invitation.</p>
                                <div class="mt-6">
                                    <Link :href="route('admin.invites.create')">
                                        <PrimaryButton class="bg-uh-red hover:bg-uh-brick focus:bg-uh-chocolate active:bg-uh-chocolate focus:ring-uh-red">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                            Send First Invitation
                                        </PrimaryButton>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-uh-gray/20">
                                <thead class="bg-uh-cream/10 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Email
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Role
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Invited By
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Sent
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Expires
                                        </th>
                                        <th class="px-6 py-4 text-right text-xs font-semibold text-uh-slate dark:text-uh-cream uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-uh-gray/20">
                                    <tr v-for="invite in invites.data" :key="invite.id" class="hover:bg-uh-cream/5 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-uh-slate dark:text-uh-cream">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-uh-gray dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                                </svg>
                                                {{ invite.email }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-uh-gray dark:text-gray-300">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-uh-teal/10 text-uh-green dark:bg-uh-teal/20 border border-uh-teal/20">
                                                {{ invite.role.charAt(0).toUpperCase() + invite.role.slice(1) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-uh-gray dark:text-gray-300">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border" :class="getStatusBadge(invite)">
                                                {{ getStatusText(invite) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-uh-gray dark:text-gray-300">
                                            {{ invite.invited_by?.name || 'System' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-uh-gray dark:text-gray-300">
                                            {{ formatDate(invite.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-uh-gray dark:text-gray-300">
                                            {{ formatDate(invite.expires_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <Link :href="route('admin.invites.show', invite.id)">
                                                    <SecondaryButton class="text-xs bg-white dark:bg-gray-700 border-uh-gray/30 text-uh-slate dark:text-uh-cream hover:bg-uh-cream/20 dark:hover:bg-gray-600">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        View
                                                    </SecondaryButton>
                                                </Link>
                                                
                                                <SecondaryButton 
                                                    v-if="!invite.accepted_at"
                                                    @click="resendInvite(invite)"
                                                    class="text-xs bg-uh-teal/10 border-uh-teal/30 text-uh-green hover:bg-uh-teal/20"
                                                >
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                    </svg>
                                                    Resend
                                                </SecondaryButton>
                                                
                                                <DangerButton 
                                                    v-if="!invite.accepted_at"
                                                    @click="cancelInvite(invite)"
                                                    class="text-xs bg-uh-red/10 border-uh-red/30 text-uh-red hover:bg-uh-red/20"
                                                >
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                    Cancel
                                                </DangerButton>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div v-if="invites.links.length > 3" class="mt-6 flex justify-between items-center pt-4 border-t border-uh-gray/20">
                                <div class="text-sm text-uh-gray dark:text-gray-400">
                                    Showing {{ invites.from }} to {{ invites.to }} of {{ invites.total }} results
                                </div>
                                <div class="flex space-x-1">
                                    <Link
                                        v-for="link in invites.links"
                                        :key="link.label"
                                        :href="link.url"
                                        :class="[
                                            'px-3 py-2 text-sm border rounded transition-colors',
                                            link.active 
                                                ? 'bg-uh-red text-white border-uh-red' 
                                                : 'bg-white dark:bg-gray-700 text-uh-slate dark:text-uh-cream border-uh-gray/30 hover:bg-uh-cream/20 dark:hover:bg-gray-600'
                                        ]"
                                        v-html="link.label"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
