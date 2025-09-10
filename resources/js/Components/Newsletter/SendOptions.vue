<script setup>
const props = defineProps({
  modelValue: { type: String, default: 'immediate' },
  scheduledDate: { type: String, default: '' },
  scheduledTime: { type: String, default: '12:00' },
});
const emit = defineEmits(['update:modelValue', 'update:scheduledDate', 'update:scheduledTime']);
</script>

<template>
  <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 text-uh-red" viewBox="0 0 24 24" fill="currentColor"><path d="M6.75 3a.75.75 0 000 1.5h.75V6a.75.75 0 001.5 0V4.5h3V6a.75.75 0 001.5 0V4.5h3V6a.75.75 0 001.5 0V4.5h.75a.75.75 0 000-1.5h-12z"/><path fill-rule="evenodd" d="M3 6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5A2.25 2.25 0 0118.75 19.5H5.25A2.25 2.25 0 013 17.25V6.75zm2.25 2.25A.75.75 0 016 8.25h12a.75.75 0 010 1.5H6a.75.75 0 01-.75-.75zm0 3.75A.75.75 0 016 12h12a.75.75 0 010 1.5H6A.75.75 0 015.25 12zm0 3.75A.75.75 0 016 15.75h7.5a.75.75 0 010 1.5H6a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
        Send Options
      </h3>
    </div>
    <div class="p-6 space-y-6">
      <div class="space-y-3">
        <label class="flex items-center p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
          <input 
            :checked="modelValue === 'immediate'"
            @change="$emit('update:modelValue', 'immediate')"
            type="radio" 
            value="immediate" 
            name="send_type" 
            class="h-4 w-4 text-uh-red focus:ring-uh-red border-gray-300" 
          />
          <div class="ml-3">
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Send Immediately</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Campaign will be sent right away</div>
          </div>
        </label>
        
        <label class="flex items-center p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
          <input 
            :checked="modelValue === 'scheduled'"
            @change="$emit('update:modelValue', 'scheduled')"
            type="radio" 
            value="scheduled" 
            name="send_type" 
            class="h-4 w-4 text-uh-red focus:ring-uh-red border-gray-300" 
          />
          <div class="ml-3">
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Schedule for Later</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Choose a specific date and time</div>
          </div>
        </label>
      </div>

      <div v-if="modelValue === 'scheduled'" class="space-y-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium">Date</label>
            <div class="mt-1 relative">
              <input 
                :value="scheduledDate"
                @input="$emit('update:scheduledDate', $event.target.value)"
                type="date" 
                :min="new Date().toISOString().split('T')[0]" 
                class="block w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-uh-red focus:border-uh-red" 
              />
            </div>
          </div>
          
          <div>
            <label class="text-sm font-medium">Time</label>
            <div class="mt-1 relative">
              <input 
                :value="scheduledTime"
                @input="$emit('update:scheduledTime', $event.target.value)"
                type="time" 
                class="block w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-uh-red focus:border-uh-red" 
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
