<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  options: {
    type: Array,
    required: true, // [{ id, name }]
  },
  placeholder: {
    type: String,
    default: 'Select...',
  },
  labelKey: {
    type: String,
    default: 'name',
  },
  valueKey: {
    type: String,
    default: 'id',
  },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const root = ref(null)

const selectedMap = computed(() => {
  const map = new Map()
  for (const v of props.modelValue || []) {
    map.set(String(v), true)
  }
  return map
})

const selectedLabels = computed(() => {
  const labels = []
  for (const opt of props.options) {
    const val = String(opt[props.valueKey])
    if (selectedMap.value.has(val)) labels.push(opt[props.labelKey])
  }
  return labels
})

function toggle() {
  open.value = !open.value
}

function onChange(option) {
  const valStr = String(option[props.valueKey])
  const next = new Set((props.modelValue || []).map(v => String(v)))
  if (next.has(valStr)) next.delete(valStr)
  else next.add(valStr)
  // Emit values preserving numeric type when possible
  const result = Array.from(next).map(v => (Number.isNaN(Number(v)) ? v : Number(v)))
  emit('update:modelValue', result)
}

function onClickOutside(e) {
  if (!root.value) return
  if (!root.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
})
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="mt-1 inline-flex w-full items-center justify-between rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-left text-sm text-uh-slate dark:text-gray-300 shadow-sm focus:outline-none focus:ring-2 focus:ring-uh-teal"
      @click="toggle"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="listbox"
    >
      <span class="truncate">
        <template v-if="selectedLabels.length === 0">{{ placeholder }}</template>
        <template v-else-if="selectedLabels.length <= 3">{{ selectedLabels.join(', ') }}</template>
        <template v-else>{{ selectedLabels.slice(0, 3).join(', ') }} +{{ selectedLabels.length - 3 }}</template>
      </span>
      <svg class="ml-2 h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
      </svg>
    </button>

    <div v-if="open" class="absolute z-10 mt-1 w-full rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
      <ul role="listbox" class="max-h-60 overflow-auto py-1 text-sm">
        <li
          v-for="opt in options"
          :key="opt[valueKey]"
          role="option"
          class="cursor-pointer select-none px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 flex items-center gap-2 text-uh-slate dark:text-uh-cream"
          @click.prevent="onChange(opt)"
        >
          <input
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 text-uh-teal focus:ring-uh-teal"
            :checked="selectedMap.has(String(opt[valueKey]))"
            @change.stop="onChange(opt)"
            :aria-checked="selectedMap.has(String(opt[valueKey])) ? 'true' : 'false'"
          />
          <span>{{ opt[labelKey] }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
