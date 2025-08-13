<template>
  <div class="file-uploader" v-bind="$attrs">
    <!-- Drop Zone -->
    <div 
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="handleDrop"
      :class="['drop-zone', { 'drag-over': isDragging }]"
      @click="openFileDialog"
    >
      <div class="drop-zone-content">
        <svg class="w-12 h-12 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
        </svg>
        <p class="text-sm">
          <span class="font-semibold text-blue-600 dark:text-blue-400">Click to upload</span> or drag and drop
        </p>
        <p class="text-xs mt-1">
          {{ allowedFileTypesText }} (Max {{ maxFileSizeMB }}MB)
        </p>
        <input
          type="file"
          ref="fileInput"
          class="hidden"
          :multiple="multiple"
          :accept="acceptedMimeTypes.join(',')"
          @change="handleFileSelect"
        />
      </div>
    </div>

    <!-- Upload Progress -->
    <div v-if="uploading" class="mt-4">
      <div v-for="(file, index) in files" :key="file.id" class="mb-4">
        <div class="flex items-center justify-between mb-1">
          <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate mr-2" :title="file.name">
            {{ file.name }}
          </span>
          <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
            {{ formatFileSize(file.size) }}
          </span>
        </div>
        <div class="progress-container">
          <div 
            class="progress-bar" 
            :style="{ width: file.progress + '%' }"
          ></div>
        </div>
      </div>
    </div>
    <div v-else-if="uploadComplete" class="mt-4 p-3 rounded bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300">
      File upload complete
    </div>

    <!-- File List -->
    <div v-if="uploadedFiles.length > 0" class="mt-6">
      <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Uploaded Files</h4>
      <ul class="file-list">
        <li 
          v-for="file in uploadedFiles" 
          :key="file.id"
          class="file-item hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer"
          @click="openFile(file)"
        >
          <div class="file-info">
            <span class="mr-3">
              <template v-if="isImage(file.mime_type)">
                <img
                  :src="getFileUrl(file)"
                  :alt="file.original_name"
                  class="h-12 w-12 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700"
                />
              </template>
              <template v-else>
                <span class="file-icon">
                  <FileIcon :mime-type="file.mime_type" />
                </span>
              </template>
            </span>
            <div class="min-w-0">
              <span class="file-name" :title="file.original_name">
                {{ file.original_name }}
              </span>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatFileSize(file.size) }} • {{ getFileType(file.mime_type) }}
              </div>
            </div>
          </div>
          <div v-if="canDelete" class="file-actions">
            <button 
              type="button" 
              @click.stop="removeFile(file.id)"
              class="p-1 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
              :disabled="uploading"
              :title="$t('Remove file')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
              </svg>
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
  <div class="mt-4 mb-4 border-b border-gray-200 dark:border-gray-600"></div>
</template>

<script>
import { ref, computed, onUnmounted } from 'vue';
import axios from 'axios';
import FileIcon from './FileIcon.vue';

