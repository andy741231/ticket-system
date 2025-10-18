<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    imageId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['close', 'success']);

const name = ref('');
const email = ref('');
const loading = ref(false);
const error = ref(null);
const success = ref(false);

const isValid = computed(() => {
    return name.value.trim().length >= 2 && 
           email.value.trim().length > 0 && 
           /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
});

const requestVerification = async () => {
    if (!isValid.value) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/external-auth/annotations/${props.imageId}/request-verification`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                name: name.value.trim(),
                email: email.value.trim()
            })
        });

        const data = await response.json();

        if (response.ok) {
            success.value = true;
            setTimeout(() => {
                emit('success');
                emit('close');
            }, 3000);
        } else {
            error.value = data.message || 'Failed to send verification email';
        }
    } catch (err) {
        console.error('Error requesting verification:', err);
        error.value = 'An error occurred. Please try again.';
    } finally {
        loading.value = false;
    }
};

const close = () => {
    if (!loading.value) {
        emit('close');
    }
};
</script>

<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
            <div class="flex min-h-screen items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 transition-opacity" @click="close"></div>

                <!-- Modal -->
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                    <!-- Close button -->
                    <button
                        v-if="!loading"
                        @click="close"
                        class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <i class="fas fa-times text-xl"></i>
                    </button>

                    <!-- Success State -->
                    <div v-if="success" class="text-center py-4">
                        <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-check text-3xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                            Check Your Email!
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            We've sent a verification link to <strong>{{ email }}</strong>. 
                            Click the link to access the annotation.
                        </p>
                    </div>

                    <!-- Login Form -->
                    <div v-else>
                        <div class="text-center mb-6">
                            <div class="mx-auto w-16 h-16 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-user-check text-3xl text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                                Verify Your Email
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                To add annotations and comments, please verify your email address
                            </p>
                        </div>

                        <!-- Error Alert -->
                        <div v-if="error" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 mt-0.5 mr-2"></i>
                                <p class="text-sm text-red-700 dark:text-red-300">{{ error }}</p>
                            </div>
                        </div>

                        <form @submit.prevent="requestVerification" class="space-y-4">
                            <!-- Name Input -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Your Name
                                </label>
                                <input
                                    id="name"
                                    v-model="name"
                                    type="text"
                                    required
                                    :disabled="loading"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white disabled:opacity-50"
                                    placeholder="John Doe"
                                />
                            </div>

                            <!-- Email Input -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email Address
                                </label>
                                <input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    required
                                    :disabled="loading"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white disabled:opacity-50"
                                    placeholder="john@example.com"
                                />
                            </div>

                            <!-- Info Box -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-3">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-2"></i>
                                    <p class="text-xs text-blue-700 dark:text-blue-300">
                                        We'll send you a verification email. Click the link to access the annotation and start collaborating.
                                    </p>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="!isValid || loading"
                                class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="loading" class="flex items-center justify-center">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Sending...
                                </span>
                                <span v-else>
                                    Send Verification Email
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.3s ease;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
}
</style>
