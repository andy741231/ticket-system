<template>
  <div class="rich-text-editor">
    <!-- Toolbar -->
    <div class="toolbar flex items-center gap-1 p-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 rounded-t-md">
      <button
        @click.prevent="applyFormat('bold')"
        :class="['toolbar-btn', { 'active': isFormatActive('bold') }]"
        title="Bold (Ctrl+B)"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'bold']" class="text-xs" />
      </button>
      <button
        @click.prevent="applyFormat('italic')"
        :class="['toolbar-btn', { 'active': isFormatActive('italic') }]"
        title="Italic (Ctrl+I)"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'italic']" class="text-xs" />
      </button>
      <button
        @click.prevent="applyFormat('underline')"
        :class="['toolbar-btn', { 'active': isFormatActive('underline') }]"
        title="Underline (Ctrl+U)"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'underline']" class="text-xs" />
      </button>
      <div class="w-px h-4 bg-gray-300 dark:bg-gray-600 mx-1"></div>
      <button
        @click.prevent="applyFormat('insertUnorderedList')"
        class="toolbar-btn"
        title="Bullet List"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'list-ul']" class="text-xs" />
      </button>
      <button
        @click.prevent="applyFormat('insertOrderedList')"
        class="toolbar-btn"
        title="Numbered List"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'list-ol']" class="text-xs" />
      </button>
      <div class="w-px h-4 bg-gray-300 dark:bg-gray-600 mx-1"></div>
      <button
        @click.prevent="insertLink"
        class="toolbar-btn"
        title="Insert Link"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'link']" class="text-xs" />
      </button>
      <button
        @click.prevent="applyFormat('removeFormat')"
        class="toolbar-btn"
        title="Clear Formatting"
        type="button"
      >
        <font-awesome-icon :icon="['fas', 'eraser']" class="text-xs" />
      </button>
    </div>

    <!-- Content Editable Area -->
    <div
      ref="editorRef"
      contenteditable="true"
      :class="editorClass"
      :style="editorStyle"
      @input="onInput"
      @keydown="onKeyDown"
      @paste="onPaste"
      @focus="onFocus"
      @blur="onBlur"
      @click="onClick"
      :placeholder="placeholder"
    ></div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Type here...'
  },
  editorClass: {
    type: String,
    default: 'editor-content px-3 py-2 min-h-[80px] max-h-[200px] overflow-y-auto border-x border-b border-gray-300 dark:border-gray-600 rounded-b-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'
  },
  editorStyle: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

const editorRef = ref(null)

// Set initial content
onMounted(() => {
  if (editorRef.value && props.modelValue) {
    editorRef.value.innerHTML = props.modelValue
  }
})

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (editorRef.value && editorRef.value.innerHTML !== newValue) {
    editorRef.value.innerHTML = newValue || ''
  }
})

const onInput = () => {
  const content = editorRef.value?.innerHTML || ''
  emit('update:modelValue', content)
}

const onFocus = () => {
  console.log('=== RichTextEditor onFocus ===', {
    editorRefExists: !!editorRef.value,
    activeElement: document.activeElement?.tagName,
    isFocused: document.activeElement === editorRef.value
  })
  emit('focus')
}

const onBlur = () => {
  console.log('=== RichTextEditor onBlur ===')
  // Clean up empty formatting tags
  if (editorRef.value) {
    const content = editorRef.value.innerHTML
    const cleaned = content
      .replace(/<(\w+)>\s*<\/\1>/g, '') // Remove empty tags
      .replace(/&nbsp;/g, ' ') // Replace nbsp with regular space
    
    if (cleaned !== content) {
      editorRef.value.innerHTML = cleaned
      emit('update:modelValue', cleaned)
    }
  }
  emit('blur')
}

const onClick = (event) => {
  console.log('=== RichTextEditor onClick ===', {
    target: event.target?.tagName,
    editorRefExists: !!editorRef.value,
    isContentEditable: editorRef.value?.contentEditable,
    activeElement: document.activeElement?.tagName,
    isFocused: document.activeElement === editorRef.value,
    hasSelection: !!window.getSelection()?.rangeCount
  })
  
  // Explicitly focus the editor when clicked
  if (editorRef.value && document.activeElement !== editorRef.value) {
    console.log('Editor not focused, forcing focus...')
    event.preventDefault()
    event.stopPropagation()
    editorRef.value.focus()
    
    // Verify focus was applied
    setTimeout(() => {
      console.log('After click focus - activeElement:', document.activeElement?.tagName, 'isFocused:', document.activeElement === editorRef.value)
    }, 10)
  }
}

