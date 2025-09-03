<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useHasAny } from '@/Extensions/useAuthz';
import { computed } from 'vue';
import ReadOnlyBanner from '@/Components/ReadOnlyBanner.vue';
import RbacRoleBadge from '@/Components/Rbac/RbacRoleBadge.vue';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    username: '',
    email: '',
    roles: [],
    expires_in_hours: 72,
});

const canManageRbacRoles = useHasAny(['admin.rbac.roles.manage']);

// Group roles by application for clearer UI
const groupedRoles = computed(() => {
    const groups = new Map();
    (props.roles || []).forEach((r) => {
        const key = r.app_slug || 'other';
        if (!groups.has(key)) {
            groups.set(key, { app_slug: key, app_name: r.app_name || 'Other', roles: [] });
        }
        groups.get(key).roles.push(r);
    });
    // Maintain backend sort, but ensure group ordering by app_name as fallback
    return Array.from(groups.values()).sort((a, b) => (a.app_name || '').localeCompare(b.app_name || ''));
});

// Live preview of selected roles using role badges (mirror Users/Edit.vue)
const selectedRoles = computed(() => {
    const selected = new Set((form.roles || []).map((v) => Number(v)));
    return (props.roles || []).filter((r) => selected.has(Number(r.id)));
});

const submit = () => {
    form.post(route('admin.invites.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Send Invitation" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Send Invitation
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-uh-gray/20">
                    <div class="p-8 text-uh-slate dark:text-uh-cream">
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-cream mb-2">Invitation Details</h3>
                            <p class="text-sm text-uh-gray dark:text-gray-400">Send an invitation to join your organization</p>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="bg-uh-cream/10 dark:bg-gray-700/50 p-6 rounded-lg border border-uh-gray/20">
                                <h4 class="text-md font-medium text-uh-slate dark:text-uh-cream mb-4">User Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="name" value="Full Name" class="text-uh-slate dark:text-uh-cream" />
                                        <TextInput
                                            id="name"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 border-uh-gray/30 focus:border-uh-red focus:ring-uh-red"
                                            v-model="form.name"
                                            autofocus
                                            placeholder="Enter full name"
                                        />
                                        <InputError class="mt-2" :message="form.errors.name" />
                                    </div>

                                    <div>
                                        <InputLabel for="username" value="Username" class="text-uh-slate dark:text-uh-cream" />
                                        <TextInput
                                            id="username"
                                            type="text"
                                            class="mt-1 block w-full dark:bg-gray-700 border-uh-gray/30 focus:border-uh-red focus:ring-uh-red"
                                            v-model="form.username"
                                            placeholder="Enter username"
                                        />
                                        <p class="mt-1 text-xs text-uh-gray dark:text-gray-400">Optional - will be auto-generated if left blank</p>
                                        <InputError class="mt-2" :message="form.errors.username" />
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-700/50 p-6 rounded-lg border border-uh-gray/20">
                                <h4 class="text-md font-medium text-uh-slate dark:text-uh-cream mb-4">Contact & Access</h4>
                                <div class="space-y-4">
                                    <div>
                                        <InputLabel for="email" value="Email Address" class="text-uh-slate dark:text-uh-cream" />
                                        <TextInput
                                            id="email"
                                            type="email"
                                            class="mt-1 block w-full dark:bg-gray-700 border-uh-gray/30 focus:border-uh-red focus:ring-uh-red"
                                            v-model="form.email"
                                            required
                                            autocomplete="email"
                                            placeholder="user@example.com"
                                        />
                                        <p class="mt-1 text-xs text-uh-gray dark:text-gray-400">
                                            <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                            </svg>
                                            Invitation will be sent to this email address
                                        </p>
                                        <InputError class="mt-2" :message="form.errors.email" />
                                    </div>

                                </div>
                            </div>

                            <!-- Roles -->
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                                <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Roles & Permissions</h4>
                                <InputLabel value="Roles" />
                                <ReadOnlyBanner v-if="!canManageRbacRoles" title="Read-only" message="You do not have permission to manage roles." />
                                <div class="mt-3 space-y-4">
                                    <div v-for="group in groupedRoles" :key="group.app_slug" class="rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                        <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                            {{ group.app_name }}
                                        </div>
                                        <div class="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <div v-for="role in group.roles" :key="role.id" class="flex items-center">
                                                <Checkbox 
                                                    :id="`role_${role.id}`" 
                                                    :value="role.id" 
                                                    v-model:checked="form.roles"
                                                    :disabled="!canManageRbacRoles"
                                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded dark:bg-gray-700"
                                                />
                                                <label 
                                                    :for="`role_${role.id}`" 
                                                    class="ml-2 block text-sm text-gray-700 dark:text-gray-300"
                                                >
                                                    {{ role.name }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Selected roles preview -->
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <RbacRoleBadge
                                      v-for="r in selectedRoles"
                                      :key="r.id"
                                      :name="r.name"
                                      size="sm"
                                    />
                                    <span v-if="selectedRoles.length === 0" class="text-xs text-gray-500 dark:text-gray-400">No roles selected</span>
                                </div>
                                <p class="mt-2 text-xs text-uh-gray dark:text-gray-400">
                                    <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                    </svg>
                                    Selected roles will be assigned upon account creation
                                </p>
                                <InputError class="mt-2" :message="form.errors.roles" />

                                <div class="mt-6">
                                    <InputLabel for="expires_in_hours" value="Expiration" class="text-uh-slate dark:text-uh-cream" />
                                    <div class="mt-1 relative">
                                        <TextInput
                                            id="expires_in_hours"
                                            type="number"
                                            class="block w-full dark:bg-gray-700 border-uh-gray/30 focus:border-uh-red focus:ring-uh-red pr-12"
                                            v-model="form.expires_in_hours"
                                            min="1"
                                            max="168"
                                            required
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-uh-gray dark:text-gray-400 text-sm">hours</span>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-uh-gray dark:text-gray-400">
                                        <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                        Valid for 1-168 hours (default: 72 hours)
                                    </p>
                                    <InputError class="mt-2" :message="form.errors.expires_in_hours" />
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-6 border-t border-uh-gray/20">
                                <Link :href="route('admin.invites.index')" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-uh-gray/30 rounded-md font-semibold text-xs text-uh-slate dark:text-uh-cream uppercase tracking-widest hover:bg-uh-cream/20 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-uh-red focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    Cancel
                                </Link>

                                <PrimaryButton
                                    type="submit"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                    class="bg-uh-red hover:bg-uh-brick focus:bg-uh-chocolate active:bg-uh-chocolate focus:ring-uh-red"
                                >
                                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    {{ form.processing ? 'Sending...' : 'Send Invitation' }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
