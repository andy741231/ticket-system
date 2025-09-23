<template>
  <div class="annotation-interface">
    <!-- Image Input Section -->
    <div v-if="!readonly" class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add Image for Annotation</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- URL Input -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Screenshot from URL
          </label>
          <div class="flex gap-2">
            <input
              v-model="urlInput"
              type="url"
              placeholder="https://example.com"
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
              @keydown.enter="captureFromUrl"
            />
            <button
              @click="captureFromUrl"
              :disabled="!urlInput || isProcessing"
              class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Capture
            </button>
          </div>
        </div>
        
        <!-- File Upload -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Upload File
          </label>
          <input
            ref="fileInput"
            type="file"
            accept="image/*,.pdf"
            class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
            @change="uploadFile"
          />
        </div>
      </div>
      
      <!-- Processing Status -->
      <div v-if="processingImages.length > 0" class="mt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Processing Images</h4>
        <div class="space-y-2">
          <div
            v-for="image in processingImages"
            :key="image.id"
            class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-700 rounded"
          >
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
            <span class="text-sm text-gray-600 dark:text-gray-400">
              {{ image.source_type === 'url' ? 'Capturing screenshot...' : 'Processing file...' }}
            </span>
            <span class="text-xs text-gray-500">{{ image.source_value }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Images List with Sidebar Layout -->
    <div v-if="images.length > 0" class="space-y-6">
      <div
        v-for="image in images"
        :key="image.id"
        class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
      >
        <!-- Image Header -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ image.original_name || 'Annotation Image' }}</h4>
            <div class="flex items-center gap-2">
              <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ formatFileSize(image.file_size) }}
              </div>
              <button
                v-if="!readonly"
                @click="deleteImage(image)"
                class="p-1 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                title="Delete image and all annotations"
                aria-label="Delete image"
              >
                <i class="fas fa-trash text-sm"></i>
              </button>
            </div>
          </div>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ image.source_type === 'url' ? 'Screenshot' : 'Uploaded File' }} • 
                {{ formatDate(image.created_at) }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <!-- Status Badge -->
              <span
                :class="getStatusBadgeClass(image.status)"
                class="px-2 py-1 rounded-full text-xs font-medium"
              >
                {{ image.status }}
              </span>
              
              <!-- Actions -->
              <button
                v-if="!readonly && allowDelete"
                @click="deleteImage(image)"
                class="p-1 text-red-500 hover:text-red-700 transition-colors"
                title="Delete image"
              >
                <i class="fas fa-trash text-sm"></i>
              </button>
            </div>
          </div>
        </div>
        
        <!-- Image Content with Sidebar Layout -->
        <div class="p-4">
          <div v-if="image.status === 'processing'" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
            <p class="text-gray-500 dark:text-gray-400">Processing image...</p>
          </div>
          
          <div v-else-if="image.status === 'failed'" class="text-center py-8">
            <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
            <p class="text-red-600 dark:text-red-400">{{ image.error_message || 'Failed to process image' }}</p>
          </div>
          
          <div v-else-if="image.status === 'completed'">
            <!-- Main Layout: Canvas + Sidebar -->
            <div class="flex gap-6">
              <!-- Left: Annotation Canvas -->
              <div class="flex-1 min-w-0">
                <AnnotationCanvas
                  :ref="el => setCanvasRef(el, image.id)"
                  :image-url="image.image_url"
                  :image-name="image.original_name || 'Annotation Image'"
                  :annotations="getImageAnnotations(image.id)"
                  :can-edit="canEditAnnotation"
                  :can-delete="canDeleteAnnotation"
                  :readonly="readonly"
                  @annotation-created="createAnnotation(image.id, $event)"
                  @annotation-updated="updateAnnotation($event)"
                  @annotation-deleted="deleteAnnotation($event)"
                  @annotation-selected="handleAnnotationSelected($event)"
                />
              </div>
              
              <!-- Right: Annotations Sidebar -->
              <div class="w-80 flex-shrink-0">
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 h-full">
                  <!-- Sidebar Header -->
                  <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                      <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Annotations ({{ getImageAnnotations(image.id).length }})
                      </h5>
                      <div class="flex items-center gap-2">
                        <!-- Filter Dropdown -->
                        <select
                          v-model="annotationFilter"
                          class="text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                          aria-label="Filter annotations by status"
                        >
                          <option value="all">All</option>
                          <option value="pending">Pending</option>
                          <option value="approved">Approved</option>
                          <option value="rejected">Rejected</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Annotations List -->
                  <div 
                    class="overflow-y-auto annotation-list" 
                    style="max-height: 500px;"
                    role="listbox"
                    aria-label="Annotation list"
                    tabindex="0"
                    @keydown="(e) => handleAnnotationListKeydown(e, image.id)"
                  >
                    <div v-if="filteredAnnotations(image.id).length === 0" class="p-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                      <i class="fas fa-comment-dots text-2xl mb-2 block"></i>
                      No annotations {{ annotationFilter !== 'all' ? `(${annotationFilter})` : '' }}
                    </div>
                    
                    <div
                      v-for="(annotation, index) in filteredAnnotations(image.id)"
                      :key="annotation.id"
                      class="border-b border-gray-200 dark:border-gray-700 last:border-b-0 cursor-pointer transition-colors annotation-list-item"
                      :class="{
                        'bg-blue-50 dark:bg-blue-900/20': selectedAnnotation?.id === annotation.id,
                        'hover:bg-gray-100 dark:hover:bg-gray-800': selectedAnnotation?.id !== annotation.id
                      }"
                      :tabindex="selectedAnnotation?.id === annotation.id ? 0 : -1"
                      role="option"
                      :aria-selected="selectedAnnotation?.id === annotation.id"
                      :aria-label="`Annotation ${index + 1}: ${annotation.content || annotation.type} by ${annotation.user?.name || 'Unknown'}`"
                      @click="focusAnnotation(annotation, image.id)"
                      @keydown.enter="focusAnnotation(annotation, image.id)"
                      @keydown.space.prevent="focusAnnotation(annotation, image.id)"
                    >
                      <div class="p-4">
                        <!-- Annotation Header -->
                        <div class="flex items-start gap-3">
                          <!-- Number Badge -->
                          <div
                            class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold text-white flex-shrink-0"
                            :class="{
                              'bg-blue-500': annotation.status === 'pending' || !annotation.status,
                              'bg-green-500': annotation.status === 'approved',
                              'bg-red-500': annotation.status === 'rejected',
                              'bg-yellow-500': annotation.status === 'pending'
                            }"
                          >
                            {{ getAnnotationIndex(image.id, annotation.id) + 1 }}
                          </div>
                          
                          <!-- Annotation Info -->
                          <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                              <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ annotation.user?.name || 'Unknown' }}
                              </span>
                              <span
                                :class="getAnnotationStatusClass(annotation.status)"
                                class="px-1.5 py-0.5 rounded text-xs font-medium flex-shrink-0"
                              >
                                {{ annotation.status || 'pending' }}
                              </span>
                            </div>
                            
                            <p v-if="annotation.content" class="text-sm text-gray-700 dark:text-gray-300 mb-2 line-clamp-2">
                              {{ annotation.content }}
                            </p>
                            
                            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                              <span>{{ annotation.type }}</span>
                              <span>•</span>
                              <span>{{ formatDate(annotation.created_at) }}</span>
                            </div>
                          </div>
                          
                          <!-- Quick Actions -->
                          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity" :class="{ 'opacity-100': selectedAnnotation?.id === annotation.id }">
                            <!-- Approve/Reject (Admin only) -->
                            <div v-if="canReviewAnnotations && !readonly && annotation.status === 'pending'" class="flex gap-1">
                              <button
                                @click.stop="approveAnnotation(annotation)"
                                class="p-1 bg-green-500 text-white text-xs rounded hover:bg-green-600 transition-colors focus:ring-2 focus:ring-green-300"
                                title="Approve annotation"
                                aria-label="Approve this annotation"
                              >
                                <i class="fas fa-check" aria-hidden="true"></i>
                              </button>
                              <button
                                @click.stop="rejectAnnotation(annotation)"
                                class="p-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition-colors focus:ring-2 focus:ring-red-300"
                                title="Reject annotation"
                                aria-label="Reject this annotation"
                              >
                                <i class="fas fa-times" aria-hidden="true"></i>
                              </button>
                            </div>
                            
                            <!-- Delete -->
                            <button
                              v-if="!readonly && canDeleteAnnotation(annotation)"
                              @click.stop="deleteAnnotation(annotation)"
                              class="p-1 bg-gray-500 text-white text-xs rounded hover:bg-gray-600 transition-colors focus:ring-2 focus:ring-gray-300"
                              title="Delete annotation"
                              aria-label="Delete this annotation"
                            >
                              <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                          </div>
                        </div>
                        
                        <!-- Review Notes -->
                        <div v-if="annotation.review_notes" class="mt-3 p-2 bg-gray-100 dark:bg-gray-800 rounded text-sm">
                          <div class="flex items-start gap-2">
                            <i class="fas fa-sticky-note text-gray-500 mt-0.5 flex-shrink-0"></i>
                            <div>
                              <p class="text-gray-700 dark:text-gray-300">{{ annotation.review_notes }}</p>
                              <p class="text-xs text-gray-500 mt-1">
                                {{ annotation.reviewer?.name }} • {{ formatDate(annotation.reviewed_at) }}
                              </p>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Comments Preview -->
                        <div v-if="annotation.comments && annotation.comments.length > 0" class="mt-3">
                          <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-comments text-gray-400 text-xs"></i>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                              {{ annotation.comments.length }} comment{{ annotation.comments.length !== 1 ? 's' : '' }}
                            </span>
                          </div>
                          
                          <!-- Latest Comment Preview -->
                          <div class="bg-white dark:bg-gray-800 rounded p-2 border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center gap-2 mb-1">
                              <span class="text-xs font-medium text-gray-900 dark:text-gray-100">
                                {{ annotation.comments[annotation.comments.length - 1].user?.name }}
                              </span>
                              <span class="text-xs text-gray-500">
                                {{ formatDate(annotation.comments[annotation.comments.length - 1].created_at) }}
                              </span>
                            </div>
                            <p class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">
                              {{ annotation.comments[annotation.comments.length - 1].content }}
                            </p>
                          </div>
                        </div>
                        
                        <!-- Quick Comment Input -->
                        <div v-if="!readonly && selectedAnnotation?.id === annotation.id" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                          <div class="flex gap-2">
                            <input
                              v-model="newComments[annotation.id]"
                              type="text"
                              placeholder="Add a comment..."
                              class="flex-1 px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                              @keydown.enter="addComment(annotation.id)"
                            />
                            <button
                              @click="addComment(annotation.id)"
                              :disabled="!newComments[annotation.id]?.trim()"
                              class="px-2 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 disabled:opacity-50 transition-colors"
                            >
                              <i class="fas fa-paper-plane"></i>
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Empty State -->
    <div v-if="images.length === 0 && processingImages.length === 0 && showEmptyState" class="text-center py-12">
      <i class="fas fa-image text-gray-400 text-4xl mb-4"></i>
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No Images</h3>
      <p v-if="!readonly" class="text-gray-500 dark:text-gray-400">Add an image by entering a URL or uploading a file to start annotating.</p>
      <p v-else class="text-gray-500 dark:text-gray-400">No images have been added for this ticket.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AnnotationCanvas from './AnnotationCanvas.vue'

