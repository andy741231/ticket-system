<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: {
        type: String,
        default: 'An error occurred during authentication'
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex items-center justify-center px-4">
        <Head title="Authentication Error" />
        
        <div class="max-w-md w-full">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center">
                <div class="mb-6">
                    <div class="mx-auto w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-3xl text-red-600 dark:text-red-400"></i>
                    </div>
                </div>
                
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    Authentication Error
                </h1>
                
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    {{ message }}
                </p>
                
                <div class="space-y-3">
                    <button
                        @click="$inertia.visit('/')"
                        class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md font-medium transition-colors"
                    >
                        Go to Home
                    </button>
                    
                    <button
                        @click="window.history.back()"
                        class="w-full px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md font-medium transition-colors"
                    >
                        Go Back
                    </button>
                </div>
                
                <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                    If you believe this is an error, please contact the person who shared this link with you.
                </p>
            </div>
        </div>
    </div>
</template>
