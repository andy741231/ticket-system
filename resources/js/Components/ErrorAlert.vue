<template>
  <div v-if="show" class="fixed top-4 right-4 z-50 max-w-md">
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 shadow-lg">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <font-awesome-icon 
            :icon="['fas', 'times-circle']" 
            class="h-5 w-5 text-red-400 dark:text-red-300"
          />
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
            {{ title }}
          </h3>
          <div class="mt-2 text-sm text-red-700 dark:text-red-300">
            <p>{{ message }}</p>
          </div>
          <div v-if="details" class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/40 p-2 rounded">
            <pre class="whitespace-pre-wrap">{{ details }}</pre>
          </div>
        </div>
        <div class="ml-4 flex-shrink-0">
          <button
            @click="close"
            class="inline-flex text-red-400 dark:text-red-300 hover:text-red-600 dark:hover:text-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded"
          >
            <span class="sr-only">Close</span>
            <font-awesome-icon :icon="['fas', 'times']" class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Error'
  },
  message: {
    type: String,
    required: true
  },
  details: {
    type: String,
    default: null
  },
  autoClose: {
    type: Boolean,
    default: true
  },
  duration: {
    type: Number,
    default: 5000
  }
})

const emit = defineEmits(['close'])

const show = ref(true)

const close = () => {
  show.value = false
  emit('close')
}

onMounted(() => {
  if (props.autoClose) {
    setTimeout(() => {
      close()
    }, props.duration)
  }
})

// Watch for message changes to show the alert again
watch(() => props.message, () => {
  show.value = true
})
</script>