const props = defineProps({
  ticketId: {
    type: Number,
    required: true
  },
  canReviewAnnotations: {
    type: Boolean,
    default: false
  },
  readonly: {
    type: Boolean,
    default: false
  },
  showEmptyState: {
    type: Boolean,
    default: true
  },
  allowDelete: {
    type: Boolean,
    default: true
  }
})

// Reactive data
const images = ref([])
const processingImages = ref([])
const annotations = ref([])
const urlInput = ref('')
const isProcessing = ref(false)
const newComments = ref({})
const selectedAnnotation = ref(null)
const annotationFilter = ref('all')
// Map of imageId -> AnnotationCanvas component instance
const canvasRefs = ref({})

// Helper to attach/detach canvas refs from template
const setCanvasRef = (el, imageId) => {
  if (el) {
    canvasRefs.value[imageId] = el
  } else if (canvasRefs.value[imageId]) {
    delete canvasRefs.value[imageId]
  }
}

// File input ref
const fileInput = ref(null)

// Computed
const canEditAnnotation = (annotation) => {
  return !props.readonly && (annotation.user_id === window.auth?.user?.id || props.canReviewAnnotations)
}

const canDeleteAnnotation = (annotation) => {
  return !props.readonly && props.allowDelete && (annotation.user_id === window.auth?.user?.id || props.canReviewAnnotations)
}

