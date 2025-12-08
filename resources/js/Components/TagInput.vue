<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
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
        default: 'Add tags...'
    },
    maxTags: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['update:modelValue']);

const inputValue = ref('');
const showSuggestions = ref(false);
const inputRef = ref(null);
const containerRef = ref(null);

// Filter suggestions based on input and exclude already selected tags
const filteredSuggestions = computed(() => {
    if (!inputValue.value.trim()) {
        return props.suggestions.filter(tag => !props.modelValue.includes(tag));
    }
    
    const search = inputValue.value.toLowerCase();
    return props.suggestions
        .filter(tag => 
            tag.toLowerCase().includes(search) && 
            !props.modelValue.includes(tag)
        );
});

// Add a tag
const addTag = (tag) => {
    const trimmedTag = tag.trim();
    
    if (!trimmedTag) return;
    
    // Check if tag already exists (case-insensitive)
    const exists = props.modelValue.some(t => t.toLowerCase() === trimmedTag.toLowerCase());
    if (exists) {
        inputValue.value = '';
        return;
    }
    
    // Check max tags limit
    if (props.maxTags && props.modelValue.length >= props.maxTags) {
        inputValue.value = '';
        return;
    }
    
    emit('update:modelValue', [...props.modelValue, trimmedTag]);
    inputValue.value = '';
    showSuggestions.value = false;
};

// Remove a tag
const removeTag = (index) => {
    const newTags = [...props.modelValue];
    newTags.splice(index, 1);
    emit('update:modelValue', newTags);
};

// Handle input keydown
const handleKeyDown = (event) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        if (inputValue.value.trim()) {
            addTag(inputValue.value);
        }
    } else if (event.key === 'Backspace' && !inputValue.value && props.modelValue.length > 0) {
        removeTag(props.modelValue.length - 1);
    } else if (event.key === 'Escape') {
        showSuggestions.value = false;
    }
};

// Handle input focus
const handleFocus = () => {
    showSuggestions.value = true;
};

// Handle click outside
const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        showSuggestions.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Watch input value to show/hide suggestions
watch(inputValue, (newVal) => {
    if (newVal.trim()) {
        showSuggestions.value = true;
    }
});
</script>

<template>
    <div ref="containerRef" class="relative">
        <!-- Tags Container -->
        <div 
            class="min-h-[42px] w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-800 focus-within:ring-2 focus-within:ring-uh-teal focus-within:border-uh-teal transition-colors cursor-text"
            @click="inputRef?.focus()"
        >
            <div class="flex flex-wrap gap-2 items-center">
                <!-- Selected Tags -->
                <span
                    v-for="(tag, index) in modelValue"
                    :key="index"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-uh-teal/20 text-uh-slate dark:text-uh-cream border border-uh-teal/30 dark:border-uh-teal/50"
                >
                    {{ tag }}
                    <button
                        type="button"
                        @click.stop="removeTag(index)"
                        class="ml-1 hover:text-uh-red transition-colors"
                        :aria-label="`Remove ${tag} tag`"
                    >
                        <FontAwesomeIcon icon="times" class="w-3 h-3" />
                    </button>
                </span>
                
                <!-- Input Field -->
                <input
                    ref="inputRef"
                    v-model="inputValue"
                    type="text"
                    :placeholder="modelValue.length === 0 ? placeholder : ''"
                    class="flex-1 min-w-[120px] outline-none bg-transparent text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"
                    @keydown="handleKeyDown"
                    @focus="handleFocus"
                />
            </div>
        </div>
        
        <!-- Suggestions Dropdown -->
        <div
            v-if="showSuggestions && (filteredSuggestions.length > 0 || inputValue.trim())"
            class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto"
        >
            <!-- Create New Tag Option -->
            <button
                v-if="inputValue.trim() && !filteredSuggestions.some(s => s.toLowerCase() === inputValue.trim().toLowerCase())"
                type="button"
                @click="addTag(inputValue)"
                class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2"
            >
                <FontAwesomeIcon icon="plus" class="w-3 h-3 text-uh-teal" />
                <span>Create "<span class="font-semibold">{{ inputValue.trim() }}</span>"</span>
            </button>
            
            <!-- Existing Suggestions -->
            <button
                v-for="(suggestion, index) in filteredSuggestions"
                :key="index"
                type="button"
                @click="addTag(suggestion)"
                class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
            >
                {{ suggestion }}
            </button>
            
            <!-- No Suggestions Message -->
            <div
                v-if="filteredSuggestions.length === 0 && !inputValue.trim()"
                class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 italic"
            >
                No tags available
            </div>
        </div>
        
        <!-- Helper Text -->
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Press Enter or comma to add a tag
        </p>
    </div>
</template>
