<template>
  <div class="comment-item group bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-4">
    <!-- Main Comment -->
    <div class="flex space-x-3">
      <!-- Avatar -->
      <Avatar :user="comment.user" size="md" />

      <!-- Comment Content -->
      <div class="flex-1 min-w-0">
        <!-- Header -->
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center space-x-2">
            <span class="font-medium text-gray-900 dark:text-gray-100">
              {{ comment.user?.name || 'Unknown User' }}
            </span>
            <!-- Pinned indicator -->
            <span v-if="comment.pinned" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
              <font-awesome-icon :icon="['fas', 'thumbtack']" class="w-3 h-3 mr-1" />
              Pinned
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
              {{ formatTime(comment.created_at) }}
            </span>
            <span v-if="comment.updated_at !== comment.created_at" class="text-xs text-gray-400 dark:text-gray-500">
              (edited)
            </span>
          </div>
          
          <!-- Actions Menu -->
          <div class="relative" ref="actionsAreaRef">
            <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
              <!-- Reply Button (only for top-level comments) -->
              <button
                v-if="!isReply"
                @click="toggleReplyForm"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded"
                title="Reply to comment"
                type="button"
                :aria-expanded="showReplyForm"
                aria-label="Reply to comment"
              >
                <font-awesome-icon :icon="['fas', 'reply']" class="w-4 h-4" />
              </button>
              
              <!-- Pin/Unpin (only for top-level comments) -->
              <button
                v-if="!isReply && (canPin || isOwnComment)"
                @click="$emit('pin', comment)"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded"
                :title="comment.pinned ? 'Unpin comment' : 'Pin comment'"
                type="button"
                :aria-pressed="comment.pinned"
                :aria-label="comment.pinned ? 'Unpin comment' : 'Pin comment'"
              >
                <font-awesome-icon :icon="['fas', 'thumbtack']" class="w-4 h-4" />
              </button>
              
              
              <!-- Edit -->
              <button
                v-if="canEdit"
                @click="toggleEdit"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded"
                title="Edit comment"
                type="button"
                aria-label="Edit comment"
              >
                <font-awesome-icon :icon="['fas', 'edit']" class="w-4 h-4" />
              </button>
              
              <!-- Emoji Reactions -->
              <button
                @click="showEmojiPicker = !showEmojiPicker"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded"
                title="Add reaction"
                type="button"
                :aria-expanded="showEmojiPicker"
                aria-label="Add reaction"
              >
                <font-awesome-icon :icon="['fas', 'face-smile']" class="w-4 h-4" />
              </button>
              
              <!-- Delete -->
              <button
                v-if="canDelete"
                @click="$emit('delete', comment)"
                class="text-red-400 hover:text-red-600 p-1 rounded"
                title="Delete comment"
                type="button"
                aria-label="Delete comment"
              >
                <font-awesome-icon :icon="['fas', 'trash']" class="w-4 h-4" />
              </button>
            </div>

            <!-- Emoji Picker Dropdown (positioned under actions) -->
            <div v-if="showEmojiPicker" ref="emojiPickerRef" class="absolute right-0 top-full mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-3 z-20 min-w-[200px]">
              <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Add Reaction</div>
              <div class="grid grid-cols-6 gap-2">
                <button
                  v-for="emoji in commonEmojis"
                  :key="emoji"
                  @click="toggleReaction(emoji)"
                  :class="[
                    'w-8 h-8 flex items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-lg transition-colors emoji-button',
                    hasReacted(emoji) ? 'bg-blue-100 dark:bg-blue-900/30' : ''
                  ]"
                  :title="`React with ${emoji}`"
                  type="button"
                  :aria-pressed="hasReacted(emoji)"
                  :aria-label="`React with ${emoji}`"
                >
                  <span class="emoji-char">{{ emoji }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Comment Body / Edit Form Container -->
        <div>
          <!-- Comment Body -->
          <div v-if="!isEditing" class="text-gray-800 dark:text-gray-200 text-sm mb-3 whitespace-pre-wrap break-words">
            {{ comment.body }}
          </div>

          <!-- Edit Form -->
          <div v-else class="mb-3">
            <textarea
            v-model="editBody"
            class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            rows="3"
            @keydown.esc="cancelEdit"
            @keydown.ctrl.enter="saveEdit"
          ></textarea>
          
          <!-- Edit File Errors -->
          <div
            v-if="editFileErrors.length > 0"
            class="mt-2 text-xs text-red-600 dark:text-red-400"
            role="alert"
            aria-live="polite"
          >
            <ul class="list-disc pl-5">
              <li v-for="(err, idx) in editFileErrors" :key="idx">{{ err }}</li>
            </ul>
          </div>
          
          <div class="flex items-center justify-between mt-2">
            <div class="flex items-center space-x-2">
              <!-- File Upload for Edit -->
              <input
                ref="editFileInput"
                type="file"
                multiple
                class="hidden"
                @change="handleEditFiles"
                accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar"
              />
              <button
                @click="$refs.editFileInput.click()"
                class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                title="Attach files"
                type="button"
                aria-label="Attach files"
              >
                <font-awesome-icon :icon="['fas', 'paperclip']" class="w-4 h-4" />
                <span class="text-xs">Attach</span>
              </button>
            </div>
            
            <div class="flex space-x-2">
              <button
                @click="cancelEdit"
                class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
              >
                Cancel
              </button>
              <button
                @click="saveEdit"
                :disabled="!editBody.trim() || (editBody === comment.body && editFiles.length === 0)"
                class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Save
              </button>
            </div>
          </div>
          
          <!-- Edit File Previews -->
          <div v-if="editFiles.length > 0" class="mt-2">
            <div class="flex flex-wrap gap-2">
              <div
                v-for="(file, index) in editFiles"
                :key="index"
                class="flex items-center space-x-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2 text-sm"
              >
                <font-awesome-icon :icon="['fas', 'file']" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <span class="text-blue-800 dark:text-blue-200 truncate max-w-[150px]">{{ file.name }}</span>
                <button
                  @click="removeEditFile(index)"
                  class="text-red-500 hover:text-red-700 transition-colors"
                >
                  <font-awesome-icon :icon="['fas', 'xmark']" class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

        <!-- Attachments -->
        <div v-if="comment.attachments && comment.attachments.length > 0" class="mb-3">
          <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Attachments</div>
          <div class="space-y-2">
            <div
              v-for="attachment in comment.attachments"
              :key="attachment.id"
              class="flex items-center space-x-2 bg-gray-50 dark:bg-gray-700 rounded-lg p-2 text-sm"
            >
              <font-awesome-icon :icon="['fas', 'file']" class="w-4 h-4 text-gray-500" />
              <a
                :href="attachment.url || `/storage/${attachment.file_path}`"
                target="_blank"
                class="text-blue-600 dark:text-blue-400 hover:underline truncate flex-1"
              >
                {{ attachment.original_name || attachment.name || 'Attachment' }}
              </a>
            </div>
          </div>
        </div>

        <!-- Emoji Reactions Display -->
        <div v-if="hasAnyReactions" class="mb-3">
          <div class="flex flex-wrap gap-1">
            <button
              v-for="(count, emoji) in getReactionSummary()"
              :key="emoji"
              @click="toggleReaction(emoji)"
              :class="[
                'flex items-center space-x-1 px-2 py-1 rounded-full text-xs border transition-colors',
                hasReacted(emoji)
                  ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800'
                  : 'bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600'
              ]"
              :title="hasReacted(emoji) ? 'Remove your reaction' : 'Add your reaction'"
              type="button"
              :aria-pressed="hasReacted(emoji)"
              :aria-label="hasReacted(emoji) ? `Remove your ${emoji} reaction` : `Add a ${emoji} reaction`"
            >
              <span class="emoji-char" style="font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', 'Segoe UI Symbol', 'Android Emoji', sans-serif; font-size: 14px; line-height: 1;">{{ emoji }}</span>
              <span class="font-medium">{{ count }}</span>
            </button>
          </div>
        </div>
        <!-- Emoji picker is now rendered under the actions menu -->

        <!-- Reply Form -->
        <div v-if="showReplyForm" class="mb-4 pl-4 border-l-2 border-gray-200 dark:border-gray-700" :id="`reply-form-${comment.id}`">
          <div class="flex space-x-3">
            <Avatar :user="currentUser" size="sm" :show-link="false" />
            <div class="flex-1">
              <textarea
                ref="replyTextarea"
                v-model="replyBody"
                placeholder="Write a reply..."
                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                rows="2"
                @keydown.ctrl.enter="submitReply"
              ></textarea>
              
              <!-- Reply File Errors -->
              <div
                v-if="replyFileErrors.length > 0"
                class="mt-2 text-xs text-red-600 dark:text-red-400"
                role="alert"
                aria-live="polite"
              >
                <ul class="list-disc pl-5">
                  <li v-for="(err, idx) in replyFileErrors" :key="idx">{{ err }}</li>
                </ul>
              </div>
              
              <!-- Reply Actions -->
              <div class="flex items-center justify-between mt-2">
                <div class="flex items-center space-x-2">
                  <input
                    ref="replyFileInput"
                    type="file"
                    multiple
                    class="hidden"
                    @change="handleReplyFiles"
                  />
                  <button
                    @click="$refs.replyFileInput.click()"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    title="Attach files"
                    type="button"
                    aria-label="Attach files"
                  >
                    <font-awesome-icon :icon="['fas', 'paperclip']" class="w-5 h-5" />
                  </button>
                </div>
                
                <div class="flex space-x-2">
                  <button
                    @click="cancelReply"
                    class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                    type="button"
                    aria-label="Cancel reply"
                  >
                    Cancel
                  </button>
                  <button
                    @click="submitReply"
                    :disabled="!replyBody.trim() && replyFiles.length === 0"
                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    type="button"
                    :aria-disabled="!replyBody.trim() && replyFiles.length === 0"
                    aria-label="Post reply"
                    title="Post reply"
                  >
                    Reply
                  </button>
                </div>
              </div>
              
              <!-- Reply File Previews -->
              <div v-if="replyFiles.length > 0" class="mt-2">
                <div class="flex flex-wrap gap-2">
                  <div
                    v-for="(file, index) in replyFiles"
                    :key="index"
                    class="flex items-center space-x-2 bg-gray-50 dark:bg-gray-700 rounded-lg p-2 text-sm"
                  >
                    <font-awesome-icon :icon="['fas', 'file']" class="w-4 h-4 text-gray-500" />
                    <span class="truncate max-w-[150px]">{{ file.name }}</span>
                    <button
                      @click="removeReplyFile(index)"
                      class="text-red-500 hover:text-red-700"
                    >
                      <font-awesome-icon :icon="['fas', 'xmark']" class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Replies -->
        <div v-if="comment.replies && comment.replies.length > 0" class="pl-4 border-l-2 border-gray-100 dark:border-gray-700 space-y-3">
          <CommentItem
            v-for="reply in comment.replies"
            :key="reply.id"
            :comment="reply"
            :current-user="currentUser"
            :can-delete="canDeleteReply(reply)"
            :can-edit="canEditReply(reply)"
            :is-reply="true"
            @delete="$emit('delete', $event)"
            @edit="$emit('edit', $event)"
            @react="$emit('react', $event)"
            @reply="$emit('reply', $event)"
            @pin="$emit('pin', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import Avatar from '@/Components/Avatar.vue';

