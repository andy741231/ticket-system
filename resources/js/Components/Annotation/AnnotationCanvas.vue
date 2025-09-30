<template>
  <div class="annotation-canvas-container flex relative bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden">
    <!-- Main Canvas Area -->
    <div class="flex-1 relative">
    <!-- Toolbar -->
     <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
    <div class="max-w-5xl mx-auto annotation-toolbar flex items-center justify-between p-3">
      <!-- Left: Drawing Tools -->
      <div class="flex items-center gap-2">
        <!-- Tool Buttons -->
        <div class="flex items-center gap-1 mr-4">
          <button
            v-for="tool in tools"
            :key="tool.name"
            @click="currentTool = tool.name"
            :class="[
              'p-2 rounded-md transition-colors',
              currentTool === tool.name
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
            ]"
            :title="tool.label"
            :disabled="readonly"
          >
            <font-awesome-icon :icon="tool.icon" class="text-sm" />
          </button>
          <!-- Clear Freehand Annotations -->
          <button
            v-if="!readonly"
            @click="clearFreehandAnnotations"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            title="Clear all freehand annotations"
            aria-label="Clear all freehand annotations"
          >
            <font-awesome-icon :icon="['fas','trash']" class="text-sm" />
          </button>
        </div>
        
        <!-- Color Swatches -->
        <div class="flex items-center gap-1 mr-4">
          <button
            v-for="color in colorPalette"
            :key="color"
            @click="currentStyle.color = color"
            :class="[
              'w-6 h-6 rounded border-2 transition-all',
              currentStyle.color === color
                ? 'border-gray-800 dark:border-gray-200 scale-110'
                : 'border-gray-300 dark:border-gray-600 hover:scale-105'
            ]"
            :style="{ backgroundColor: color }"
            :title="`Color: ${color}`"
            :disabled="readonly"
          ></button>
          <input
            v-model="currentStyle.color"
            type="color"
            class="w-6 h-6 rounded border border-gray-300 dark:border-gray-600 cursor-pointer"
            title="Custom color"
            :disabled="readonly"
          />
        </div>
        
        <!-- Stroke Width -->
        <div class="flex items-center gap-2 mr-4">
          <label class="text-xs text-gray-600 dark:text-gray-400">Width:</label>
          <input
            v-model.number="currentStyle.strokeWidth"
            type="range"
            min="1"
            max="8"
            class="w-16"
            :disabled="readonly"
          />
          <span class="text-xs text-gray-600 dark:text-gray-400 w-6">{{ currentStyle.strokeWidth }}</span>
        </div>
      </div>
      
      <!-- Right: View Controls -->
      <div class="flex items-center gap-2">
        <!-- Zoom Controls -->
        <div class="flex items-center gap-1">
          <button
            @click="zoomOut"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            title="Zoom out"
          >
            <font-awesome-icon :icon="['fas','search-minus']" class="text-sm" />
          </button>
          <span class="text-xs text-gray-600 dark:text-gray-400 min-w-12 text-center">{{ Math.round(zoomLevel * 100) }}%</span>
          <button
            @click="zoomIn"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            title="Zoom in"
          >
            <font-awesome-icon :icon="['fas','search-plus']" class="text-sm" />
          </button>
          <button
            @click="toggleFit"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            :title="fitMode === 'width' ? 'Fit to width' : 'Fit to height'"
          >
            <font-awesome-icon :icon="fitMode === 'width' ? ['fas','expand-arrows-alt'] : ['fas','compress-arrows-alt']" class="text-sm" />
          </button>
        </div>
        
        <!-- Undo/Redo -->
        <div class="flex items-center gap-1 ml-2">
          <button
            @click="undo"
            :disabled="!canUndo || readonly"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            title="Undo (Ctrl+Z)"
            aria-label="Undo last action"
          >
            <font-awesome-icon :icon="['fas','undo']" class="text-sm" />
          </button>
          <button
            @click="redo"
            :disabled="!canRedo || readonly"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            title="Redo (Ctrl+Shift+Z)"
            aria-label="Redo last undone action"
          >
            <font-awesome-icon :icon="['fas','redo']" class="text-sm" />
          </button>
        </div>
        
        <!-- Help Button -->
        <div class="flex items-center gap-1 ml-2">
          <button
            @click="showKeyboardHelp = !showKeyboardHelp"
            class="p-2 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            title="Keyboard shortcuts (?)"
            aria-label="Show keyboard shortcuts"
          >
            <font-awesome-icon :icon="['fas','keyboard']" class="text-sm" />
          </button>
       
        </div>
      </div>
      </div>
    </div>
    
    <!-- Image Viewport -->
    <div 
      ref="viewportRef"
      class="annotation-viewport relative overflow-hidden" 
      :style="{ height: 'calc(100vh - 64px)' }"
      @wheel.prevent="onWheel"
      @mousedown="onViewportMouseDown"
      @mousemove="onViewportMouseMove"
      @mouseup="onViewportMouseUp"
      @mouseleave="onViewportMouseUp"
    >
      <div 
        class="annotation-content-wrapper relative"
        :style="{
          transform: `translate(${panOffset.x}px, ${panOffset.y}px) scale(${zoomLevel})`,
          transformOrigin: '0 0',
          transition: isAnimating ? 'transform 0.3s ease' : 'none'
        }"
      >
        <img
          ref="imageRef"
          :src="imageUrl"
          :alt="imageName"
          class="block border border-gray-300 dark:border-gray-600 rounded-lg"
          @load="onImageLoad"
          @error="onImageError"
          draggable="false"
        />
        
        <!-- Canvas Overlay for Annotations -->
        <canvas
          ref="canvasRef"
          class="absolute top-0 left-0"
          :class="{
            'cursor-crosshair': !readonly && !['grab', 'select'].includes(currentTool),
            'cursor-grab': currentTool === 'grab' || readonly,
            'cursor-grabbing': isPanning,
            'cursor-select': currentTool === 'select',
            'cursor-text': currentTool === 'text'
          }"
          :width="canvasWidth"
          :height="canvasHeight"
          @mousedown="onCanvasMouseDown"
          @mousemove="onCanvasMouseMove"
          @mouseup="onCanvasMouseUp"
          @click="onCanvasClick"
          @contextmenu.prevent="onRightClick"
        ></canvas>
        
        <!-- Direct Text Input -->
        <input
          v-if="isEditingText"
          ref="textEditElement"
          v-model="textEditContent"
          type="text"
          class="absolute bg-white dark:bg-gray-700 border border-blue-300 dark:border-blue-500 rounded px-2 py-1 outline-none text-blue-600 dark:text-blue-400 font-medium z-20 shadow-md"
          :style="{
            left: Math.max(0, Math.min(textEditPosition.x, (canvasWidth || 800) - 150)) + 'px',
            top: Math.max(0, Math.min(textEditPosition.y - 20, (canvasHeight || 600) - 40)) + 'px',
            fontSize: '14px',
            minWidth: '100px',
            maxWidth: '200px'
          }"
          @keydown.stop
          @blur="finishTextEdit"
          @keydown.enter="finishTextEdit"
          @keydown.escape="cancelTextEdit"
          placeholder="Type here..."
        />
        
        <!-- Numbered Annotation Markers -->
        <div
          v-for="(annotation, index) in annotations"
          :key="annotation.id"
          class="absolute annotation-marker"
          :style="getAnnotationStyle(annotation)"
          @mousedown="onAnnotationMarkerMouseDown(annotation, $event)"
          @click="selectAnnotation(annotation)"
          @mouseenter="hoveredAnnotation = annotation"
          @mouseleave="hoveredAnnotation = null"
          :class="{
            'selected': selectedAnnotation?.id === annotation.id,
            'hovered': hoveredAnnotation?.id === annotation.id,
            'pending': annotation.status === 'pending',
            'approved': annotation.status === 'approved',
            'rejected': annotation.status === 'rejected'
          }"
        >
          <div class="annotation-number">
            {{ index + 1 }}
          </div>
          <!-- Always-available actions overlay (shown on hover/selected) -->
          <div v-if="!readonly" class="annotation-actions">
            <button
              v-if="canEdit(annotation) && annotation.type === 'text'"
              @click.stop="editAnnotation(annotation)"
              class="action-btn edit-btn"
              title="Edit annotation"
              aria-label="Edit annotation"
            >
              <font-awesome-icon :icon="['fas','edit']" class="text-xs" />
            </button>
            <button
              v-if="canDelete(annotation)"
              @click.stop="deleteAnnotation(annotation)"
              class="action-btn delete-btn"
              title="Delete annotation"
              aria-label="Delete annotation"
            >
              <font-awesome-icon :icon="['fas','trash']" class="text-xs" />
            </button>
          </div>
          

          <!-- Overlay text/comment box for this annotation -->
          <div v-if="getOverlayContent(annotation)" class="annotation-comment-box ml-3">
            <div class="flex items-start gap-2">
              <div class="flex-shrink-0">
                <Avatar :user="getDisplayUserForAnnotation(annotation)" size="xs" :show-link="false" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                  <div class="text-xs text-gray-800 dark:text-gray-100 break-words">
                    <div class="text-gray-700 dark:text-gray-300" v-html="linkifyText(getOverlayContent(annotation))">
                    </div>
                  </div>
                  <div v-if="!readonly" class="flex items-center gap-1">
                    
                  </div>
                </div>
              </div>
            </div>
          </div>

          
        </div>
      </div>
    </div>
    </div>
    
    <!-- Comments Panel -->
    <div class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 flex flex-col" :style="{ height: 'calc(100vh - 64px)' }">
      <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Comments</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ combinedComments.length }} comment{{ combinedComments.length !== 1 ? 's' : '' }}</p>
      </div>

      <!-- Add Comment Form -->
      <div class="p-4 border-t border-gray-200 dark:border-gray-700">
        <div v-if="selectedAnnotation" class="mb-3 text-sm text-blue-600 dark:text-blue-400">
          <font-awesome-icon :icon="['fas', 'reply']" class="mr-1" />
          Commenting on Annotation #{{ getAnnotationNumber(selectedAnnotation.id) }}
        </div>
        <div class="flex gap-2">
          <MentionAutocomplete
            v-model="newCommentContent"
            :ticket-id="ticketId"
            placeholder="Add a comment... Use @username to mention"
            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm resize-none"
            rows="2"
            @keydown.ctrl.enter="addComment"
            @keydown.meta.enter="addComment"
          />
          <button
            @click="addComment"
            :disabled="!newCommentContent.trim()"
            class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <font-awesome-icon :icon="['fas', 'paper-plane']" class="text-sm" />
          </button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ctrl+Enter to send</p>
      </div>
      
      <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div v-if="combinedComments.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-8">
          <font-awesome-icon :icon="['fas', 'comments']" class="text-4xl mb-2" />
          <p>No comments yet</p>
          <p class="text-sm">Add a text annotation on the canvas or use the panel below</p>
        </div>
        
        <div
          v-for="entry in combinedComments"
          :key="`${entry.type}-${entry.id}`"
          class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 transition-colors cursor-default"
          :class="{
            'hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer': entry.type === 'annotation' || entry.annotation,
            'ring-1 ring-blue-400/60': entry.type === 'annotation' && selectedAnnotation?.id === entry.annotation.id
          }"
          @click="handleCommentEntryClick(entry)"
        >
          <template v-if="entry.type === 'annotation'">
            <div class="flex items-start justify-between mb-2">
              <div class="flex items-center gap-2">
                <Avatar :user="getDisplayUserForAnnotation(entry.annotation)" size="sm" :show-link="false" />
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Text annotation</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(entry.createdAt) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-1" v-if="!readonly && canEdit(entry.annotation)">
                <button @click.stop="editAnnotation(entry.annotation)" class="text-gray-400 hover:text-blue-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'edit']" class="text-xs" />
                </button>
                <button v-if="canDelete(entry.annotation)" @click.stop="deleteAnnotation(entry.annotation)" class="text-gray-400 hover:text-red-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'trash']" class="text-xs" />
                </button>
              </div>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300 break-words" v-html="linkifyText(entry.annotation.content)"></p>
          </template>
          <template v-else>
            <div class="flex items-start justify-between mb-2">
              <div class="flex items-center gap-2">
                <Avatar :user="getDisplayUserForComment(entry.comment)" size="sm" :show-link="false" />
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ getDisplayUserForComment(entry.comment)?.name || 'Anonymous' }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(entry.createdAt) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-1" v-if="canEditComment(entry.comment)">
                <button @click.stop="editComment(entry.comment)" class="text-gray-400 hover:text-blue-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'edit']" class="text-xs" />
                </button>
                <button @click.stop="deleteComment(entry.comment)" class="text-gray-400 hover:text-red-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'trash']" class="text-xs" />
                </button>
              </div>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300 break-words" v-html="linkifyText(entry.comment.content)"></p>
            <div v-if="entry.comment.annotation_id" class="mt-2 text-xs text-blue-600 dark:text-blue-400 flex items-center gap-1">
              <font-awesome-icon :icon="['fas', 'link']" />
              <span>Annotation #{{ getAnnotationNumber(entry.comment.annotation_id) }}</span>
            </div>
          </template>
        </div>
      </div>
      
      
    </div>
    
    <!-- Text Input Modal -->
    <div
      v-if="showTextModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click="closeTextModal"
      role="dialog"
      aria-labelledby="text-modal-title"
      aria-modal="true"
    >
      <div
        class="bg-white dark:bg-gray-800 p-6 rounded-lg max-w-md w-full mx-4"
        @click.stop
      >
        <h3 id="text-modal-title" class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add Text Annotation</h3>
        <textarea
          ref="textInput"
          v-model="textContent"
          placeholder="Enter your annotation text..."
          class="w-full h-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 resize-none"
          @keydown.enter.ctrl="saveTextAnnotation"
          @keydown.escape="closeTextModal"
          aria-label="Annotation text content"
        ></textarea>
        <div class="flex justify-end gap-2 mt-4">
          <button
            @click="closeTextModal"
            class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="saveTextAnnotation"
            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
          >
            Save
          </button>
        </div>
      </div>
    </div>
    
    <!-- Keyboard Shortcuts Help Modal -->
    <div
      v-if="showKeyboardHelp"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click="showKeyboardHelp = false"
      role="dialog"
      aria-labelledby="help-modal-title"
      aria-modal="true"
    >
      <div
        class="bg-white dark:bg-gray-800 p-6 rounded-lg max-w-lg w-full mx-4 max-h-96 overflow-y-auto"
        @click.stop
      >
        <div class="flex items-center justify-between mb-4">
          <h3 id="help-modal-title" class="text-lg font-medium text-gray-900 dark:text-gray-100">Keyboard Shortcuts</h3>
          <button
            @click="showKeyboardHelp = false"
            class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            aria-label="Close help"
          >
            <font-awesome-icon :icon="['fas','times']" />
          </button>
        </div>
        
        <div class="space-y-4">
          <!-- Tools -->
          <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Tools</h4>
            <div class="grid grid-cols-2 gap-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Grab</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">G</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Select</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">V</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Rectangle</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">R</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Circle</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">C</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Freehand</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">F</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Text</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">T</kbd>
              </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Actions</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Undo</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl+Z</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Redo</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl+Shift+Z</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Delete Selected</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Del</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Clear Selection</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Esc</kbd>
              </div>
            </div>
          </div>
          
          <!-- Navigation -->
          <div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Navigation</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Zoom In</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl++</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Zoom Out</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl+-</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Fit to Screen</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl+0</kbd>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">Pan</span>
                <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl+Drag</kbd>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
          <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
            Press <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">?</kbd> or click the keyboard icon to toggle this help
          </p>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirmation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          {{ annotationToDelete === 'all' ? 'Clear All Annotations' : (annotationToDelete === 'all-freehand' ? 'Clear All Freehand Annotations' : 'Delete Annotation') }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
          {{ annotationToDelete === 'all-freehand' 
            ? 'Are you sure you want to clear all freehand annotations? This action cannot be undone.' 
            : (annotationToDelete === 'all' 
              ? 'Are you sure you want to clear all annotations? This action cannot be undone.' 
              : 'Are you sure you want to delete this annotation? This action cannot be undone.') }}
        </p>
        <div class="flex justify-end space-x-3">
          <button
            @click="cancelDelete"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors"
          >
            Cancel
          </button>
          <button
            @click="confirmDelete"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors"
          >
            {{ annotationToDelete === 'all-freehand' ? 'Clear Freehand' : (annotationToDelete === 'all' ? 'Clear All' : 'Delete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Public User Info Modal -->
    <div
      v-if="showPublicUserModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click="showPublicUserModal = false"
      role="dialog"
      aria-labelledby="public-user-modal-title"
      aria-modal="true"
    >
      <div
        class="bg-white dark:bg-gray-800 p-6 rounded-lg max-w-md w-full mx-4"
        @click.stop
      >
        <h3 id="public-user-modal-title" class="text-lg font-medium text-gray-900 dark:text-white mb-4">Add Your Information</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Please provide your name and email to submit this comment.
        </p>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Name *
            </label>
            <input
              v-model="publicUserInfo.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Your full name"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Email *
            </label>
            <input
              v-model="publicUserInfo.email"
              type="email"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="your.email@example.com"
            />
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button
            @click="cancelPublicComment"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitPublicComment"
            :disabled="!publicUserInfo.name.trim() || !publicUserInfo.email.trim()"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors"
          >
            Submit Comment
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import Avatar from '@/Components/Avatar.vue'
import MentionAutocomplete from '@/Components/MentionAutocomplete.vue'

const props = defineProps({
  imageUrl: {
    type: String,
    required: true
  },
  imageName: {
    type: String,
    default: 'Annotation Image'
  },
  annotations: {
    type: Array,
    default: () => []
  },
  comments: {
    type: Array,
    default: () => []
  },
  canEdit: {
    type: Function,
    default: () => true
  },
  canDelete: {
    type: Function,
    default: () => true
  },
  canEditComment: {
    type: Function,
    default: () => true
  },
  readonly: {
    type: Boolean,
    default: false
  },
  isPublic: {
    type: Boolean,
    default: false
  },
  ticketId: {
    type: [String, Number],
    required: true
  }
})

const emit = defineEmits([
  'annotation-created',
  'annotation-updated',
  'annotation-deleted',
  'annotation-selected',
  'annotations-restored',
  'comment-added',
  'comment-updated',
  'comment-deleted',
  'clear-freehand'
])

// Refs
const imageRef = ref(null)
const canvasRef = ref(null)
const viewportRef = ref(null)

// Canvas state
const canvasWidth = ref(0)
const canvasHeight = ref(0)
const ctx = ref(null)

// Drawing state
const isDrawing = ref(false)
const startPoint = ref({ x: 0, y: 0 })
const currentPoint = ref({ x: 0, y: 0 })
const freehandPath = ref([])

// Zoom and Pan state
const zoomLevel = ref(1)
const panOffset = ref({ x: 0, y: 0 })
const isPanning = ref(false)
const lastPanPoint = ref({ x: 0, y: 0 })
const isAnimating = ref(false)

// Fit mode state for toggling width/height fit
const fitMode = ref('width') // 'width' | 'height'

// Tool state
const currentTool = ref('grab')

// Text editing state
const isEditingText = ref(false)
const textEditPosition = ref({ x: 0, y: 0 })
const textEditContent = ref('')
const textEditElement = ref(null)
const editingAnnotationId = ref(null)
const currentStyle = ref({
  color: '#3b82f6',
  strokeWidth: 2,
  fillColor: 'transparent'
})

// Tools configuration
const tools = ref([
  { name: 'grab', label: 'Grab', icon: ['fas', 'hand-paper'] },
  { name: 'select', label: 'Select', icon: ['fas', 'mouse-pointer'] },
  { name: 'rectangle', label: 'Rectangle', icon: ['far', 'square'] },
  { name: 'circle', label: 'Circle', icon: ['far', 'circle'] },
  { name: 'freehand', label: 'Freehand', icon: ['fas', 'pencil-alt'] },
  { name: 'text', label: 'Text', icon: ['fas', 'i-cursor'] }
])

// Color palette
const colorPalette = ref([
  '#3b82f6', // blue
  '#ef4444', // red
  '#10b981', // green
  '#f59e0b', // yellow
  '#8b5cf6', // purple
  '#f97316', // orange
  '#06b6d4', // cyan
  '#84cc16'  // lime
])

// Annotation state
const selectedAnnotation = ref(null)
const hoveredAnnotation = ref(null)
const isDraggingAnnotation = ref(false)
const draggedAnnotationSnapshot = ref(null)
const dragStartAnchor = ref({ x: 0, y: 0 })
const dragOffset = ref({ x: 0, y: 0 })
const lastDragDelta = ref({ x: 0, y: 0 })
const activeDragAnnotationId = ref(null)
const activeDragDelta = ref({ x: 0, y: 0 })
const pendingAnnotationUpdates = ref({})
const showTextModal = ref(false)
const textContent = ref('')
const pendingTextPosition = ref({ x: 0, y: 0 })

// Comment state
const newCommentContent = ref('')

// Derived annotations/comments for unified panel
const textAnnotations = computed(() => props.annotations.filter(annotation => annotation.type === 'text'))

const combinedComments = computed(() => {
  const annotationEntries = textAnnotations.value.map(annotation => ({
    type: 'annotation',
    id: annotation.id,
    annotation,
    createdAt: annotation.created_at || annotation.updated_at || annotation.createdAt || annotation.createdAt || annotation.updatedAt || null
  }))

  const commentEntries = (props.comments || []).map(comment => ({
    type: 'comment',
    id: comment.id,
    comment,
    annotation: props.annotations.find(a => a.id === comment.annotation_id) || null,
    createdAt: comment.created_at || comment.updated_at || comment.createdAt || comment.updatedAt || null
  }))

  const entries = [...annotationEntries, ...commentEntries]

  return entries.sort((a, b) => {
    const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0
    const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0
    return aTime - bTime
  })
})

const handleCommentEntryClick = (entry) => {
  if (entry.type === 'annotation' && entry.annotation) {
    selectAnnotation(entry.annotation)
  } else if (entry.type === 'comment' && entry.annotation) {
    selectAnnotation(entry.annotation)
  }
}

// Modal state
const showDeleteConfirmation = ref(false)
const annotationToDelete = ref(null)
const showPublicUserModal = ref(false)
const publicUserInfo = ref({ name: '', email: '' })
const pendingComment = ref(null)

// Undo/Redo state
const undoStack = ref([])
const redoStack = ref([])
const canUndo = computed(() => undoStack.value.length > 0)
const canRedo = computed(() => redoStack.value.length > 0)

// Keyboard shortcuts state
const isShiftPressed = ref(false)
const isCtrlPressed = ref(false)
const isAltPressed = ref(false)

// Watch for annotation changes to redraw canvas
watch(() => props.annotations, () => {
  if (imageLoaded.value) {
    redrawCanvas()
  }
}, { deep: true })

// Watch for comments changes
watch(() => props.comments, (newComments) => {
  console.log('[AnnotationCanvas] Comments updated:', newComments?.length || 0, newComments)
}, { deep: true, immediate: true })

const showKeyboardHelp = ref(false)

// Image loading
const imageLoaded = ref(false)

// Refs for accessibility
const textInput = ref(null)

// Methods
const onImageLoad = () => {
  imageLoaded.value = true
  // Ensure default zoom is 100%
  zoomLevel.value = 1
  // Reset pan so image starts at origin; user can pan/fit afterward
  panOffset.value = { x: 0, y: 0 }
  nextTick(() => {
    setupCanvas()
  })
}

const onImageError = () => {
  console.error('Failed to load annotation image')
}

// Helper function to escape HTML and linkify URLs in text
const linkifyText = (text) => {
  if (!text) return ''
  
  // First escape HTML to prevent XSS
  const escapeHtml = (str) => {
    const div = document.createElement('div')
    div.textContent = str
    return div.innerHTML
  }
  
  let escaped = escapeHtml(text)
  
  // Convert newlines to <br> tags
  escaped = escaped.replace(/\n/g, '<br>')
  
  // Convert URLs to clickable links
  const urlRegex = /(https?:\/\/[^\s<]+)/g
  escaped = escaped.replace(urlRegex, (url) => {
    // Remove trailing punctuation that might have been captured
    const cleanUrl = url.replace(/[.,;:!?]+$/, '')
    return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline break-all" onclick="event.stopPropagation()">${cleanUrl}</a>`
  })
  
  return escaped
}

const setupCanvas = () => {
  if (!imageRef.value || !canvasRef.value) return
  
  const img = imageRef.value
  const canvas = canvasRef.value
  
  canvasWidth.value = img.clientWidth
  canvasHeight.value = img.clientHeight
  
  canvas.width = img.clientWidth
  canvas.height = img.clientHeight
  
  ctx.value = canvas.getContext('2d')
  
  // Set canvas position to match image
  const imgRect = img.getBoundingClientRect()
  const container = img.parentElement.getBoundingClientRect()
  
  canvas.style.left = (imgRect.left - container.left) + 'px'
  canvas.style.top = (imgRect.top - container.top) + 'px'
  
  redrawCanvas()
}

const getCanvasCoordinates = (event) => {
  // Translate screen coordinates into image/canvas coordinates,
  // compensating for current pan (translate) and zoom (scale)
  if (!viewportRef.value) return { x: 0, y: 0 }
  const viewportRect = viewportRef.value.getBoundingClientRect()
  const localX = event.clientX - viewportRect.left
  const localY = event.clientY - viewportRect.top

  return {
    x: (localX - panOffset.value.x) / zoomLevel.value,
    y: (localY - panOffset.value.y) / zoomLevel.value
  }
}

// Viewport mouse handlers for panning
const onViewportMouseDown = (event) => {
  if (event.button === 1 || (event.button === 0 && (event.ctrlKey || event.metaKey)) || 
      (event.button === 0 && currentTool.value === 'select' && !props.readonly)) {
    // Middle mouse, Ctrl+click, or left click with select tool for panning
    isPanning.value = true
    lastPanPoint.value = { x: event.clientX, y: event.clientY }
    event.preventDefault()
  }
}

const onViewportMouseMove = (event) => {
  if (isPanning.value) {
    const deltaX = event.clientX - lastPanPoint.value.x
    const deltaY = event.clientY - lastPanPoint.value.y
    
    panOffset.value = {
      x: panOffset.value.x + deltaX,
      y: panOffset.value.y + deltaY
    }
    
    lastPanPoint.value = { x: event.clientX, y: event.clientY }
  }
}

const onViewportMouseUp = () => {
  isPanning.value = false
}

// Canvas mouse handlers for drawing
const onCanvasMouseDown = (event) => {
  if (props.readonly) return
  
  // Handle panning with grab tool
  if (currentTool.value === 'grab') {
    isPanning.value = true
    lastPanPoint.value = { x: event.clientX, y: event.clientY }
    event.preventDefault()
    return
  }
  
  // Handle selection with select tool
  if (currentTool.value === 'select') {
    const coords = getCanvasCoordinates(event)
    const clickedAnnotation = findAnnotationAtPoint(coords)
    if (clickedAnnotation) {
      selectAnnotation(clickedAnnotation)
    } else {
      selectedAnnotation.value = null
    }
    return
  }
  
  // Don't start drawing if we're panning
  if (isPanning.value) return
  
  const coords = getCanvasCoordinates(event)
  startPoint.value = coords
  currentPoint.value = coords
  
  if (!['grab', 'select', 'text'].includes(currentTool.value)) {
    isDrawing.value = true
    
    if (currentTool.value === 'freehand') {
      freehandPath.value = [coords]
    }
  }
}

const onCanvasMouseMove = (event) => {
  // Handle panning
  if (isPanning.value) {
    const deltaX = event.clientX - lastPanPoint.value.x
    const deltaY = event.clientY - lastPanPoint.value.y
    
    panOffset.value = {
      x: panOffset.value.x + deltaX,
      y: panOffset.value.y + deltaY
    }
    
    lastPanPoint.value = { x: event.clientX, y: event.clientY }
    return
  }
  
  if (!isDrawing.value || props.readonly) return
  
  const coords = getCanvasCoordinates(event)
  currentPoint.value = coords
  
  if (currentTool.value === 'freehand') {
    freehandPath.value.push(coords)
  }
  
  redrawCanvas()
}

const onCanvasMouseUp = (event) => {
  // Stop panning
  if (isPanning.value) {
    isPanning.value = false
    return
  }
  
  if (!isDrawing.value || props.readonly) return
  
  isDrawing.value = false
  
  const coords = getCanvasCoordinates(event)
  createAnnotation(coords)
}

const onCanvasClick = (event) => {
  if (props.readonly) return
  
  const coords = getCanvasCoordinates(event)
  
  if (currentTool.value === 'text') {
    startTextEdit(coords)
  }
}

const onRightClick = (event) => {
  // Handle right-click context menu if needed
  console.log('Right click at:', getCanvasCoordinates(event))
}

const createAnnotation = (endCoords) => {
  if (['grab', 'select', 'text'].includes(currentTool.value)) return
  
  let coordinates = {}
  
  switch (currentTool.value) {
    case 'select':
      // Select tool doesn't create annotations
      return
      
    case 'rectangle':
      const width = Math.abs(endCoords.x - startPoint.value.x)
      const height = Math.abs(endCoords.y - startPoint.value.y)
      
      // Don't create tiny rectangles
      if (width < 5 || height < 5) return
      
      coordinates = {
        x: Math.min(startPoint.value.x, endCoords.x),
        y: Math.min(startPoint.value.y, endCoords.y),
        width,
        height
      }
      break
      
    case 'circle':
      const centerX = (startPoint.value.x + endCoords.x) / 2
      const centerY = (startPoint.value.y + endCoords.y) / 2
      const radius = Math.sqrt(
        Math.pow(endCoords.x - startPoint.value.x, 2) + 
        Math.pow(endCoords.y - startPoint.value.y, 2)
      ) / 2
      
      // Don't create tiny circles
      if (radius < 5) return
      
      coordinates = { centerX, centerY, radius }
      break
      
    // Arrow tool removed
      
    case 'freehand':
      // Don't create if path is too short
      if (freehandPath.value.length < 3) return
      
      coordinates = { path: [...freehandPath.value] }
      break
      
    default:
      return
  }
  
  // Save to undo stack before creating
  saveToUndoStack()
  
  const annotation = {
    type: currentTool.value,
    coordinates,
    style: { ...currentStyle.value },
    content: null
  }
  
  emit('annotation-created', annotation)
}

const saveTextAnnotation = () => {
  if (!textContent.value.trim()) {
    closeTextModal()
    return
  }
  
  // Save to undo stack before creating
  saveToUndoStack()
  
  const annotation = {
    type: 'text',
    coordinates: { x: pendingTextPosition.value.x, y: pendingTextPosition.value.y },
    style: { ...currentStyle.value },
    content: textContent.value.trim()
  }
  
  emit('annotation-created', annotation)
  closeTextModal()
}

const closeTextModal = () => {
  showTextModal.value = false
  textContent.value = ''
  pendingTextPosition.value = { x: 0, y: 0 }
}

// Focus text input when modal opens
watch(showTextModal, (isOpen) => {
  if (isOpen) {
    nextTick(() => {
      textInput.value?.focus()
    })
  }
})

// Text editing methods
const startTextEdit = (coords) => {
  if (isEditingText.value) {
    finishTextEdit()
  }
  
  // Store canvas coordinates for the annotation
  textEditPosition.value = {
    x: coords.x,
    y: coords.y
  }
  textEditContent.value = ''
  isEditingText.value = true
  
  nextTick(() => {
    textEditElement.value?.focus()
  })
}

const finishTextEdit = () => {
  if (!isEditingText.value || !textEditContent.value.trim()) {
    cancelTextEdit()
    return
  }

  const canvasCoords = {
    x: textEditPosition.value.x,
    y: textEditPosition.value.y
  }

  if (editingAnnotationId.value) {
    // Update existing text annotation
    saveToUndoStack()
    const original = props.annotations.find(a => a.id === editingAnnotationId.value) || {}
    const updated = {
      ...original,
      id: editingAnnotationId.value,
      type: 'text',
      coordinates: canvasCoords,
      style: original.style || { ...currentStyle.value },
      content: textEditContent.value.trim()
    }
    emit('annotation-updated', updated)
  } else {
    // Create a new TEXT ANNOTATION (also mirrored as a comment by parent after creation)
    saveToUndoStack()
    emit('annotation-created', {
      type: 'text',
      coordinates: canvasCoords,
      style: { ...currentStyle.value },
      content: textEditContent.value.trim()
    })
  }

  cancelTextEdit()
}

const cancelTextEdit = () => {
  isEditingText.value = false
  textEditContent.value = ''
  textEditPosition.value = { x: 0, y: 0 }
  editingAnnotationId.value = null
}

// Annotation selection helper
const findAnnotationAtPoint = (coords) => {
  // Find annotation that contains the clicked point
  for (let i = props.annotations.length - 1; i >= 0; i--) {
    const annotation = props.annotations[i]
    if (isPointInAnnotation(coords, annotation)) {
      return annotation
    }
  }
  return null
}

const isPointInAnnotation = (point, annotation) => {
  const coords = annotation.coordinates
  const tolerance = 10 // pixels
  
  switch (annotation.type) {
    case 'text':
    case 'select':
      return Math.abs(point.x - coords.x) < tolerance && Math.abs(point.y - coords.y) < tolerance
      
    case 'rectangle':
      return point.x >= coords.x - tolerance && 
             point.x <= coords.x + coords.width + tolerance &&
             point.y >= coords.y - tolerance && 
             point.y <= coords.y + coords.height + tolerance
             
    case 'circle':
      const distance = Math.sqrt(
        Math.pow(point.x - coords.centerX, 2) + 
        Math.pow(point.y - coords.centerY, 2)
      )
      return distance <= coords.radius + tolerance
      
    case 'freehand':
      if (!coords.path || coords.path.length === 0) return false
      // Check if point is near any segment of the path
      for (let i = 0; i < coords.path.length - 1; i++) {
        const dist = distanceToLineSegment(point, coords.path[i], coords.path[i + 1])
        if (dist < tolerance) return true
      }
      return false
      
    default:
      return false
  }
}

const distanceToLineSegment = (point, start, end) => {
  const A = point.x - start.x
  const B = point.y - start.y
  const C = end.x - start.x
  const D = end.y - start.y
  
  const dot = A * C + B * D
  const lenSq = C * C + D * D
  let param = -1
  if (lenSq !== 0) param = dot / lenSq
  
  let xx, yy
  if (param < 0) {
    xx = start.x
    yy = start.y
  } else if (param > 1) {
    xx = end.x
    yy = end.y
  } else {
    xx = start.x + param * C
    yy = start.y + param * D
  }
  
  const dx = point.x - xx
  const dy = point.y - yy
  return Math.sqrt(dx * dx + dy * dy)
}

// Comment management methods
const addComment = () => {
  console.log('[AnnotationCanvas.addComment] Called, content:', newCommentContent.value)
  console.log('[AnnotationCanvas.addComment] Selected annotation:', selectedAnnotation.value?.id || 'none')
  
  if (!newCommentContent.value.trim()) {
    console.warn('[AnnotationCanvas.addComment] Empty content, aborting')
    return
  }
  
  const comment = {
    content: newCommentContent.value.trim(),
    annotation_id: selectedAnnotation.value?.id || null,
    created_at: new Date().toISOString()
  }
  
  console.log('[AnnotationCanvas.addComment] Comment object:', comment)
  
  // For public users, collect name and email first
  if (props.isPublic) {
    console.log('[AnnotationCanvas.addComment] Public user, showing modal')
    pendingComment.value = comment
    showPublicUserModal.value = true
  } else {
    console.log('[AnnotationCanvas.addComment] Emitting comment-added event')
    emit('comment-added', comment)
    newCommentContent.value = ''
    console.log('[AnnotationCanvas.addComment] Comment emitted, input cleared')
  }
}

const submitPublicComment = () => {
  if (!publicUserInfo.value.name.trim() || !publicUserInfo.value.email.trim()) {
    return
  }
  
  const commentWithUserInfo = {
    ...pendingComment.value,
    public_name: publicUserInfo.value.name.trim(),
    public_email: publicUserInfo.value.email.trim()
  }
  
  emit('comment-added', commentWithUserInfo)
  
  // Reset state
  newCommentContent.value = ''
  showPublicUserModal.value = false
  pendingComment.value = null
  publicUserInfo.value = { name: '', email: '' }
}

const cancelPublicComment = () => {
  showPublicUserModal.value = false
  pendingComment.value = null
  publicUserInfo.value = { name: '', email: '' }
}

const editComment = (comment) => {
  // Implement comment editing
  const newContent = prompt('Edit comment:', comment.content)
  if (newContent && newContent.trim() !== comment.content) {
    emit('comment-updated', { ...comment, content: newContent.trim() })
  }
}

const deleteComment = (comment) => {
  if (confirm('Are you sure you want to delete this comment?')) {
    emit('comment-deleted', comment)
  }
}

const formatDate = (dateString) => {
  if (!dateString) return 'Unknown'
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const getAnnotationNumber = (annotationId) => {
  const index = props.annotations.findIndex(a => a.id === annotationId)
  return index >= 0 ? index + 1 : '?'
}

const normalizeId = (id) => {
  if (id === null || id === undefined) return null
  return String(id)
}

const ensureNumber = (value, fallback = 0) => {
  const number = Number(value)
  return Number.isFinite(number) ? number : fallback
}

const getAnnotationAnchor = (annotation) => {
  if (!annotation) return { x: 0, y: 0 }
  const coords = annotation.coordinates || {}

  switch (annotation.type) {
    case 'rectangle':
      return {
        x: ensureNumber(coords.x),
        y: ensureNumber(coords.y)
      }
    case 'circle':
      return {
        x: ensureNumber(coords.centerX ?? coords.x),
        y: ensureNumber(coords.centerY ?? coords.y)
      }
    case 'freehand':
      if (Array.isArray(coords.path) && coords.path.length > 0) {
        const firstPoint = coords.path[0] || {}
        return {
          x: ensureNumber(firstPoint.x),
          y: ensureNumber(firstPoint.y)
        }
      }
      return { x: 0, y: 0 }
    case 'point':
    case 'text':
    default:
      return {
        x: ensureNumber(coords.x),
        y: ensureNumber(coords.y)
      }
  }
}

const parsePublicInfo = (info) => {
  if (!info) return null
  if (typeof info === 'string') {
    try {
      return JSON.parse(info)
    } catch (error) {
      console.warn('Failed to parse public user info JSON', error)
      return null
    }
  }
  return info
}

const buildUserLike = (name, email) => {
  const trimmedName = name?.toString().trim()
  const trimmedEmail = email?.toString().trim()

  if (!trimmedName && !trimmedEmail) {
    return {
      name: 'Anonymous',
      email: null
    }
  }

  return {
    name: trimmedName || trimmedEmail || 'Anonymous',
    email: trimmedEmail || null
  }
}

const extractPublicUser = (source) => {
  const info = parsePublicInfo(source)
  if (!info) return null
  const name = info.name || info.public_name
  const email = info.email || info.public_email
  if (!name && !email) return null
  return buildUserLike(name, email)
}

const getLatestCommentForAnnotation = (annotationId) => {
  if (!Array.isArray(props.comments) || !annotationId) return null
  const list = props.comments.filter(c => c.annotation_id === annotationId)
  if (list.length === 0) return null
  // Sort by created_at ascending then take last; fallback if missing created_at
  list.sort((a, b) => {
    const ta = new Date(a.created_at || 0).getTime()
    const tb = new Date(b.created_at || 0).getTime()
    return ta - tb
  })
  return list[list.length - 1]
}

// Prefer latest comment content; fallback to annotation.content for text annotations
const getOverlayContent = (annotation) => {
  const latest = getLatestCommentForAnnotation(annotation.id)
  if (latest?.content) return latest.content
  if (annotation?.type === 'text' && annotation?.content) return annotation.content
  return null
}

const getDisplayUserForComment = (comment) => {
  if (!comment) return buildUserLike()

  if (comment.user) {
    return comment.user
  }

  const fromPublicInfo = extractPublicUser(comment.public_user_info)
  if (fromPublicInfo) return fromPublicInfo

  if (comment.public_name || comment.public_email) {
    return buildUserLike(comment.public_name, comment.public_email)
  }

  if (comment.name || comment.email) {
    return buildUserLike(comment.name, comment.email)
  }

  return buildUserLike()
}

const getDisplayUserForAnnotation = (annotation) => {
  if (!annotation) return buildUserLike()

  if (annotation.user) {
    return annotation.user
  }

  const fromPublicInfo = extractPublicUser(annotation.public_user_info)
  if (fromPublicInfo) return fromPublicInfo

  const latestComment = getLatestCommentForAnnotation(annotation.id)
  if (latestComment) {
    return getDisplayUserForComment(latestComment)
  }

  if (annotation.public_name || annotation.public_email) {
    return buildUserLike(annotation.public_name, annotation.public_email)
  }

  if (annotation.created_by_public) {
    return buildUserLike('Guest Reviewer', null)
  }

  return buildUserLike()
}

const selectAnnotation = (annotation) => {
  selectedAnnotation.value = annotation
  emit('annotation-selected', annotation)
}

// Expose only the methods needed by parent components
defineExpose({
  selectAnnotation
})

const editAnnotation = (annotation) => {
  // Only text annotations are currently editable inline
  if (annotation?.type === 'text') {
    isEditingText.value = true
    textEditPosition.value = { x: annotation.coordinates.x, y: annotation.coordinates.y }
    textEditContent.value = annotation.content || ''
    editingAnnotationId.value = annotation.id
    nextTick(() => {
      textEditElement.value?.focus()
    })
  } else {
    // Could add a modal for unsupported types, but keeping simple for now
    console.log('Editing for this annotation type is not supported yet.')
  }
}

const deleteAnnotation = (annotation) => {
  // Use proper modal instead of alert
  showDeleteConfirmation.value = true
  annotationToDelete.value = annotation
}

const confirmDelete = () => {
  if (annotationToDelete.value === 'all-freehand') {
    emit('clear-freehand')
  } else if (annotationToDelete.value === 'all') {
    // optional: could emit a general clear-all later
    // no-op by default
  } else if (annotationToDelete.value) {
    saveToUndoStack()
    emit('annotation-deleted', annotationToDelete.value)
  }
  showDeleteConfirmation.value = false
  annotationToDelete.value = null
}

const cancelDelete = () => {
  showDeleteConfirmation.value = false
  annotationToDelete.value = null
}

const clearAnnotations = () => {
  // Keep existing clear-all behavior
  showDeleteConfirmation.value = true
  annotationToDelete.value = 'all'
}

const clearFreehandAnnotations = () => {
  if (props.readonly) return
  showDeleteConfirmation.value = true
  annotationToDelete.value = 'all-freehand'
}

// Zoom and Pan methods
const zoomIn = () => {
  const newZoom = Math.min(zoomLevel.value * 1.2, 5)
  setZoom(newZoom)
}

const zoomOut = () => {
  const newZoom = Math.max(zoomLevel.value / 1.2, 0.1)
  setZoom(newZoom)
}

const setZoom = (newZoom) => {
  isAnimating.value = true
  zoomLevel.value = newZoom
  
  setTimeout(() => {
    isAnimating.value = false
  }, 300)
}

// Apply fit according to current fitMode (width or height)
const applyFit = (mode = fitMode.value) => {
  if (!imageRef.value || !viewportRef.value) return
  
  isAnimating.value = true
  const viewport = viewportRef.value
  const image = imageRef.value

  const viewportWidth = viewport.clientWidth - 40 // padding
  const viewportHeight = viewport.clientHeight - 40

  const imageWidth = image.naturalWidth
  const imageHeight = image.naturalHeight

  let scale
  if (mode === 'width') {
    scale = Math.min(viewportWidth / imageWidth, 1)
  } else {
    scale = Math.min(viewportHeight / imageHeight, 1)
  }

  const scaledWidth = imageWidth * scale
  const scaledHeight = imageHeight * scale

  panOffset.value = {
    x: (viewportWidth - scaledWidth) / 2 + 20,
    y: (viewportHeight - scaledHeight) / 2 + 20
  }

  zoomLevel.value = scale
  
  setTimeout(() => {
    isAnimating.value = false
  }, 300)
}

// Toggle between fitting to width and to height
const toggleFit = () => {
  fitMode.value = fitMode.value === 'width' ? 'height' : 'width'
  applyFit(fitMode.value)
}

const fitToScreen = () => {
  if (!imageRef.value || !viewportRef.value) return
  
  const viewport = viewportRef.value
  const image = imageRef.value
  
  const viewportWidth = viewport.clientWidth - 40 // padding
  const viewportHeight = viewport.clientHeight - 40
  
  const imageWidth = image.naturalWidth
  const imageHeight = image.naturalHeight
  
  const scaleX = viewportWidth / imageWidth
  const scaleY = viewportHeight / imageHeight
  const scale = Math.min(scaleX, scaleY, 1) // Don't zoom in beyond 100%
  
  // Center the image
  const scaledWidth = imageWidth * scale
  const scaledHeight = imageHeight * scale
  
  panOffset.value = {
    x: (viewportWidth - scaledWidth) / 2 + 20,
    y: (viewportHeight - scaledHeight) / 2 + 20
  }
  
  setZoom(scale)
}

// Optimized wheel handling with throttling
let wheelTimeout = null
const onWheel = (event) => {
  // Throttle wheel events for better performance
  if (wheelTimeout) return

  wheelTimeout = setTimeout(() => {
    wheelTimeout = null
  }, 16) // ~60fps

  // If Ctrl/Cmd is held, perform zoom. Otherwise, scroll (pan) vertically
  if (event.ctrlKey || event.metaKey) {
    const deltaZoom = event.deltaY > 0 ? 0.9 : 1.1
    const newZoom = Math.min(Math.max(zoomLevel.value * deltaZoom, 0.1), 5)

    // Zoom towards mouse position
    const rect = viewportRef.value.getBoundingClientRect()
    const mouseX = event.clientX - rect.left
    const mouseY = event.clientY - rect.top

    // Calculate the point under the mouse before zoom
    const beforeZoomX = (mouseX - panOffset.value.x) / zoomLevel.value
    const beforeZoomY = (mouseY - panOffset.value.y) / zoomLevel.value

    // Calculate the point under the mouse after zoom
    const afterZoomX = beforeZoomX * newZoom
    const afterZoomY = beforeZoomY * newZoom

    // Adjust pan to keep the same point under the mouse
    panOffset.value = {
      x: mouseX - afterZoomX,
      y: mouseY - afterZoomY
    }

    zoomLevel.value = newZoom
  } else {
    // Scroll: move the canvas up/down by adjusting pan offset
    // Negative deltaY should move content up (scroll down), so subtract
    panOffset.value = {
      x: panOffset.value.x,
      y: panOffset.value.y - event.deltaY
    }
  }
}

// Undo/Redo methods with debouncing for performance
let saveToUndoTimeout = null
const saveToUndoStack = () => {
  // Debounce rapid changes to avoid excessive undo states
  if (saveToUndoTimeout) {
    clearTimeout(saveToUndoTimeout)
  }
  
  saveToUndoTimeout = setTimeout(() => {
    // Save current state to undo stack
    const currentState = JSON.stringify(props.annotations)
    
    // Don't save if state hasn't changed
    if (undoStack.value.length > 0 && undoStack.value[undoStack.value.length - 1] === currentState) {
      return
    }
    
    undoStack.value.push(currentState)
    redoStack.value = [] // Clear redo stack when new action is performed
    
    // Limit undo stack size for memory efficiency
    if (undoStack.value.length > 20) {
      undoStack.value.shift()
    }
  }, 100) // 100ms debounce
}

const undo = () => {
  if (!canUndo.value) return
  
  // Save current state to redo stack
  redoStack.value.push(JSON.stringify(props.annotations))
  
  // Restore previous state
  const previousState = undoStack.value.pop()
  try {
    const restoredAnnotations = JSON.parse(previousState)
    emit('annotations-restored', restoredAnnotations)
  } catch (error) {
    console.error('Failed to undo:', error)
  }
}

const redo = () => {
  if (!canRedo.value) return
  
  // Save current state to undo stack
  undoStack.value.push(JSON.stringify(props.annotations))
  
  // Restore next state
  const nextState = redoStack.value.pop()
  try {
    const restoredAnnotations = JSON.parse(nextState)
    emit('annotations-restored', restoredAnnotations)
  } catch (error) {
    console.error('Failed to redo:', error)
  }
}

const getAnnotationStyle = (annotation) => {
  const effective = getEffectiveAnnotation(annotation)
  const coords = effective.coordinates || {}
  const anchor = getAnnotationAnchor(effective)

  switch (effective.type) {
    case 'point':
      return {
        left: anchor.x + 'px',
        top: anchor.y + 'px',
        transform: 'translate(-50%, -50%)'
      }
    case 'rectangle':
      return {
        left: anchor.x + 'px',
        top: anchor.y + 'px',
        width: ensureNumber(coords.width) + 'px',
        height: ensureNumber(coords.height) + 'px'
      }
    default:
      return {
        left: anchor.x + 'px',
        top: anchor.y + 'px'
      }
  }
}

const cloneAnnotationCoordinates = (annotation) => {
  if (!annotation || !annotation.coordinates) return {}
  try {
    return JSON.parse(JSON.stringify(annotation.coordinates))
  } catch (error) {
    console.warn('Failed to clone annotation coordinates', error)
    return { ...annotation.coordinates }
  }
}

const applyDeltaToCoordinates = (type, originalCoords, delta) => {
  const safe = (value) => ensureNumber(value)
  const coords = { ...originalCoords }

  switch (type) {
    case 'rectangle':
      return {
        ...coords,
        x: safe(coords.x) + delta.x,
        y: safe(coords.y) + delta.y
      }
    case 'circle': {
      const updated = {
        ...coords,
        centerX: safe(coords.centerX ?? coords.x) + delta.x,
        centerY: safe(coords.centerY ?? coords.y) + delta.y
      }
      if ('x' in coords) {
        updated.x = safe(coords.x) + delta.x
      }
      if ('y' in coords) {
        updated.y = safe(coords.y) + delta.y
      }
      return updated
    }
    case 'freehand':
      return {
        ...coords,
        path: Array.isArray(coords.path)
          ? coords.path.map(point => ({
              x: safe(point.x) + delta.x,
              y: safe(point.y) + delta.y
            }))
          : []
      }
    default:
      return {
        ...coords,
        x: safe(coords.x) + delta.x,
        y: safe(coords.y) + delta.y
      }
  }
}

const startAnnotationDrag = (annotation, event) => {
  if (!annotation) return

  saveToUndoStack()

  const pointer = getCanvasCoordinates(event)
  const anchor = getAnnotationAnchor(annotation)

  draggedAnnotationSnapshot.value = {
    ...annotation,
    coordinates: cloneAnnotationCoordinates(annotation)
  }
  dragStartAnchor.value = anchor
  dragOffset.value = {
    x: pointer.x - anchor.x,
    y: pointer.y - anchor.y
  }
  lastDragDelta.value = { x: 0, y: 0 }
  isDraggingAnnotation.value = true
  activeDragAnnotationId.value = annotation.id
  activeDragDelta.value = { x: 0, y: 0 }

  selectAnnotation(annotation)

  window.addEventListener('mousemove', handleAnnotationDrag)
  window.addEventListener('mouseup', endAnnotationDrag)
}

const handleAnnotationDrag = (event) => {
  if (!isDraggingAnnotation.value || props.readonly || !draggedAnnotationSnapshot.value) {
    return
  }

  event.preventDefault()

  const pointer = getCanvasCoordinates(event)
  const newAnchor = {
    x: pointer.x - dragOffset.value.x,
    y: pointer.y - dragOffset.value.y
  }
  const delta = {
    x: newAnchor.x - dragStartAnchor.value.x,
    y: newAnchor.y - dragStartAnchor.value.y
  }

  if (
    Math.abs(delta.x - lastDragDelta.value.x) < 0.01 &&
    Math.abs(delta.y - lastDragDelta.value.y) < 0.01
  ) {
    return
  }

  lastDragDelta.value = { ...delta }
  activeDragDelta.value = { ...delta }

  const snapshot = draggedAnnotationSnapshot.value
  const updatedAnnotation = {
    ...snapshot,
    coordinates: applyDeltaToCoordinates(snapshot.type, snapshot.coordinates, activeDragDelta.value)
  }

  selectedAnnotation.value = updatedAnnotation
  redrawCanvas()
}

const cleanupAnnotationDrag = () => {
  window.removeEventListener('mousemove', handleAnnotationDrag)
  window.removeEventListener('mouseup', endAnnotationDrag)

  isDraggingAnnotation.value = false
  draggedAnnotationSnapshot.value = null
  dragStartAnchor.value = { x: 0, y: 0 }
  dragOffset.value = { x: 0, y: 0 }
  lastDragDelta.value = { x: 0, y: 0 }
  activeDragAnnotationId.value = null
  activeDragDelta.value = { x: 0, y: 0 }
}

const endAnnotationDrag = (event) => {
  if (!isDraggingAnnotation.value) return

  if (event) {
    event.preventDefault()
    handleAnnotationDrag(event)
  }

  const snapshot = draggedAnnotationSnapshot.value
  if (snapshot) {
    const idKey = normalizeId(snapshot.id)
    const finalAnnotation = {
      ...snapshot,
      coordinates: applyDeltaToCoordinates(snapshot.type, snapshot.coordinates, activeDragDelta.value)
    }
    if (idKey) {
      pendingAnnotationUpdates.value = {
        ...pendingAnnotationUpdates.value,
        [idKey]: finalAnnotation
      }
    }
    emit('annotation-updated', finalAnnotation)
    selectedAnnotation.value = finalAnnotation
  }

  cleanupAnnotationDrag()
}

const onAnnotationMarkerMouseDown = (annotation, event) => {
  if (props.readonly) return
  if (event.button !== 0) return
  if (event.target.closest('.annotation-actions')) return
  if (typeof props.canEdit === 'function' && !props.canEdit(annotation)) return

  event.preventDefault()
  event.stopPropagation()

  startAnnotationDrag(annotation, event)
}

const getEffectiveAnnotation = (annotation) => {
  if (
    isDraggingAnnotation.value &&
    activeDragAnnotationId.value &&
    draggedAnnotationSnapshot.value &&
    annotation.id === activeDragAnnotationId.value
  ) {
    return {
      ...draggedAnnotationSnapshot.value,
      coordinates: applyDeltaToCoordinates(
        draggedAnnotationSnapshot.value.type,
        draggedAnnotationSnapshot.value.coordinates,
        activeDragDelta.value
      )
    }
  }
  const idKey = normalizeId(annotation?.id)
  if (idKey && pendingAnnotationUpdates.value[idKey]) {
    return pendingAnnotationUpdates.value[idKey]
  }
  return annotation
}

const syncPendingAnnotations = (annotations) => {
  const pending = pendingAnnotationUpdates.value
  const keys = Object.keys(pending)
  if (!keys.length) return

  const nextPending = { ...pending }
  let changed = false

  keys.forEach(key => {
    const match = annotations?.find?.(item => normalizeId(item?.id) === key)
    if (!match) {
      delete nextPending[key]
      changed = true
      return
    }

    const pendingAnnotation = pending[key]
    const pendingCoords = JSON.stringify(pendingAnnotation?.coordinates ?? null)
    const matchCoords = JSON.stringify(match?.coordinates ?? null)
    if (pendingCoords === matchCoords) {
      delete nextPending[key]
      changed = true
    }
  })

  if (changed) {
    pendingAnnotationUpdates.value = nextPending
  }
}

// Optimized canvas redrawing with requestAnimationFrame
let redrawRequested = false
const redrawCanvas = () => {
  if (!ctx.value || !imageLoaded.value || redrawRequested) return
  
  redrawRequested = true
  requestAnimationFrame(() => {
    if (!ctx.value) {
      redrawRequested = false
      return
    }
    
    // Clear canvas
    ctx.value.clearRect(0, 0, canvasWidth.value, canvasHeight.value)
    
    // Draw existing annotations
    props.annotations.forEach(annotation => {
      drawAnnotation(annotation)
    })
    
    // Draw current drawing if in progress
    if (isDrawing.value) {
      drawCurrentAnnotation()
    }
    
    redrawRequested = false
  })
}

const redrawAnnotations = () => {
  if (!ctx.value) return
  
  // Clear then draw all to ensure a fresh render
  ctx.value.clearRect(0, 0, canvasWidth.value, canvasHeight.value)
  props.annotations.forEach(annotation => {
    drawAnnotation(annotation)
  })
}

const drawAnnotation = (annotation) => {
  if (!ctx.value) return

  const effective = getEffectiveAnnotation(annotation)
  const coords = effective.coordinates
  const style = effective.style || annotation.style || currentStyle.value

  ctx.value.strokeStyle = style.color
  ctx.value.lineWidth = style.strokeWidth
  ctx.value.fillStyle = style.fillColor || 'transparent'

  switch (effective.type) {
    case 'point':
      ctx.value.beginPath()
      ctx.value.arc(coords.x, coords.y, 5, 0, 2 * Math.PI)
      ctx.value.fill()
      ctx.value.stroke()
      break
      
    case 'rectangle':
      ctx.value.beginPath()
      ctx.value.rect(coords.x, coords.y, coords.width, coords.height)
      ctx.value.stroke()
      break
      
    case 'circle':
      ctx.value.beginPath()
      ctx.value.arc(coords.centerX, coords.centerY, coords.radius, 0, 2 * Math.PI)
      ctx.value.stroke()
      break
      
    // Arrow tool removed
      
    case 'freehand':
      if (coords.path && coords.path.length > 1) {
        ctx.value.beginPath()
        ctx.value.moveTo(coords.path[0].x, coords.path[0].y)
        coords.path.forEach(point => {
          ctx.value.lineTo(point.x, point.y)
        })
        ctx.value.stroke()
      }
      break
  }
}

const drawPreview = () => {
  if (!ctx.value) return
  
  ctx.value.strokeStyle = currentStyle.value.color
  ctx.value.lineWidth = currentStyle.value.strokeWidth
  ctx.value.setLineDash([5, 5]) // Dashed line for preview
  
  switch (currentTool.value) {
    case 'rectangle':
      ctx.value.beginPath()
      ctx.value.rect(
        Math.min(startPoint.value.x, currentPoint.value.x),
        Math.min(startPoint.value.y, currentPoint.value.y),
        Math.abs(currentPoint.value.x - startPoint.value.x),
        Math.abs(currentPoint.value.y - startPoint.value.y)
      )
      ctx.value.stroke()
      break
      
    case 'circle':
      const centerX = (startPoint.value.x + currentPoint.value.x) / 2
      const centerY = (startPoint.value.y + currentPoint.value.y) / 2
      const radius = Math.sqrt(
        Math.pow(currentPoint.value.x - startPoint.value.x, 2) + 
        Math.pow(currentPoint.value.y - startPoint.value.y, 2)
      ) / 2
      ctx.value.beginPath()
      ctx.value.arc(centerX, centerY, radius, 0, 2 * Math.PI)
      ctx.value.stroke()
      break
      
    // Arrow tool removed
      
    case 'freehand':
      if (freehandPath.value.length > 1) {
        ctx.value.beginPath()
        ctx.value.moveTo(freehandPath.value[0].x, freehandPath.value[0].y)
        freehandPath.value.forEach(point => {
          ctx.value.lineTo(point.x, point.y)
        })
        ctx.value.stroke()
      }
      break
  }
  
  ctx.value.setLineDash([]) // Reset line dash
}

// Alias used by redrawCanvas to render in-progress shape
const drawCurrentAnnotation = () => {
  drawPreview()
}

const drawArrow = (startX, startY, endX, endY) => {
  if (!ctx.value) return
  
  const headLength = 15
  const angle = Math.atan2(endY - startY, endX - startX)
  
  // Draw line
  ctx.value.beginPath()
  ctx.value.moveTo(startX, startY)
  ctx.value.lineTo(endX, endY)
  ctx.value.stroke()
  
  // Draw arrowhead
  ctx.value.beginPath()
  ctx.value.moveTo(endX, endY)
  ctx.value.lineTo(
    endX - headLength * Math.cos(angle - Math.PI / 6),
    endY - headLength * Math.sin(angle - Math.PI / 6)
  )
  ctx.value.moveTo(endX, endY)
  ctx.value.lineTo(
    endX - headLength * Math.cos(angle + Math.PI / 6),
    endY - headLength * Math.sin(angle + Math.PI / 6)
  )
  ctx.value.stroke()
}

// Watch for image changes
watch(() => props.imageUrl, () => {
  imageLoaded.value = false
  nextTick(() => {
    if (imageRef.value) {
      setupCanvas()
    }
  })
})

// Watch for annotation changes
watch(() => props.annotations, () => {
  syncPendingAnnotations(props.annotations)
  nextTick(() => {
    redrawCanvas()
  })
}, { deep: true })

// Handle window resize
const handleResize = () => {
  if (imageLoaded.value) {
    setupCanvas()
  }
}

// Keyboard event handlers
const isTextEntryEvent = (event) => {
  const el = event?.target
  if (!el) return false
  const tag = (el.tagName || '').toUpperCase()
  return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable === true || el.getAttribute?.('role') === 'textbox'
}

const handleKeyDown = (event) => {
  // Do not trigger shortcuts while typing in inputs/textareas/contenteditable or during inline text edit
  if (isTextEntryEvent(event) || isEditingText.value) {
    return
  }
  // Update modifier key states
  isShiftPressed.value = event.shiftKey
  isCtrlPressed.value = event.ctrlKey || event.metaKey
  isAltPressed.value = event.altKey
  
  // Prevent default for our shortcuts
  const key = event.key.toLowerCase()
  
  // Tool shortcuts
  if (!event.ctrlKey && !event.metaKey && !event.altKey) {
    switch (key) {
      case 'g':
        if (!props.readonly) {
          currentTool.value = 'grab'
          event.preventDefault()
        }
        break
      case 'v':
        if (!props.readonly) {
          currentTool.value = 'select'
          event.preventDefault()
        }
        break
      case 'r':
        if (!props.readonly) {
          currentTool.value = 'rectangle'
          event.preventDefault()
        }
        break
      case 'c':
        if (!props.readonly) {
          currentTool.value = 'circle'
          event.preventDefault()
        }
        break
      // Arrow tool removed
      case 'f':
        if (!props.readonly) {
          currentTool.value = 'freehand'
          event.preventDefault()
        }
        break
      case 't':
        if (!props.readonly) {
          currentTool.value = 'text'
          event.preventDefault()
        }
        break
      case 'escape':
        // Close modals first, then clear selection
        if (showKeyboardHelp.value) {
          showKeyboardHelp.value = false
        } else if (showTextModal.value) {
          closeTextModal()
        } else {
          selectedAnnotation.value = null
        }
        event.preventDefault()
        break
      case '?':
        // Toggle keyboard help
        showKeyboardHelp.value = !showKeyboardHelp.value
        event.preventDefault()
        break
      case 'delete':
      case 'backspace':
        // Delete selected annotation
        if (selectedAnnotation.value && !props.readonly) {
          deleteAnnotation(selectedAnnotation.value)
          event.preventDefault()
        }
        break
    }
  }
  
  // Ctrl/Cmd shortcuts
  if (event.ctrlKey || event.metaKey) {
    switch (key) {
      case 'z':
        if (event.shiftKey) {
          // Redo
          redo()
        } else {
          // Undo
          undo()
        }
        event.preventDefault()
        break
      case 'y':
        // Redo (alternative)
        redo()
        event.preventDefault()
        break
      case '=':
      case '+':
        // Zoom in
        zoomIn()
        event.preventDefault()
        break
      case '-':
        // Zoom out
        zoomOut()
        event.preventDefault()
        break
      case '0':
        // Fit to screen
        fitToScreen()
        event.preventDefault()
        break
    }
  }
}

const handleKeyUp = (event) => {
  // Update modifier key states
  isShiftPressed.value = event.shiftKey
  isCtrlPressed.value = event.ctrlKey || event.metaKey
  isAltPressed.value = event.altKey
}

// Initialize fit to screen when image loads
watch(imageLoaded, (loaded) => {
  if (loaded) {
    nextTick(() => {
      fitToScreen()
      // Ensure any existing annotations render right after image becomes ready
      redrawCanvas()
    })
  }
})

onMounted(() => {
  window.addEventListener('resize', handleResize)
  window.addEventListener('keydown', handleKeyDown)
  window.addEventListener('keyup', handleKeyUp)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('keyup', handleKeyUp)
  cleanupAnnotationDrag()
})
</script>

<style scoped>
.annotation-canvas-container {
  user-select: none;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.annotation-toolbar {
  backdrop-filter: blur(8px);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.annotation-viewport {
  background: 
    radial-gradient(circle at 20px 20px, #e5e7eb 1px, transparent 1px),
    radial-gradient(circle at 20px 20px, #e5e7eb 1px, transparent 1px);
  background-size: 20px 20px;
  background-position: 0 0, 10px 10px;
}

.dark .annotation-viewport {
  background: 
    radial-gradient(circle at 20px 20px, #374151 1px, transparent 1px),
    radial-gradient(circle at 20px 20px, #374151 1px, transparent 1px);
  background-size: 20px 20px;
  background-position: 0 0, 10px 10px;
}

.annotation-content-wrapper {
  will-change: transform;
}

/* Numbered Annotation Markers */
.annotation-marker {
  pointer-events: auto;
  z-index: 10;
  transform: translate(-50%, -50%);
  transition: all 0.2s ease;
}

/* On-canvas comment box anchored near the marker */
.annotation-comment-box {
  position: absolute;
  left: 18px; /* offset to the right of the marker */
  top: -8px;  /* slight upward offset */
  background: white;
  color: #111827;
  border: 1px solid rgba(0,0,0,0.1);
  border-radius: 6px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
  padding: 6px 8px;
  max-width: 360px;
  width: fit-content;
  min-width: 120px;
}

.dark .annotation-comment-box {
  background: #111827;
  color: #e5e7eb;
  border-color: rgba(255,255,255,0.1);
}

.annotation-number {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: white;
  background: #3b82f6;
  border: 2px solid white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.2s ease;
}

.annotation-marker.pending .annotation-number {
  background: #f59e0b;
}

.annotation-marker.approved .annotation-number {
  background: #10b981;
}

.annotation-marker.rejected .annotation-number {
  background: #ef4444;
}

.annotation-marker.selected .annotation-number {
  transform: scale(1.2);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3), 0 4px 12px rgba(0, 0, 0, 0.2);
}

.annotation-marker.hovered .annotation-number {
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Tooltip */
.annotation-tooltip {
  position: absolute;
  top: -40px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 12px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease;
  z-index: 20;
}

.annotation-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 4px solid transparent;
  border-top-color: rgba(0, 0, 0, 0.8);
}

.annotation-marker.hovered .annotation-tooltip {
  opacity: 1;
}

/* Action Buttons */
.annotation-actions {
  position: absolute;
  top: -50px;
  right: -20px;
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.2s ease;
  z-index: 15;
}

.annotation-marker.selected .annotation-actions,
.annotation-marker.hovered .annotation-actions {
  opacity: 1;
}

.action-btn {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.edit-btn {
  background: #3b82f6;
  color: white;
}

.edit-btn:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.delete-btn {
  background: #ef4444;
  color: white;
}

.delete-btn:hover {
  background: #dc2626;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Toolbar Buttons */
button:disabled {
  cursor: not-allowed;
}

/* Smooth transitions for zoom/pan */
.annotation-content-wrapper {
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Performance optimizations */
.annotation-canvas-container {
  contain: layout style paint;
}

.annotation-content-wrapper {
  will-change: transform;
  backface-visibility: hidden;
  transform-style: preserve-3d;
}

/* Micro-interactions and polish */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.annotation-toolbar {
  animation: slideIn 0.3s ease-out;
}

.annotation-marker {
  animation: fadeIn 0.2s ease-out;
}

.annotation-marker.selected .annotation-number {
  animation: pulse 2s infinite;
}

/* Loading states */
.loading-pulse {
  animation: pulse 1.5s infinite;
}

/* Improved focus indicators */
button:focus-visible,
input:focus-visible,
textarea:focus-visible,
select:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

/* Keyboard shortcut styling */
kbd {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Consolas, 'Liberation Mono', Menlo, monospace;
  font-size: 0.75rem;
  font-weight: 600;
  border: 1px solid currentColor;
  border-bottom-width: 2px;
  border-radius: 0.25rem;
  box-shadow: inset 0 -1px 0 currentColor;
}

/* Tooltip animations */
.annotation-tooltip {
  animation: fadeIn 0.2s ease-out;
}

/* Button hover effects */
button:not(:disabled):hover {
  transform: translateY(-1px);
}

button:not(:disabled):active {
  transform: translateY(0);
}

/* Modal animations */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.95) translateY(-10px);
}

/* Improved scrollbar styling */
.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.7);
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(75, 85, 99, 0.5);
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(75, 85, 99, 0.7);
}

/* Custom scrollbar for better UX */
.annotation-viewport::-webkit-scrollbar {
  display: none;
}

.annotation-viewport {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Focus styles for accessibility */
button:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

/* Loading state */
.annotation-canvas-container.loading {
  opacity: 0.7;
  pointer-events: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .annotation-toolbar {
    flex-direction: column;
    gap: 8px;
    align-items: stretch;
  }
  
  .annotation-toolbar > div {
    justify-content: center;
  }
  
  .annotation-viewport {
    height: 400px;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .annotation-number {
    border-width: 3px;
  }
  
  .annotation-marker.selected .annotation-number {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.5);
  }
}

/* Custom cursors */
.cursor-select {
  cursor: default;
}

.cursor-select:hover {
  cursor: pointer;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .annotation-marker,
  .annotation-number,
  .annotation-tooltip,
  .annotation-actions,
  .action-btn {
    transition: none;
  }
  
  .annotation-content-wrapper {
    transition: none !important;
  }
}
</style>
