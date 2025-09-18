<script setup>
const props = defineProps({
  groups: { type: Array, default: () => [] },
  sendToAll: { type: Boolean, default: false },
  selectedGroupIds: { type: Array, default: () => [] },
  estimatedCount: { type: Number, default: null },
  loadingEstimated: { type: Boolean, default: false },
  showEstimate: { type: Boolean, default: true },
  error: { type: String, default: '' }
});

const emit = defineEmits(['update:sendToAll', 'update:selectedGroupIds']);

function toggleAll(ev) {
  emit('update:sendToAll', ev.target.checked);
}

function onToggleGroup(id, ev) {
  const current = Array.isArray(props.selectedGroupIds) ? [...props.selectedGroupIds] : [];
  const idx = current.findIndex((x) => String(x) === String(id));
  if (ev.target.checked) {
    if (idx === -1) current.push(id);
  } else {
    if (idx !== -1) current.splice(idx, 1);
  }
  emit('update:selectedGroupIds', current);
}
</script>

<template>
  <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 text-uh-red" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-3-3h-2M7 20H2v-2a3 3 0 013-3h2m10-4h.01M7 10h.01M12 10h.01M7 6h.01M12 6h.01M17 6h.01M17 10h.01M12 14h.01M7 14h.01"/></svg>
        Recipients
      </h3>
    </div>

    <div class="p-6 space-y-4">
      <div class="flex items-center">
        <input 
          :id="'send-to-all'" 
          :checked="sendToAll"
          @change="toggleAll"
          type="checkbox" 
          class="h-4 w-4 rounded border-gray-300 text-uh-red focus:ring-uh-red focus:ring-offset-0" 
        />
        <label :for="'send-to-all'" class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
          Send to all active subscribers
        </label>
      </div>
      
      <div v-if="!sendToAll">
        <div class="flex justify-between items-center mb-1">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Groups</label>
          <span v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</span>
        </div>
        <div 
          id="target_groups"
          role="group"
          class="space-y-2 max-h-48 overflow-y-auto border rounded-lg p-3"
          :class="{
            'border-red-500': error,
            'border-gray-200 dark:border-gray-600': !error
          }"
        >
          <label 
            v-for="group in groups" 
            :key="group.id" 
            class="flex items-center p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors"
          >
            <input 
              :checked="selectedGroupIds?.some(x => String(x) === String(group.id))"
              @change="(e)=>onToggleGroup(group.id, e)"
              type="checkbox" 
              class="rounded border-gray-300 text-uh-red shadow-sm focus:ring-uh-red focus:ring-offset-0" 
            />
            <div class="ml-3 flex-1">
              <div class="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <span>{{ group.name }}</span>
                <span v-if="String(group.id) === 'protected_dir_team'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200" title="This group is auto-managed from the campus directory and cannot be edited on the public page.">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
                    <path fill-rule="evenodd" d="M12 1.5A5.25 5.25 0 006.75 6.75V9H6a2.25 2.25 0 00-2.25 2.25v9A2.25 2.25 0 006 22.5h12a2.25 2.25 0 002.25-2.25v-9A2.25 2.25 0 0018 9h-.75V6.75A5.25 5.25 0 0012 1.5zm3.75 7.5V6.75a3.75 3.75 0 10-7.5 0V9h7.5z" clip-rule="evenodd" />
                  </svg>
                  <span>Auto-managed</span>
                </span>
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                <template v-if="String(group.id) === 'protected_dir_team'">
                  {{ group.active_subscriber_count || 0 }} members
                </template>
                <template v-else>
                  {{ group.active_subscriber_count || 0 }} subscribers
                </template>
              </div>
            </div>
          </label>
        </div>
      </div>

      <div v-if="showEstimate" class="bg-uh-cream dark:bg-uh-forest/20 border border-uh-gold/30 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-uh-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M13 7H7v6h6V7z"/><path fill-rule="evenodd" d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm8 4a2 2 0 012 2v4a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2h6z" clip-rule="evenodd"/></svg>
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-uh-chocolate dark:text-uh-cream">
              Estimated recipients:
              <span v-if="loadingEstimated" class="animate-pulse">Calculating...</span>
              <span v-else class="font-bold">{{ (estimatedCount ?? 0).toLocaleString() }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="text-sm text-red-600 dark:text-red-400 mt-2">
        {{ error }}
      </div>
    </div>
  </div>
</template>
