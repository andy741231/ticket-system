<template>
  <div class="comment-list">
    <div class="mb-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
        <font-awesome-icon :icon="['fas', 'comments']" class="w-5 h-5 mr-2" />
        Comments ({{ comments.length }})
      </h3>

      <!-- Comment Form -->
      <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex space-x-3">
          <!-- Current User Avatar -->
          <Avatar :user="currentUser" size="md" :show-link="false" />

          <!-- Comment Input -->
          <div class="flex-1">
            <textarea
              v-model="newComment"
              placeholder="Write a comment..."
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              rows="3"
              @keydown.ctrl.enter="submitComment"
              @input="adjustTextareaHeight"
              ref="commentTextarea"
            ></textarea>
           
            <!-- Comment Actions -->
            <div class="flex items-center justify-between mt-3">
              <div class="flex items-center space-x-3">
                <!-- File Upload -->
                <input
                  ref="fileInput"
                  type="file"
                  multiple
                  class="hidden"
                  @change="handleFiles"
                  accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar"
                />
                <button
                  type="button"
                  @click="$refs.fileInput.click()"
                  class="flex items-center space-x-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                  title="Attach files"
                  aria-label="Attach files"
                >
                  <font-awesome-icon :icon="['fas', 'paperclip']" class="w-5 h-5" />
                  <span class="text-sm">Attach</span>
                </button>

              </div>

              <!-- Submit Button -->
              <button
                type="button"
                @click="submitComment"
                :disabled="!newComment.trim() && attachedFiles.length === 0"
                :class="[
                  'px-4 py-2 rounded-lg font-medium text-sm transition-all',
                  (!newComment.trim() && attachedFiles.length === 0)
                    ? 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                    : 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm hover:shadow-md'
                ]"
                :aria-disabled="!newComment.trim() && attachedFiles.length === 0"
                aria-label="Post comment"
                title="Post comment"
              >
                {{ posting ? 'Posting...' : 'Post Comment' }}
              </button>
            </div>

            <!-- File Previews -->
            <div
              v-if="fileErrors.length > 0"
              class="mt-2 text-xs text-red-600 dark:text-red-400"
              role="alert"
              aria-live="polite"
            >
              <ul class="list-disc pl-5">
                <li v-for="(err, idx) in fileErrors" :key="idx">{{ err }}</li>
              </ul>
            </div>
            <div v-if="attachedFiles.length > 0" class="mt-3">
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="(file, index) in attachedFiles"
                  :key="index"
                  class="flex items-center space-x-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2 text-sm"
                >
                  <font-awesome-icon :icon="['fas', 'file']" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                  <span class="text-blue-800 dark:text-blue-200 truncate max-w-[200px]">{{ file.name }}</span>
                  <button
                    @click="removeFile(index)"
                    class="text-red-500 hover:text-red-700 transition-colors"
                  >
                    <font-awesome-icon :icon="['fas', 'xmark']" class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Comments List -->
    <div v-if="comments.length > 0" class="space-y-4">
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :current-user="currentUser"
        :can-delete="canDeleteComment(comment)"
        :can-edit="canEditComment(comment)"
        :can-pin="canPinAny"
        @delete="handleDeleteComment"
        @edit="handleEditComment"
        @react="handleReaction"
        @reply="handleReply"
        @pin="handlePin"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <font-awesome-icon :icon="['fas', 'comments']" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
      <p class="text-gray-500 dark:text-gray-400 text-lg">No comments yet</p>
      <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">Be the first to share your thoughts!</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import Avatar from '@/Components/Avatar.vue';
import CommentItem from './CommentItem.vue'

const props = defineProps({
  comments: {
    type: Array,
    default: () => []
  },
  currentUser: {
    type: Object,
    default: () => ({})
  },
  ticketId: {
    type: [String, Number],
    required: true
  },
  canDeleteAny: {
    type: Boolean,
    default: false
  },
  canPinAny: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['comment-posted', 'comment-deleted', 'comment-edited', 'reaction-toggled', 'reply-posted', 'pin-toggled'])

// Comment form state
const newComment = ref('')
const attachedFiles = ref([])
const posting = ref(false)


// Auto-resize textarea
const commentTextarea = ref(null)
const adjustTextareaHeight = () => {
  nextTick(() => {
    if (commentTextarea.value) {
      commentTextarea.value.style.height = 'auto'
      commentTextarea.value.style.height = commentTextarea.value.scrollHeight + 'px'
    }
  })
}

// File handling
const MAX_FILE_SIZE = 10 * 1024 * 1024 // 10 MB per file (matches backend max:10240 KB)
const fileErrors = ref([])

const handleFiles = (event) => {
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
    attachedFiles.value.push(...accepted)
  }
  fileErrors.value = rejected
  // allow selecting the same file again after a rejection
  event.target.value = ''
}

const removeFile = (index) => {
  attachedFiles.value.splice(index, 1)
}


// Comment submission
const submitComment = async () => {
  if (!newComment.value.trim() && attachedFiles.value.length === 0) return
  if (posting.value) return

  posting.value = true
  
  try {
    const formData = new FormData()
    formData.append('body', newComment.value)
    
    attachedFiles.value.forEach((file, index) => {
      formData.append(`files[${index}]`, file)
    })

    emit('comment-posted', {
      body: newComment.value,
      files: attachedFiles.value,
      formData
    })

    // Reset form
    newComment.value = ''
    attachedFiles.value = []
    fileErrors.value = []
    if (commentTextarea.value) {
      commentTextarea.value.style.height = 'auto'
    }
  } catch (error) {
    console.error('Error posting comment:', error)
  } finally {
    posting.value = false
  }
}

// Comment actions
const handleDeleteComment = (comment) => {
  emit('comment-deleted', comment)
}

const handleEditComment = (data) => {
  emit('comment-edited', data)
}

const handleReaction = (data) => {
  emit('reaction-toggled', data)
}

const handleReply = (data) => {
  emit('reply-posted', data)
}

const handlePin = (comment) => {
  emit('pin-toggled', comment)
}

// Permissions
const canDeleteComment = (comment) => {
  return comment.user_id === props.currentUser?.id || props.canDeleteAny
}

const canEditComment = (comment) => {
  return comment.user_id === props.currentUser?.id
}

</script>

<style scoped>
.comment-list {
  max-width: none;
}

.emoji-picker-container {
  position: relative;
}

textarea {
  min-height: 80px;
  max-height: 200px;
}

/* Custom scrollbar for emoji picker */
.emoji-picker::-webkit-scrollbar {
  width: 6px;
}

.emoji-picker::-webkit-scrollbar-track {
  background: transparent;
}

.emoji-picker::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 3px;
}

.dark .emoji-picker::-webkit-scrollbar-thumb {
  background: #4a5568;
}
</style>