// Sidebar methods
const filteredAnnotations = (imageId) => {
  const imageAnnotations = getImageAnnotations(imageId)
  if (annotationFilter.value === 'all') {
    return imageAnnotations
  }
  return imageAnnotations.filter(annotation => 
    (annotation.status || 'pending') === annotationFilter.value
  )
}

const getAnnotationIndex = (imageId, annotationId) => {
  const imageAnnotations = getImageAnnotations(imageId)
  return imageAnnotations.findIndex(annotation => annotation.id === annotationId)
}

const focusAnnotation = (annotation, imageId) => {
  selectedAnnotation.value = annotation

  // Forward selection to the correct canvas instance if available
  const comp = canvasRefs.value?.[imageId]
  if (comp && typeof comp.selectAnnotation === 'function') {
    try {
      comp.selectAnnotation(annotation)
    } catch (e) {
      console.warn('Failed to forward selection to canvas:', e)
    }
  }

  // Note: zoom-to-annotation could be implemented via another exposed method
}

const handleAnnotationSelected = (annotation) => {
  selectedAnnotation.value = annotation
}

// Keyboard navigation for annotation list
const handleAnnotationListKeydown = (event, imageId) => {
  const annotations = filteredAnnotations(imageId)
  if (annotations.length === 0) return
  
  const currentIndex = selectedAnnotation.value 
    ? annotations.findIndex(a => a.id === selectedAnnotation.value.id)
    : -1
  
  let newIndex = currentIndex
  
  switch (event.key) {
    case 'ArrowDown':
      newIndex = Math.min(currentIndex + 1, annotations.length - 1)
      event.preventDefault()
      break
    case 'ArrowUp':
      newIndex = Math.max(currentIndex - 1, 0)
      event.preventDefault()
      break
    case 'Home':
      newIndex = 0
      event.preventDefault()
      break
    case 'End':
      newIndex = annotations.length - 1
      event.preventDefault()
      break
    case 'Enter':
    case ' ':
      if (currentIndex >= 0) {
        focusAnnotation(annotations[currentIndex])
        event.preventDefault()
      }
      break
  }
  
  if (newIndex !== currentIndex && newIndex >= 0) {
    focusAnnotation(annotations[newIndex])
  }
}

