<template>
  <div class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Success Message -->
    <div v-if="flash.success" class="max-w-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 shadow-lg">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <font-awesome-icon 
            :icon="['fas', 'check-circle']" 
            class="h-5 w-5 text-green-400 dark:text-green-300"
          />
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm text-green-800 dark:text-green-200">{{ flash.success }}</p>
        </div>
        <button @click="clearFlash('success')" class="ml-4 text-green-400 hover:text-green-600">
          <font-awesome-icon :icon="['fas', 'times']" class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="flash.error" class="max-w-md bg-red-50 dark:bg-gray-900 border border-red-200 dark:border-red-800 rounded-lg p-4 shadow-lg">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <font-awesome-icon 
            :icon="['fas', 'times-circle']" 
            class="h-5 w-5 text-red-400 dark:text-red-300"
          />
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm text-red-800 dark:text-red-200">{{ flash.error }}</p>
        </div>
        <button @click="clearFlash('error')" class="ml-4 text-red-400 hover:text-red-600">
          <font-awesome-icon :icon="['fas', 'times']" class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Warning Message -->
    <div v-if="flash.warning" class="max-w-md bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 shadow-lg">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <font-awesome-icon 
            :icon="['fas', 'times-circle']" 
            class="h-5 w-5 text-yellow-400 dark:text-yellow-300"
          />
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ flash.warning }}</p>
        </div>
        <button @click="clearFlash('warning')" class="ml-4 text-yellow-400 hover:text-yellow-600">
          <font-awesome-icon :icon="['fas', 'times']" class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- Info Message -->
    <div v-if="flash.info" class="max-w-md bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 shadow-lg">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <font-awesome-icon 
            :icon="['fas', 'check-circle']" 
            class="h-5 w-5 text-blue-400 dark:text-blue-300"
          />
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm text-blue-800 dark:text-blue-200">{{ flash.info }}</p>
        </div>
        <button @click="clearFlash('info')" class="ml-4 text-blue-400 hover:text-blue-600">
          <font-awesome-icon :icon="['fas', 'times']" class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed, onMounted } from 'vue'

const page = usePage()
const flash = computed(() => page.props.flash)

const clearFlash = (type) => {
  // Clear the flash message by making a request that will reset the session
  window.history.replaceState({}, '', window.location.href)
}

// Auto-clear messages after 5 seconds
onMounted(() => {
  Object.keys(flash.value).forEach(type => {
    if (flash.value[type]) {
      setTimeout(() => {
        clearFlash(type)
      }, 5000)
    }
  })
})
</script>
