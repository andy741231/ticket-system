<script setup>
const props = defineProps({
  name: { type: String, required: true },
  size: { type: String, default: 'sm' }, // 'sm' | 'md'
});
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-md font-mono',
      size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-3 py-1',
      'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
    ]"
  >
    {{ name }}
  </span>
</template>
