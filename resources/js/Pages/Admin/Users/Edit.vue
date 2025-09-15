<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import RbacRoleBadge from '@/Components/Rbac/RbacRoleBadge.vue';
import { computed } from 'vue';
import { useHasAny } from '@/Extensions/useAuthz';
import ReadOnlyBanner from '@/Components/ReadOnlyBanner.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    email: props.user.email,
    password: '',
    password_confirmation: '',
    // Normalize to array of role IDs (supports array of IDs or array of objects)
    roles: Array.isArray(props.user.roles)
        ? props.user.roles.map((r) => typeof r === 'object' && r !== null ? Number(r.id) : Number(r)).filter((v) => !Number.isNaN(v))
        : [],
});

const page = usePage();
const isCurrentUser = computed(() => page.props.auth.user?.id === props.user.id);

// Live preview of selected roles using role badges
const selectedRoles = computed(() => {
    const selected = new Set((form.roles || []).map((v) => Number(v)));
    return (props.roles || []).filter((r) => selected.has(Number(r.id)));
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
    return Array.from(groups.values()).sort((a, b) => (a.app_name || '').localeCompare(b.app_name || ''));
});

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="`Edit User: ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Name -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="first_name" value="First name" />
                                    <TextInput
                                        id="first_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.first_name"
                                        required
                                        autofocus
                                        autocomplete="given-name"
                                    />
                                    <InputError class="mt-2" :message="form.errors.first_name" />
                                </div>
                                <div>
                                    <InputLabel for="last_name" value="Last name" />
                                    <TextInput
                                        id="last_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.last_name"
                                        required
                                        autocomplete="family-name"
                                    />
                                    <InputError class="mt-2" :message="form.errors.last_name" />
                                </div>
                            </div>

                            <!-- Username (read-only) -->
                            <div>
                                <InputLabel for="username" value="Username" />
                                <input
                                    id="username"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :value="user.username"
                                    readonly
                                    disabled
                                    autocomplete="username"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Username is managed by administrators and cannot be changed here.</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <InputLabel for="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <!-- Password (optional) -->
                            <div>
                                <InputLabel for="password" value="New Password (leave blank to keep current)" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    v-model="form.password"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <InputLabel for="password_confirmation" value="Confirm New Password" />
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    class="mt-1 block w-full"
                                    v-model="form.password_confirmation"
                                    :disabled="!form.password"
                                    autocomplete="new-password"
                                />
                                <InputError class="mt-2" :message="form.errors.password_confirmation" />
                            </div>

                            <!-- Roles -->
                            <div v-if="!isCurrentUser">
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
                                <p v-if="isCurrentUser" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    You cannot modify your own roles.
                                </p>
                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>

                            <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Last updated: {{ new Date(user.updated_at).toLocaleString() }}
                                </div>
                                <div class="flex items-center space-x-4">
                                    <Link 
                                        :href="route('admin.users.index')" 
                                        class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                    >
                                        Cancel
                                    </Link>
                                    <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                        Update User
                                    </PrimaryButton>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