const props = defineProps({
  comment: {
    type: Object,
    required: true
  },
  currentUser: {
    type: Object,
    default: () => ({})
  },
  canDelete: {
    type: Boolean,
    default: false
  },
  canEdit: {
    type: Boolean,
    default: false
  },
  isReply: {
    type: Boolean,
    default: false
  },
  canPin: {
    type: Boolean,
    default: false
  }
})

// Additional state for UI toggles
const isOwnComment = computed(() => props.comment.user_id === props.currentUser?.id)

const emit = defineEmits(['delete', 'edit', 'react', 'reply', 'pin'])

// Edit functionality
const isEditing = ref(false)
const editBody = ref('')
const editFiles = ref([])
const editFileErrors = ref([])

const toggleEdit = () => {
  isEditing.value = !isEditing.value
  if (isEditing.value) {
    editBody.value = props.comment.body
    editFiles.value = []
    editFileErrors.value = []
  }
}

const cancelEdit = () => {
  isEditing.value = false
  editBody.value = ''
  editFiles.value = []
  editFileErrors.value = []
}

const saveEdit = () => {
  if (editBody.value.trim() && (editBody.value !== props.comment.body || editFiles.value.length > 0)) {
    const formData = new FormData()
    formData.append('body', editBody.value)
    
    editFiles.value.forEach((file, index) => {
      formData.append(`files[${index}]`, file)
    })

    emit('edit', {
      comment: props.comment,
      body: editBody.value,
      files: editFiles.value,
      formData
    })
  }
  isEditing.value = false
}

