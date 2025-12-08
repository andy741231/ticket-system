<template>
  <div class="mention-autocomplete relative">
    <textarea
      ref="textarea"
      :value="modelValue"
      @input="handleInput"
      @keydown="handleKeydown"
      v-bind="$attrs"
      class="w-full"
    ></textarea>
    
    <!-- Mention Dropdown -->
    <div
      v-if="showMentions"
      class="absolute z-[9999] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto max-w-[350px]"
      :style="dropdownStyle"
    >
      <div class="p-2 text-xs font-medium text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
        Mention someone
      </div>
      <!-- Empty state -->
      <div v-if="filteredUsers.length === 0" class="p-3 text-xs text-gray-500 dark:text-gray-400">
        Only users who have access to this ticket can be mentioned.
      </div>

      <!-- Results -->
      <template v-else>
        <div
          v-for="(user, index) in filteredUsers"
          :key="user.id"
          @click="selectUser(user)"
          :class="[
            'flex items-center p-3 cursor-pointer transition-colors',
            index === selectedIndex 
              ? 'bg-blue-50 dark:bg-blue-900/30' 
              : 'hover:bg-gray-50 dark:hover:bg-gray-700'
          ]"
        >
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-medium">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ user.name }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                @{{ user.username }}
                <span v-if="user.first_name && user.last_name" class="ml-2">
                  ({{ user.first_name }} {{ user.last_name }})
                </span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { getMentionAtCursor, replaceMention, searchUsers } from '@/Utils/mentionUtils';

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  ticketId: {
    type: [String, Number],
    required: false
  },
  imageId: {
    type: [String, Number],
    required: false
  },
  publicToken: {
    type: String,
    required: false
  },
  isPublic: {
    type: Boolean,
    default: false
  },
  includeExternalUsers: {
    type: Boolean,
    default: false
  },
  dropdownPosition: {
    type: String,
    default: 'bottom', // 'bottom' or 'top'
    validator: (value) => ['bottom', 'top'].includes(value)
  }
});

const emit = defineEmits(['update:modelValue']);

// Refs
const textarea = ref(null);
const showMentions = ref(false);
const mentionInfo = ref(null);
const selectedIndex = ref(0);
const availableUsers = ref([]);
const dropdownStyle = ref({});

// Computed
const filteredUsers = computed(() => {
  // Ensure availableUsers is always an array
  const users = Array.isArray(availableUsers.value) ? availableUsers.value : [];
  if (!mentionInfo.value || !mentionInfo.value.query) {
    return users.slice(0, 50);
  }
  return searchUsers(mentionInfo.value.query, users);
});

// Methods
const handleInput = (event) => {
  emit('update:modelValue', event.target.value);
  checkForMention();
};

const checkForMention = async () => {
  if (!textarea.value) return;
  const mention = getMentionAtCursor(textarea.value);
  if (mention) {
    mentionInfo.value = mention;
    selectedIndex.value = 0;
    // Load users if not already loaded
    if (!Array.isArray(availableUsers.value) || availableUsers.value.length === 0) {
      await loadMentionableUsers();
    }
    // Position dropdown
    positionDropdown();
    showMentions.value = true;
  } else {
    showMentions.value = false;
    mentionInfo.value = null;
  }
};

const positionDropdown = () => {
  if (!textarea.value || !mentionInfo.value) return;
  
  if (props.dropdownPosition === 'top') {
    // Position above the textarea
    dropdownStyle.value = {
      bottom: '100%',
      left: '0px',
      marginBottom: '4px',
      minWidth: '200px',
      width: 'auto'
    };
  } else {
    // Position below the textarea (default)
    dropdownStyle.value = {
      top: '100%',
      left: '0px',
      marginTop: '4px',
      minWidth: '200px',
      width: 'auto'
    };
  }
};

const handleKeydown = (event) => {
  if (!showMentions.value || filteredUsers.value.length === 0) return;
  
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      selectedIndex.value = Math.min(selectedIndex.value + 1, filteredUsers.value.length - 1);
      break;
    case 'ArrowUp':
      event.preventDefault();
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
      break;
    case 'Enter':
    case 'Tab':
      if (filteredUsers.value[selectedIndex.value]) {
        event.preventDefault();
        selectUser(filteredUsers.value[selectedIndex.value]);
      }
      break;
    case 'Escape':
      showMentions.value = false;
      break;
  }
};

const selectUser = (user) => {
  if (!mentionInfo.value || !textarea.value) return;
  
  replaceMention(textarea.value, mentionInfo.value, user.username);
  emit('update:modelValue', textarea.value.value);
  
  showMentions.value = false;
  mentionInfo.value = null;
  
  // Focus back on textarea
  nextTick(() => {
    textarea.value.focus();
  });
};

const loadMentionableUsers = async () => {
  try {
    let url;
    console.log('[MentionAutocomplete] Loading mentionable users', {
      isPublic: props.isPublic,
      imageId: props.imageId,
      publicToken: props.publicToken ? 'present' : 'missing',
      ticketId: props.ticketId,
      includeExternalUsers: props.includeExternalUsers
    });
    
    if (props.isPublic && props.imageId && props.publicToken) {
      // External user context - use public endpoint
      url = `/api/public/annotations/${props.imageId}/mentionable-users?token=${props.publicToken}`;
      console.log('[MentionAutocomplete] Using public endpoint:', url);
    } else if (props.ticketId) {
      // Internal user context - use ticket endpoint
      url = `/api/tickets/${props.ticketId}/mentionable-users`;
      // Add include_external parameter if requested
      if (props.includeExternalUsers) {
        url += '?include_external=true';
      }
      console.log('[MentionAutocomplete] Using ticket endpoint:', url);
    } else {
      console.warn('MentionAutocomplete: Neither ticketId nor (imageId + publicToken) provided');
      availableUsers.value = [];
      return;
    }

    const response = await fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
      credentials: 'include'
    });
    
    console.log('[MentionAutocomplete] Response status:', response.status);
    
    if (response.ok) {
      const data = await response.json();
      console.log('[MentionAutocomplete] Received data:', data);
      availableUsers.value = Array.isArray(data.users) ? data.users : [];
      console.log('[MentionAutocomplete] Available users count:', availableUsers.value.length);
    } else {
      const errorData = await response.text();
      console.error('[MentionAutocomplete] Failed to load users:', response.status, errorData);
      availableUsers.value = [];
    }
  } catch (error) {
    console.error('[MentionAutocomplete] Error loading mentionable users:', error);
    availableUsers.value = [];
  }
};

const handleClickOutside = (event) => {
  if (!textarea.value?.contains(event.target)) {
    showMentions.value = false;
  }
};

// Lifecycle
onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  loadMentionableUsers();
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Expose focus method for parent components to focus the internal textarea
defineExpose({
  focus: () => {
    if (textarea.value) {
      textarea.value.focus();
    }
  }
});
</script>

<style scoped>
.mention-autocomplete {
  position: relative;
}
</style>
