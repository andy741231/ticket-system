<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    suggestions: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Search labels'
    }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);

// Filter suggestions based on search
const filteredSuggestions = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.suggestions;
    }
    
    const search = searchQuery.value.toLowerCase();
    return props.suggestions.filter(tag => 
        tag.toLowerCase().includes(search)
    );
});

// Check if a tag is selected
const isSelected = (tag) => {
    return props.modelValue.includes(tag);
};

// Toggle tag selection
const toggleTag = (tag) => {
    if (isSelected(tag)) {
        emit('update:modelValue', props.modelValue.filter(t => t !== tag));
    } else {
        emit('update:modelValue', [...props.modelValue, tag]);
    }
};

// Add new label from search input
const addNewLabel = () => {
    const trimmedQuery = searchQuery.value.trim();
    
    if (!trimmedQuery) {
        isOpen.value = false;
        return;
    }
    
    // Check if label already exists (case-insensitive)
    const exists = props.suggestions.some(tag => tag.toLowerCase() === trimmedQuery.toLowerCase());
    const alreadySelected = props.modelValue.some(tag => tag.toLowerCase() === trimmedQuery.toLowerCase());
    
    if (!exists && !alreadySelected) {
        // Add new label to selection
        emit('update:modelValue', [...props.modelValue, trimmedQuery]);
    } else if (exists && !alreadySelected) {
        // Select existing label
        const existingTag = props.suggestions.find(tag => tag.toLowerCase() === trimmedQuery.toLowerCase());
        emit('update:modelValue', [...props.modelValue, existingTag]);
    }
    
    searchQuery.value = '';
    isOpen.value = false;
};

// Handle click outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Get color for tag badge
const getTagColor = (index) => {
    const colors = [
        'bg-uh-red/20 text-uh-red border-uh-red/30',
        'bg-uh-teal/20 text-uh-teal border-uh-teal/30',
        'bg-uh-gold/20 text-uh-ocher border-uh-gold/30',
        'bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-700',
        'bg-orange-100 text-orange-700 border-orange-300 dark:bg-orange-900/20 dark:text-orange-400 dark:border-orange-700',
        'bg-purple-100 text-purple-700 border-purple-300 dark:bg-purple-900/20 dark:text-purple-400 dark:border-purple-700',
        'bg-green-100 text-green-700 border-green-300 dark:bg-green-900/20 dark:text-green-400 dark:border-green-700',
    ];
    return colors[index % colors.length];
};
</script>

<template>
    <div ref="dropdownRef" class="relative">
        

        <!-- Dropdown Button -->
        <button
            type="button"
            @click="isOpen = !isOpen"
            class="w-full px-3 py-2 text-left border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-uh-teal focus:border-uh-teal transition-colors text-sm text-gray-700 dark:text-gray-300"
        >
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <FontAwesomeIcon icon="tag" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                    <span>{{ modelValue.length > 0 ? `${modelValue.length} selected` : 'Select labels' }}</span>
                </span>
                <FontAwesomeIcon 
                    :icon="isOpen ? 'chevron-up' : 'chevron-down'" 
                    class="w-3 h-3 text-gray-400 dark:text-gray-500"
                />
            </div>
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg overflow-hidden"
        >
            <!-- Search Input -->
            <div class="p-2 border-b border-gray-200 dark:border-gray-700">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="placeholder"
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-uh-teal focus:border-uh-teal"
                    @click.stop
                />
            </div>

            <!-- Options List -->
            <div class="max-h-60 overflow-y-auto">
                <div
                    v-if="filteredSuggestions.length === 0"
                    class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center"
                >
                    No labels found
                </div>
                <label
                    v-for="(tag, index) in filteredSuggestions"
                    :key="tag"
                    class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                    @click.stop
                >
                    <input
                        type="checkbox"
                        :checked="isSelected(tag)"
                        @change="toggleTag(tag)"
                        class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-uh-teal focus:ring-uh-teal focus:ring-offset-0"
                    />
                    <span
                        :class="getTagColor(props.suggestions.indexOf(tag))"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                    >
                        {{ tag }}
                    </span>
                </label>
            </div>

            <!-- Add Labels Button -->
            <div class="p-2 border-t border-gray-200 dark:border-gray-700">
                <button
                    type="button"
                    @click="addNewLabel"
                    class="w-full px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded transition-colors"
                >
                    {{ searchQuery.trim() ? `Create "${searchQuery.trim()}"` : 'Add labels' }}
                </button>
            </div>
        </div>
        
    </div>
    <!-- Selected Tags Display -->
        <div class="flex flex-wrap gap-2 mt-3 mb-2" v-if="modelValue.length > 0">
            <span
                v-for="(tag, index) in modelValue"
                :key="tag"
                :class="getTagColor(props.suggestions.indexOf(tag))"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border"
            >
                {{ tag }}
                <button
                    type="button"
                    @click.stop="toggleTag(tag)"
                    class="hover:opacity-70 transition-opacity"
                    :aria-label="`Remove ${tag}`"
                >
                    <FontAwesomeIcon icon="times" class="w-3 h-3" />
                </button>
            </span>
        </div>
</template>