const handleEditFiles = (event) => {
  const files = Array.from(event.target.files)
  const accepted = []
  const rejected = []
  files.forEach((file) => {
    if (file.size <= MAX_FILE_SIZE) {
      accepted.push(file)
    } else {
      rejected.push(`${file.name} exceeds 10 MB limit`)
    }
  })
  if (accepted.length) {
    editFiles.value.push(...accepted)
  }
  editFileErrors.value = rejected
  // allow selecting the same file again after a rejection
  event.target.value = ''
}

const removeEditFile = (index) => {
  editFiles.value.splice(index, 1)
}

// Reply functionality
const showReplyForm = ref(false)
const replyBody = ref('')
const replyFiles = ref([])
const replyFileErrors = ref([])
const MAX_FILE_SIZE = 10 * 1024 * 1024 // 10 MB per file (matches backend max:10240 KB)
const replyButton = ref(null)
const replyTextarea = ref(null)

const toggleReplyForm = async () => {
  showReplyForm.value = !showReplyForm.value
  if (showReplyForm.value) {
    await nextTick()
    if (replyTextarea.value) {
      replyTextarea.value.focus()
      // place cursor at end
      const val = replyBody.value || ''
      replyBody.value = ''
      replyBody.value = val
    }
  } else if (replyButton.value) {
    replyButton.value.focus()
  }
}

