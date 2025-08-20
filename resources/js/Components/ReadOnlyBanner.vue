<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
  title: { type: String, default: 'Read-only' },
  message: { type: String, default: 'You do not have permission to modify these fields. The form is read-only.' },
});
</script>

<template>
  <div class="mb-3 rounded border border-amber-300 bg-amber-50 p-3 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200 flex items-start gap-2">
    <ExclamationTriangleIcon class="h-5 w-5 mt-0.5 shrink-0" />
    <div>
      <div class="font-semibold">{{ title }}</div>
      <div class="text-sm">{{ message }}</div>
    </div>
  </div>
</template>
