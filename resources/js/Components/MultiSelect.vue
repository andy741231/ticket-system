<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        required: true,
        // Expects array of strings or objects { label: 'Label', value: 'value' }
    },
    label: {
        type: String,
        default: 'Select options',
    },
    placeholder: {
        type: String,
        default: 'Select...',
    },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const containerRef = ref(null);

const normalizedOptions = computed(() => {
    return props.options.map(opt => {
        if (typeof opt === 'object' && opt !== null) {
            return opt;
        }
        return { label: opt, value: opt };
    });
});

const selectedLabels = computed(() => {
    if (props.modelValue.length === 0) return props.placeholder;
    if (props.modelValue.length === 1) {
        const option = normalizedOptions.value.find(o => o.value === props.modelValue[0]);
        return option ? option.label : props.modelValue[0];
    }
    return `${props.modelValue.length} selected`;
});

function toggleDropdown() {
    isOpen.value = !isOpen.value;
}

function closeDropdown(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        isOpen.value = false;
    }
}

function toggleOption(value) {
    const newValue = [...props.modelValue];
    const index = newValue.indexOf(value);
    if (index === -1) {
        newValue.push(value);
    } else {
        newValue.splice(index, 1);
    }
    emit('update:modelValue', newValue);
}

function isSelected(value) {
    return props.modelValue.includes(value);
}

onMounted(() => {
    document.addEventListener('click', closeDropdown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdown);
});
</script>

<template>
    <div class="relative" ref="containerRef">
        <label v-if="label" class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">{{ label }}</label>
        <button 
            type="button" 
            @click.stop="toggleDropdown"
            class="w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-left shadow-sm focus:outline-none focus:ring-1 focus:ring-uh-red focus:border-uh-red sm:text-sm h-[42px] flex items-center justify-between"
        >
            <span class="block truncate text-gray-700 dark:text-gray-300">{{ selectedLabels }}</span>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                <font-awesome-icon :icon="['fas', 'chevron-down']" class="h-3 w-3 text-gray-400" />
            </span>
        </button>

        <div v-if="isOpen" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
            <div 
                v-for="option in normalizedOptions" 
                :key="option.value"
                @click="toggleOption(option.value)"
                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100"
            >
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        :checked="isSelected(option.value)"
                        class="h-4 w-4 text-uh-red focus:ring-uh-red border-gray-300 rounded"
                    />
                    <span class="ml-3 block truncate" :class="{ 'font-semibold': isSelected(option.value), 'font-normal': !isSelected(option.value) }">
                        {{ option.label }}
                    </span>
                </div>
            </div>
            <div v-if="normalizedOptions.length === 0" class="py-2 px-3 text-gray-500 text-sm">
                No options available
            </div>
        </div>
    </div>
</template>