const submitReply = () => {
  if (replyBody.value.trim() || replyFiles.value.length > 0) {
    emit('reply', {
      parentId: props.comment.id,
      body: replyBody.value,
      files: replyFiles.value
    })
    cancelReply()
  }
}

const cancelReply = () => {
  showReplyForm.value = false
  replyBody.value = ''
  replyFiles.value = []
  replyFileErrors.value = []
  // Return focus to the Reply button for accessibility
  if (replyButton.value) {
    replyButton.value.focus()
  }
}

const handleReplyFiles = (event) => {
  const files = Array.from(event.target.files)
  const accepted = []
  const rejected = []
  files.forEach((file) => {
    if (file.size <= MAX_FILE_SIZE) {
      accepted.push(file)
    } else {
      rejected.push(`${file.name} exceeds 10 MB limit`)
    }
  })
  if (accepted.length) {
    replyFiles.value.push(...accepted)
  }
  replyFileErrors.value = rejected
  // allow selecting the same file again after a rejection
  event.target.value = ''
}

const removeReplyFile = (index) => {
  replyFiles.value.splice(index, 1)
}

// Reactions
const showEmojiPicker = ref(false)
const actionsAreaRef = ref(null)
const emojiPickerRef = ref(null)
const commonEmojis = ['👍', '❤️', '😂', '😊', '😮', '😢', '😡', '🎉', '🔥', '👏', '💯', '🚀', '👀']

const hasReacted = (reaction) => {
  if (!props.comment.reactions || !props.currentUser?.id) return false
  return props.comment.reactions.some(r => 
    r.type === reaction && r.user_id === props.currentUser.id
  )
}

const getReactionCount = (reaction) => {
  if (!props.comment.reactions) return 0
  return props.comment.reactions.filter(r => r.type === reaction).length
}

const toggleReaction = (reaction) => {
  showEmojiPicker.value = false
  
  // If user already reacted with this emoji, just remove it
  if (hasReacted(reaction)) {
    emit('react', {
      commentId: props.comment.id,
      reaction: reaction,
      isRemoving: true
    })
    return
  }
  
  // Get current user reaction (if any)
  const currentUserReaction = props.comment.reactions?.find(r => r.user_id === props.currentUser?.id)
  
  // Emit a single event with both old and new reaction info
  emit('react', {
    commentId: props.comment.id,
    reaction: reaction,
    isRemoving: false,
    previousReaction: currentUserReaction?.type || null
  })
}

const hasAnyReactions = computed(() => {
  return props.comment.reactions && props.comment.reactions.length > 0
})

const getReactionSummary = () => {
  if (!props.comment.reactions) return {}
  
  const summary = {}
  props.comment.reactions.forEach(reaction => {
    if (summary[reaction.type]) {
      summary[reaction.type]++
    } else {
      summary[reaction.type] = 1
    }
  })
  
  return summary
}

// Permissions for replies
const canDeleteReply = (reply) => {
  return reply.user_id === props.currentUser?.id || props.canDelete
}

const canEditReply = (reply) => {
  return reply.user_id === props.currentUser?.id
}

// Time formatting
const formatTime = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)
  
  if (diffInSeconds < 60) return 'just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`
  
  return date.toLocaleDateString()
}

// Close emoji picker when clicking outside or pressing Escape
const handleGlobalClick = (e) => {
  if (!showEmojiPicker.value) return
  const container = actionsAreaRef.value
  if (container && !container.contains(e.target)) {
    showEmojiPicker.value = false
  }
}

const handleKeydown = (e) => {
  if (e.key === 'Escape' && showEmojiPicker.value) {
    showEmojiPicker.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleGlobalClick, true)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleGlobalClick, true)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.comment-item {
  transition: all 0.2s ease;
}

.comment-item:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.dark .comment-item:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.emoji-button {
  font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', 'Segoe UI Symbol', 'Android Emoji', 'EmojiSymbols', sans-serif;
}

.emoji-char {
  font-size: 16px;
  line-height: 1;
  display: block;
}
</style>
