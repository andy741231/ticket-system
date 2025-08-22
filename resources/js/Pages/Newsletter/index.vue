<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Newsletter" />
  <AuthenticatedLayout>
    <template #header></template>

    <section
      class="max-w-7xl mx-auto sm:px-6 lg:px-8"
      role="region"
      aria-labelledby="newsletter-heading"
    >
      <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
        <h1
          id="newsletter-heading"
          class="text-xl font-semibold text-gray-900 dark:text-gray-100"
        >
          Newsletter
        </h1>
        <p class="mt-2 text-gray-600 dark:text-gray-300">
          This page is under construction. Check back soon.
        </p>
      </div>
    </section>
  </AuthenticatedLayout>
</template>