export default {
  name: 'FileUploader',
  inheritAttrs: true,
  components: {
    FileIcon,
  },
  props: {
    ticketId: {
      type: [Number, String],
      required: false,
      default: null,
    },
    // When true, uploads go to /api/temp-files and deletions use /api/temp-files/{id}
    tempMode: {
      type: Boolean,
      default: false,
    },
    multiple: {
      type: Boolean,
      default: true,
    },
    canDelete: {
      type: Boolean,
      default: true,
    },
    existingFiles: {
      type: Array,
      default: () => [],
    },
    maxFileSizeMB: {
      type: Number,
      default: 10,
    },
    allowedFileTypes: {
      type: Array,
      default: () => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
      ],
    },
  },
  emits: ['update:modelValue', 'uploaded', 'removed'],
  setup(props, { emit }) {
    const fileInput = ref(null);
    const isDragging = ref(false);
    const uploading = ref(false);
    const files = ref([]);
    const uploadComplete = ref(false);
    const uploadedFiles = ref([...props.existingFiles]);
    let cancelTokenSource = axios.CancelToken.source();
    
    // Cleanup on component unmount
    onUnmounted(() => {
      // Cancel any pending requests
      cancelTokenSource.cancel('Component unmounted');
    });

    const maxFileSize = computed(() => props.maxFileSizeMB * 1024 * 1024);
    const acceptedMimeTypes = computed(() => props.allowedFileTypes);
    
    const allowedFileTypesText = computed(() => {
      const types = {
        'image/*': 'Images',
        'application/pdf': 'PDFs',
        'application/msword': 'Word Documents',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word Documents',
        'application/vnd.ms-excel': 'Excel Spreadsheets',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel Spreadsheets',
        'text/plain': 'Text Files',
        'text/csv': 'CSV Files',
      };

      return props.allowedFileTypes
        .map(type => types[type] || type.split('/').pop().toUpperCase())
        .filter((value, index, self) => self.indexOf(value) === index)
        .join(', ');
    });

    const openFileDialog = () => {
      fileInput.value.click();
    };

    const handleDrop = (e) => {
      isDragging.value = false;
      const droppedFiles = e.dataTransfer.files;
      if (droppedFiles.length > 0) {
        processFiles(Array.from(droppedFiles));
      }
    };

    const handleFileSelect = (e) => {
      const selectedFiles = e.target.files;
      if (selectedFiles.length > 0) {
        processFiles(Array.from(selectedFiles));
        // Reset the file input
        e.target.value = '';
      }
    };

    const processFiles = (fileList) => {
      const validFiles = [];
      
      fileList.forEach(file => {
        // Check file type
        if (!props.allowedFileTypes.includes(file.type)) {
          alert(`File type not allowed: ${file.name}`);
          return;
        }
        
        // Check file size
        if (file.size > maxFileSize.value) {
          alert(`File too large (max ${props.maxFileSizeMB}MB): ${file.name}`);
          return;
        }
        
        validFiles.push({
          id: 'temp-' + Math.random().toString(36).substr(2, 9),
          file,
          name: file.name,
          size: file.size,
          progress: 0,
          status: 'pending',
        });
      });
      
      if (validFiles.length > 0) {
        // new upload starting; reset completion flag
        uploadComplete.value = false;
        files.value = [...files.value, ...validFiles];
        uploadFiles();
      }
    };

    const uploadFiles = async () => {
      if (files.value.length === 0) return;
      
      uploading.value = true;
      
      try {
        const formData = new FormData();
        
        // Append all files to the form data with 'files[]' key
        files.value.forEach(file => {
          formData.append('files[]', file.file);
        });
        
        // Create a new cancel token for this request
        cancelTokenSource = axios.CancelToken.source();
        
        // Single request with all files
        const uploadUrl = props.tempMode
          ? `/api/temp-files`
          : `/api/tickets/${props.ticketId}/files`;
        const response = await axios.post(uploadUrl, formData, {
          cancelToken: cancelTokenSource.token,
          headers: {
            'Content-Type': 'multipart/form-data',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          withCredentials: true, // Important for session cookies
          onUploadProgress: progressEvent => {
            if (!progressEvent.total) return;
            const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            // Update progress for all files
            files.value.forEach(f => {
              f.progress = progress;
              f.status = 'uploading';
            });
          },
        });

        // Add the uploaded files to the list
        if (response.data && response.data.files) {
          const newFiles = response.data.files.map(f => ({
            ...f,
            id: f.id.toString(),
          }));
          uploadedFiles.value = [...uploadedFiles.value, ...newFiles];
          emit('uploaded', newFiles);
        }
        // Mark current queued files as completed to clear the progress UI
        files.value.forEach(f => {
          f.progress = 100;
          f.status = 'completed';
        });
        // signal completion
        uploadComplete.value = true;
        
      } catch (error) {
        console.error('Error uploading files:', error);
        alert('An error occurred while uploading files. Please try again.');
      } finally {
        // Remove completed files from the upload queue
        files.value = files.value.filter(f => f.status !== 'completed');
        if (files.value.length === 0) {
          uploading.value = false;
        }
      }
    };

    const removeFile = async (fileId) => {
      try {
        const deleteUrl = props.tempMode
          ? `/api/temp-files/${fileId}`
          : `/api/tickets/${props.ticketId}/files/${fileId}`;
        await axios.delete(deleteUrl, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          withCredentials: true // Important for session cookies
        });
        
        // Remove from the UI
        const index = uploadedFiles.value.findIndex(f => f.id.toString() === fileId.toString());
        if (index !== -1) {
          const [removed] = uploadedFiles.value.splice(index, 1);
          emit('removed', removed);
        }
      } catch (error) {
        console.error('Error removing file:', error);
        alert('An error occurred while removing the file. Please try again.');
      }
    };

    const formatFileSize = (bytes) => {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    // Get a user-friendly file type from MIME type
    const getFileType = (mimeType) => {
      const typeMap = {
        // Images
        'image/jpeg': 'JPEG Image',
        'image/png': 'PNG Image',
        'image/gif': 'GIF Image',
        'image/svg+xml': 'SVG Image',
        'image/webp': 'WebP Image',
        
        // Documents
        'application/pdf': 'PDF Document',
        'application/msword': 'Word Document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word Document',
        'application/vnd.ms-excel': 'Excel Spreadsheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel Spreadsheet',
        'application/vnd.ms-powerpoint': 'PowerPoint Presentation',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint Presentation',
        'text/plain': 'Text File',
        'text/csv': 'CSV File',
        'application/rtf': 'Rich Text File',
        
        // Archives
        'application/zip': 'ZIP Archive',
        'application/x-rar-compressed': 'RAR Archive',
        'application/x-7z-compressed': '7z Archive',
        'application/x-tar': 'TAR Archive',
        'application/gzip': 'GZIP Archive',
        
        // Code
        'application/json': 'JSON File',
        'application/xml': 'XML File',
        'text/html': 'HTML File',
        'text/css': 'CSS File',
        'application/javascript': 'JavaScript File',
        
        // Default
        'application/octet-stream': 'File'
      };
      
      return typeMap[mimeType] || mimeType.split('/').pop().toUpperCase() + ' File';
    };

    // Expose methods to parent component if needed
    const clearFiles = () => {
      uploadedFiles.value = [];
    };

    return {
      fileInput,
      isDragging,
      uploading,
      files,
      uploadComplete,
      uploadedFiles,
      maxFileSize,
      acceptedMimeTypes,
      allowedFileTypesText,
      openFileDialog,
      handleDrop,
      handleFileSelect,
      formatFileSize,
      getFileType,
      isImage: (mime) => !!mime && mime.startsWith('image/'),
      getFileUrl: (file) => {
        if (!file) return '#';
        // Prefer explicit url if present (likely on freshly uploaded files)
        if (file.url) return file.url;
        // Fallback to storage path used by existing ticket files
        if (file.file_path) return `/storage/${file.file_path}`;
        return '#';
      },
      openFile: (file) => {
        const url = (file && (file.url || (file.file_path && `/storage/${file.file_path}`))) || '#';
        if (url && url !== '#') {
          window.open(url, '_blank', 'noopener,noreferrer');
        }
      },
      removeFile,
      handleFilesUploaded: (uploaded) => {
        uploadedFiles.value = [...uploadedFiles.value, ...uploaded];
        emit('uploaded', uploaded);
      },
      handleFileRemoved: (file) => {
        uploadedFiles.value = uploadedFiles.value.filter(f => f.id !== file.id);
        emit('removed', file);
      },
      clearFiles,
    };
  },
};
</script>

