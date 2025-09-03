<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <GuestLayout>
        <Head title="Invitation Error" />

        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                Invitation Error
            </h1>

            <p class="text-gray-600 mb-6">
                {{ message }}
            </p>

            <div class="space-y-3">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    Go to Login
                </Link>

                <p class="text-sm text-gray-500">
                    If you believe this is an error, please contact your administrator.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