// Methods
const loadImages = async () => {
  try {
    const response = await fetch(`/api/tickets/${props.ticketId}/images`, {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      }
    })
    
    if (response.ok) {
      const data = await response.json()
      images.value = data.data || []
      
      // Extract all annotations
      annotations.value = []
      images.value.forEach(image => {
        if (image.annotations) {
          annotations.value.push(...image.annotations)
        }
      })
    }
  } catch (error) {
    console.error('Failed to load images:', error)
  }
}

const captureFromUrl = async () => {
  if (!urlInput.value || isProcessing.value) return
  
  isProcessing.value = true
  
  try {
    const response = await fetch(`/api/tickets/${props.ticketId}/images/from-url`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({ url: urlInput.value })
    })
    
    if (response.ok) {
      const data = await response.json()
      processingImages.value.push(data.data)
      urlInput.value = ''
      
      // Poll for completion
      pollImageStatus(data.data.id)
    } else {
      const error = await response.json()
      alert('Failed to capture screenshot: ' + (error.message || 'Unknown error'))
    }
  } catch (error) {
    console.error('Failed to capture from URL:', error)
    alert('Failed to capture screenshot')
  } finally {
    isProcessing.value = false
  }
}

const uploadFile = async (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('file', file)
  
  try {
    const response = await fetch(`/api/tickets/${props.ticketId}/images/from-file`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: formData
    })
    
    if (response.ok) {
      const data = await response.json()
      processingImages.value.push(data.data)
      
      // Poll for completion
      pollImageStatus(data.data.id)
    } else {
      const error = await response.json()
      alert('Failed to upload file: ' + (error.message || 'Unknown error'))
    }
  } catch (error) {
    console.error('Failed to upload file:', error)
    alert('Failed to upload file')
  }
  
  // Reset file input
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const pollImageStatus = async (imageId) => {
  const maxAttempts = 30 // 30 seconds max
  let attempts = 0
  
  const poll = async () => {
    try {
      const response = await fetch(`/api/tickets/${props.ticketId}/images/${imageId}/status`, {
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
          'Accept': 'application/json'
        }
      })
      
      if (response.ok) {
        const data = await response.json()
        const status = data.data.status
        
        if (status === 'completed' || status === 'failed') {
          // Remove from processing list
          processingImages.value = processingImages.value.filter(img => img.id !== imageId)
          
          // Reload images list
          await loadImages()
          return
        }
      }
      
      attempts++
      if (attempts < maxAttempts) {
        setTimeout(poll, 1000) // Poll every second
      } else {
        // Remove from processing list after timeout
        processingImages.value = processingImages.value.filter(img => img.id !== imageId)
      }
    } catch (error) {
      console.error('Failed to poll image status:', error)
      processingImages.value = processingImages.value.filter(img => img.id !== imageId)
    }
  }
  
  setTimeout(poll, 1000) // Start polling after 1 second
}


