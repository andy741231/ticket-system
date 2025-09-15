<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateAccountInformationForm from './Partials/UpdateAccountInformationForm.vue';
import DirectoryProfileInfo from './Partials/DirectoryProfileInfo.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    directoryEntry: {
        type: Object,
        default: null,
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
                    v-if="directoryEntry"
                    class="dark:bg-gray-800 dark:text-uh-cream bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DirectoryProfileInfo :team="directoryEntry" />
                </div>

                <div
                    class="dark:bg-gray-800 dark:text-uh-cream bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdateAccountInformationForm
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
