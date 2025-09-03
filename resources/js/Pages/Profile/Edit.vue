<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
        </template>

        <div class="">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8  space-y-6">
                <div
                    class="dark:bg-gray-800 dark:text-uh-cream bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl dark:bg-gray-800 dark:text-uh-cream"
                    />
                </div>

                <div
                    class="dark:bg-gray-800 dark:text-uh-cream bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl dark:bg-gray-800 dark:text-uh-cream" />
                </div>

                <div
                    class="dark:bg-gray-800 dark:text-uh-cream bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl dark:bg-gray-800 dark:text-uh-cream" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
