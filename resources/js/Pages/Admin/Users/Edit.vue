<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { computed } from 'vue';

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
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    roles: props.user.roles || [],
});

const page = usePage();
const isCurrentUser = computed(() => page.props.auth.user?.id === props.user.id);

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
                            <div>
                                <InputLabel for="name" value="Name" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
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
                                <div class="mt-2 space-y-2">
                                    <div v-for="role in roles" :key="role.id" class="flex items-center">
                                        <Checkbox 
                                            :id="`role_${role.id}`" 
                                            :value="role.id" 
                                            v-model:checked="form.roles"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <label 
                                            :for="`role_${role.id}`" 
                                            class="ml-2 block text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            {{ role.name }}
                                        </label>
                                    </div>
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
                                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
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
