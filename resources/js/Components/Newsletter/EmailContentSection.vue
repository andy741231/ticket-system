<script setup>
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';

const props = defineProps({
  modelValue: { type: [String, Object, Array], default: '' },
  templates: { type: Array, default: () => [] },
  initialHtml: { type: String, default: '' },
  title: { type: String, default: 'Email Content' },
  subtitle: { type: String, default: 'Design your email using the visual editor below' },
  campaignId: { type: [Number, String], default: null },
  tempKey: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'update:html-content', 'template-selected']);
</script>

<template>
  <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ title }}</h3>
      <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
    </div>
    <div class="p-6">
      <EmailBuilder
        :model-value="modelValue"
        :templates="templates"
        :initial-html="initialHtml"
        :campaign-id="campaignId"
        :temp-key="tempKey"
        @update:modelValue="$emit('update:modelValue', $event)"
        @update:html-content="$emit('update:html-content', $event)"
        @template-selected="$emit('template-selected', $event)"
      />
    </div>
  </div>
</template>