const getImageAnnotations = (imageId) => {
  return annotations.value.filter(annotation => annotation.ticket_image_id === imageId)
}

const createAnnotation = async (imageId, annotationData) => {
  try {
    const response = await fetch(`/api/tickets/${props.ticketId}/images/${imageId}/annotations`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify(annotationData)
    })
    
    if (response.ok) {
      const data = await response.json()
      annotations.value.push(data.data)
    } else {
      const error = await response.json()
      alert('Failed to create annotation: ' + (error.message || 'Unknown error'))
    }
  } catch (error) {
    console.error('Failed to create annotation:', error)
    alert('Failed to create annotation')
  }
}

const updateAnnotation = async (annotation) => {
  // Implementation for updating annotations
  console.log('Update annotation:', annotation)
}

const deleteAnnotation = async (annotation) => {
  if (!confirm('Are you sure you want to delete this annotation?')) {
    return
  }
  
  try {
    const response = await fetch(`/api/tickets/${props.ticketId}/images/${annotation.ticket_image_id}/annotations/${annotation.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      }
    })
    
    if (response.ok) {
      // Remove from local state
      const index = annotations.value.findIndex(a => a.id === annotation.id)
      if (index !== -1) {
        annotations.value.splice(index, 1)
      }
      
      // Clear selection if this was selected
      if (selectedAnnotation.value?.id === annotation.id) {
        selectedAnnotation.value = null
      }
    } else {
      throw new Error('Failed to delete annotation')
    }
  } catch (error) {
    console.error('Error deleting annotation:', error)
    alert('Failed to delete annotation. Please try again.')
  }
}

const deleteImage = async (image) => {
  if (!confirm(`Are you sure you want to delete "${image.original_name || 'this image'}" and all its annotations? This action cannot be undone.`)) {
    return
  }
  
  try {
    const response = await fetch(`/api/tickets/${props.ticketId}/images/${image.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      }
    })
    
    if (response.ok) {
      // Remove image from local state
      const imageIndex = images.value.findIndex(img => img.id === image.id)
      if (imageIndex !== -1) {
        images.value.splice(imageIndex, 1)
      }
      
      // Remove all annotations for this image
      annotations.value = annotations.value.filter(a => a.ticket_image_id !== image.id)
      
      // Clear selection if it was from this image
      if (selectedAnnotation.value?.ticket_image_id === image.id) {
        selectedAnnotation.value = null
      }
      
      // Clear canvas refs for this image
      if (canvasRefs.value[image.id]) {
        delete canvasRefs.value[image.id]
      }
    } else {
      throw new Error('Failed to delete image')
    }
  } catch (error) {
    console.error('Error deleting image:', error)
    alert('Failed to delete image. Please try again.')
  }
}

const selectAnnotation = (annotation) => {
  selectedAnnotation.value = annotation
}

const approveAnnotation = async (annotation) => {
  await updateAnnotationStatus(annotation, 'approved')
}

const rejectAnnotation = async (annotation) => {
  const notes = prompt('Rejection reason (optional):')
  await updateAnnotationStatus(annotation, 'rejected', notes)
}

const updateAnnotationStatus = async (annotation, status, reviewNotes = '') => {
  try {
    const imageId = annotation.ticket_image_id
    const response = await fetch(`/api/tickets/${props.ticketId}/images/${imageId}/annotations/${annotation.id}/status`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({ status, review_notes: reviewNotes })
    })
    
    if (response.ok) {
      const data = await response.json()
      const index = annotations.value.findIndex(a => a.id === annotation.id)
      if (index !== -1) {
        annotations.value[index] = data.data
      }
    } else {
      const error = await response.json()
      alert('Failed to update annotation status: ' + (error.message || 'Unknown error'))
    }
  } catch (error) {
    console.error('Failed to update annotation status:', error)
    alert('Failed to update annotation status')
  }
}