<style scoped>
.file-uploader {
  @apply w-full;
}

.drop-zone {
  @apply border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer transition-colors bg-white dark:bg-gray-800 hover:border-blue-500 dark:hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20;
  min-height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.drop-zone.drag-over {
  @apply border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20;
}

.drop-zone-content {
  @apply flex flex-col items-center justify-center text-gray-600 dark:text-gray-300;
}

.drop-zone-content p {
  @apply m-0 p-0;
}

.drop-zone-content svg {
  @apply w-10 h-10 mb-3 text-gray-400 dark:text-gray-500;
}

.file-icon {
  @apply w-5 h-5 text-gray-500 dark:text-gray-400 flex-shrink-0;
}

/* File list styles */
.file-list {
  @apply mt-4 space-y-3;
}

.file-item {
  @apply flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 transition-colors;
}

.file-item:hover {
  @apply bg-gray-100 dark:bg-gray-700;
}

.file-info {
  @apply flex items-center min-w-0 flex-1;
}

.file-name {
  @apply text-sm font-medium text-gray-900 dark:text-gray-100 truncate;
  max-width: 250px;
}

.file-size {
  @apply text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap ml-2;
}

.file-actions {
  @apply flex items-center space-x-2;
}

/* Progress bar */
.progress-container {
  @apply w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden mt-1;
}

.progress-bar {
  @apply bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all duration-300;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .file-item {
    @apply flex-col items-start;
  }
  
  .file-actions {
    @apply w-full justify-end mt-2 pt-2 border-t border-gray-200 dark:border-gray-600;
  }
}
</style>