const applyFormat = (command, value = null) => {
  document.execCommand(command, false, value)
  editorRef.value?.focus()
  onInput() // Trigger update
}

const isFormatActive = (command) => {
  return document.queryCommandState(command)
}

const insertLink = () => {
  const url = prompt('Enter URL:')
  if (url) {
    applyFormat('createLink', url)
  }
}

const onKeyDown = (event) => {
  // Handle keyboard shortcuts
  if (event.ctrlKey || event.metaKey) {
    switch (event.key.toLowerCase()) {
      case 'b':
        event.preventDefault()
        applyFormat('bold')
        break
      case 'i':
        event.preventDefault()
        applyFormat('italic')
        break
      case 'u':
        event.preventDefault()
        applyFormat('underline')
        break
    }
  }
}

const onPaste = (event) => {
  // Prevent pasting HTML, paste as plain text
  event.preventDefault()
  const text = event.clipboardData.getData('text/plain')
  document.execCommand('insertText', false, text)
}

// Public method to clear content
const clear = () => {
  if (editorRef.value) {
    editorRef.value.innerHTML = ''
    emit('update:modelValue', '')
  }
}

// Public method to focus
const focus = () => {
  console.log('=== RichTextEditor.focus() called ===', {
    editorRefExists: !!editorRef.value,
    editorRefType: typeof editorRef.value,
    isContentEditable: editorRef.value?.contentEditable,
    currentContent: editorRef.value?.innerHTML
  })
  
  try {
    if (editorRef.value) {
      console.log('Calling editorRef.value.focus()...')
      editorRef.value.focus()
      console.log('Focus called, checking if focused:', {
        isFocused: document.activeElement === editorRef.value,
        activeElement: document.activeElement?.tagName
      })
      
      // Try to set cursor at the end of content
      const range = document.createRange()
      const selection = window.getSelection()
      
      if (editorRef.value.childNodes.length > 0) {
        const lastNode = editorRef.value.childNodes[editorRef.value.childNodes.length - 1]
        range.setStartAfter(lastNode)
        range.collapse(true)
      } else {
        range.selectNodeContents(editorRef.value)
        range.collapse(false)
      }
      
      selection.removeAllRanges()
      selection.addRange(range)
      console.log('Cursor positioned at end')
    } else {
      console.error('editorRef.value is null or undefined in focus()')
    }
  } catch (error) {
    console.error('Error in RichTextEditor.focus():', error)
  }
}

defineExpose({
  clear,
  focus
})
</script>

<style scoped>
.rich-text-editor {
  @apply overflow-hidden;
}

.toolbar-btn {
  @apply p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors text-gray-700 dark:text-gray-300;
  min-width: 28px;
  min-height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toolbar-btn.active {
  @apply bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400;
}

.editor-content {
  outline: none;
}

.editor-content:empty:before {
  content: attr(placeholder);
  @apply text-gray-400 dark:text-gray-500;
  pointer-events: none;
  position: absolute;
}

.editor-content:focus:empty:before {
  content: '';
}

/* Formatting styles */
.editor-content :deep(strong),
.editor-content :deep(b) {
  @apply font-bold;
}

.editor-content :deep(em),
.editor-content :deep(i) {
  @apply italic;
}

.editor-content :deep(u) {
  @apply underline;
}

.editor-content :deep(a) {
  @apply text-blue-600 dark:text-blue-400 underline hover:text-blue-700 dark:hover:text-blue-300;
}

.editor-content :deep(ul) {
  @apply list-disc list-inside my-1;
}

.editor-content :deep(ol) {
  @apply list-decimal list-inside my-1;
}

.editor-content :deep(li) {
  @apply my-0.5;
}

/* Scrollbar styling */
.editor-content::-webkit-scrollbar {
  width: 6px;
}

.editor-content::-webkit-scrollbar-track {
  @apply bg-gray-100 dark:bg-gray-700;
}

.editor-content::-webkit-scrollbar-thumb {
  @apply bg-gray-300 dark:bg-gray-600 rounded;
}

.editor-content::-webkit-scrollbar-thumb:hover {
  @apply bg-gray-400 dark:bg-gray-500;
}
</style>