const addComment = async (annotationId) => {
  const content = newComments.value[annotationId]?.trim()
  if (!content) return
  
  const annotation = annotations.value.find(a => a.id === annotationId)
  if (!annotation) return
  
  try {
    const imageId = annotation.ticket_image_id
    const response = await fetch(`/api/tickets/${props.ticketId}/images/${imageId}/annotations/${annotationId}/comments`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({ content })
    })
    
    if (response.ok) {
      const data = await response.json()
      
      // Add comment to annotation
      const annotationIndex = annotations.value.findIndex(a => a.id === annotationId)
      if (annotationIndex !== -1) {
        if (!annotations.value[annotationIndex].comments) {
          annotations.value[annotationIndex].comments = []
        }
        annotations.value[annotationIndex].comments.push(data.data)
      }
      
      // Clear input
      newComments.value[annotationId] = ''
    } else {
      const error = await response.json()
      alert('Failed to add comment: ' + (error.message || 'Unknown error'))
    }
  } catch (error) {
    console.error('Failed to add comment:', error)
    alert('Failed to add comment')
  }
}

// Utility functions
const getImageTitle = (image) => {
  if (image.source_type === 'url') {
    try {
      const url = new URL(image.source_value)
      return url.hostname
    } catch {
      return 'Screenshot'
    }
  }
  return image.source_value || 'Uploaded File'
}

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'processing':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    case 'completed':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    case 'failed':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }
}

const getAnnotationStatusClass = (status) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    case 'approved':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    case 'rejected':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString()
}

// Local helper used in template to display human-readable sizes
const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatCoordinates = (coordinates) => {
  if (coordinates.x !== undefined && coordinates.y !== undefined) {
    return `(${Math.round(coordinates.x)}, ${Math.round(coordinates.y)})`
  }
  return 'Complex shape'
}

// Lifecycle
onMounted(() => {
  loadImages()
})

// If selected annotation was deleted, clear selection
watch(annotations, () => {
  if (selectedAnnotation.value) {
    const stillExists = annotations.value.find(a => a.id === selectedAnnotation.value.id)
    if (!stillExists) selectedAnnotation.value = null
  }
}, { deep: true })

// Announce changes to screen readers
const announceToScreenReader = (message) => {
  // Create a temporary element for screen reader announcements
  const announcement = document.createElement('div')
  announcement.setAttribute('aria-live', 'polite')
  announcement.setAttribute('aria-atomic', 'true')
  announcement.className = 'sr-only'
  announcement.textContent = message
  
  document.body.appendChild(announcement)
  
  // Remove after announcement
  setTimeout(() => {
    document.body.removeChild(announcement)
  }, 1000)
}

// Watch for annotation selection changes to announce
watch(selectedAnnotation, (newAnnotation, oldAnnotation) => {
  if (newAnnotation && newAnnotation !== oldAnnotation) {
    const message = `Selected annotation ${newAnnotation.content || newAnnotation.type} by ${newAnnotation.user?.name || 'Unknown'}`
    announceToScreenReader(message)
  }
})
</script>

<style scoped>
.annotation-interface {
  max-width: 100%;
}

/* Line clamp utility for text truncation */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Sidebar styling */
.annotation-sidebar {
  min-height: 500px;
}

/* Hover effects for annotation list items */
.annotation-list-item {
  transition: all 0.2s ease;
}

.annotation-list-item:hover .opacity-0 {
  opacity: 1;
}

/* Status badge colors */
.status-pending {
  background-color: #f59e0b;
  color: white;
}

.status-approved {
  background-color: #10b981;
  color: white;
}

.status-rejected {
  background-color: #ef4444;
  color: white;
}

/* Smooth scrolling for sidebar */
.annotation-list {
  scroll-behavior: smooth;
}

/* Focus styles for accessibility */
.annotation-list-item:focus {
  outline: 2px solid #3b82f6;
  outline-offset: -2px;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .flex-gap-6 {
    flex-direction: column;
    gap: 1rem;
  }
  
  .w-80 {
    width: 100%;
  }
}

/* Dark mode adjustments */
.dark .bg-gray-50 {
  background-color: rgb(17 24 39);
}

.dark .border-gray-200 {
  border-color: rgb(55 65 81);
}

/* Animation for comment input */
.comment-input-enter-active,
.comment-input-leave-active {
  transition: all 0.3s ease;
}

.comment-input-enter-from,
.comment-input-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Screen reader only class */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Enhanced focus indicators for accessibility */
.annotation-list-item:focus {
  outline: 2px solid #3b82f6;
  outline-offset: -2px;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

/* Improved button focus states */
button:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .annotation-list-item:focus {
    outline-width: 3px;
  }
  
  button:focus-visible {
    outline-width: 3px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .annotation-list-item,
  .comment-input-enter-active,
  .comment-input-leave-active {
    transition: none;
  }
}
</style>
