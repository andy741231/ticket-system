<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
  <Head title="Invalid Invitation" />
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 text-center">
      <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
        Invitation Invalid or Expired
      </h2>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
        The invitation link is invalid or has expired. Please contact the person who invited you to request a new link.
      </p>
      <div class="mt-6">
        <Link :href="route('login')" class="text-indigo-600 hover:text-indigo-500 font-medium">
          Return to sign in
        </Link>
      </div>
    </div>
  </div>
</template>
