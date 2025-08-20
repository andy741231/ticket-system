<script setup>
const props = defineProps({
  name: { type: String, required: true },
  appSlug: { type: String, default: null },
  size: { type: String, default: 'sm' }, // 'sm' | 'md'
  variant: { type: String, default: 'solid' }, // 'solid' | 'outline'
});
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full font-medium',
      size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-3 py-1',
      variant === 'solid'
        ? 'bg-uh-teal text-white'
        : 'ring-1 ring-uh-teal/60 text-uh-teal dark:text-uh-teal bg-transparent'
    ]"
  >
    <span v-if="appSlug" class="opacity-80 mr-1">{{ appSlug }}:</span>
    <span>{{ name }}</span>
  </span>
</template>
