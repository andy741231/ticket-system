<script setup>
import { computed } from 'vue';

const props = defineProps({
  effect: { type: String, required: true }, // 'allow' | 'deny'
  expiresAt: { type: [String, Date, null], default: null },
  appSlug: { type: String, default: null },
  size: { type: String, default: 'sm' }, // 'sm' | 'md'
});

const now = () => new Date().getTime();
const expDate = computed(() => (props.expiresAt ? new Date(props.expiresAt) : null));
const isExpired = computed(() => (expDate.value ? expDate.value.getTime() < now() : false));
const daysLeft = computed(() => {
  if (!expDate.value) return null;
  const ms = expDate.value.getTime() - now();
  return Math.ceil(ms / 86400000);
});

const classes = computed(() => {
  const base = ['inline-flex', 'items-center', 'rounded-full', 'font-medium'];
  base.push(props.size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-3 py-1');

  if (isExpired.value) {
    base.push('bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200');
    return base;
  }

  if (props.effect === 'deny') {
    base.push('bg-rose-600 text-white');
  } else {
    base.push('bg-emerald-600 text-white');
  }
  return base;
});

const label = computed(() => {
  if (isExpired.value) return 'Expired';
  if (props.effect === 'deny') return 'Deny';
  return 'Allow';
});
</script>

<template>
  <span :class="classes">
    <span>{{ label }}</span>
    <span v-if="daysLeft !== null && !isExpired" class="ml-1 opacity-80">(in {{ daysLeft }}d)</span>
    <span v-if="appSlug" class="ml-2 text-xs bg-black/10 rounded px-1 py-0.5">{{ appSlug || 'global' }}</span>
  </span>
</template>
