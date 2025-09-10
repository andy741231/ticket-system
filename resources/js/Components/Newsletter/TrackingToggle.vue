<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: true },
  label: { type: String, default: 'Enable open and click tracking' },
  help: { type: String, default: 'When disabled, tracked URLs like /newsletter/public/track-click/... will be removed from links in the email HTML before sending.' },
  inputId: { type: String, default: 'enable-tracking' },
});

const emit = defineEmits(['update:modelValue']);

function onChange(e) {
  emit('update:modelValue', e.target.checked);
}
</script>

<template>
  <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
    <label class="flex items-start p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors" :for="inputId">
      <input
        :id="inputId"
        :checked="modelValue"
        @change="onChange"
        type="checkbox"
        class="mt-1 h-4 w-4 text-uh-red focus:ring-uh-red dark:focus:ring-blue-500 border-gray-300 rounded"
      />
      <div class="ml-3">
        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ label }}</div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          <code>/newsletter/public/track-click/...</code>
          <span class="ml-1">{{ help }}</span>
        </p>
      </div>
    </label>
  </div>
</template>
