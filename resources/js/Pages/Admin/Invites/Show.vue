<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    invite: Object,
});

const resendInvite = () => {
    if (confirm('Are you sure you want to resend this invitation?')) {
        router.post(route('admin.invites.resend', props.invite.id));
    }
};

const cancelInvite = () => {
    if (confirm('Are you sure you want to cancel this invitation?')) {
        router.delete(route('admin.invites.destroy', props.invite.id));
    }
};

const getStatusBadge = () => {
    if (props.invite.accepted_at) {
        return 'bg-green-100 text-green-800';
    }
    
    const expiresAt = new Date(props.invite.expires_at);
    const now = new Date();
    
    if (expiresAt < now) {
        return 'bg-red-100 text-red-800';
    }
    
    return 'bg-yellow-100 text-yellow-800';
};

const getStatusText = () => {
    if (props.invite.accepted_at) {
        return 'Accepted';
    }
    
    const expiresAt = new Date(props.invite.expires_at);
    const now = new Date();
    
    if (expiresAt < now) {
        return 'Expired';
    }
    
    return 'Pending';
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const copyInviteLink = () => {
    const inviteUrl = route('invites.accept', props.invite.token);
    navigator.clipboard.writeText(inviteUrl).then(() => {
        alert('Invite link copied to clipboard!');
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Invitation Details" />

        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Invitation Details
                </h2>
                <Link :href="route('admin.invites.index')">
                    <SecondaryButton>
                        Back to Invitations
                    </SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-900">Basic Information</h3>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ invite.email }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Role</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ invite.role.charAt(0).toUpperCase() + invite.role.slice(1) }}
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusBadge()">
                                            {{ getStatusText() }}
                                        </span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Invited By</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ invite.invited_by?.name || 'System' }}</dd>
                                </div>
                            </div>

                            <!-- Timestamps -->
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-900">Timeline</h3>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Sent</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(invite.created_at) }}</dd>
                                </div>

                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Expires</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(invite.expires_at) }}</dd>
                                </div>

                                <div v-if="invite.accepted_at">
                                    <dt class="text-sm font-medium text-gray-500">Accepted</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(invite.accepted_at) }}</dd>
                                </div>
                            </div>
                        </div>

                        <!-- Invite Link -->
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Invitation Link</h4>
                            <div class="flex items-center space-x-2">
                                <input
                                    type="text"
                                    :value="route('invites.accept', invite.token)"
                                    readonly
                                    class="flex-1 text-sm bg-white border border-gray-300 rounded px-3 py-2"
                                />
                                <SecondaryButton @click="copyInviteLink" class="text-xs">
                                    Copy
                                </SecondaryButton>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-8 flex items-center justify-end space-x-3">
                            <SecondaryButton 
                                v-if="!invite.accepted_at"
                                @click="resendInvite"
                            >
                                Resend Invitation
                            </SecondaryButton>
                            
                            <DangerButton 
                                v-if="!invite.accepted_at"
                                @click="cancelInvite"
                            >
                                Cancel Invitation
                            </DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
