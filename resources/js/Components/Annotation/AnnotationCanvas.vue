<template>
  <div class="annotation-canvas-container flex relative bg-gray-50 dark:bg-gray-900 rounded-lg overflow-auto" style="height: 800px; min-height: 600px;">
    <!-- Main Canvas Area -->
    <div class="flex-1 relative flex flex-col" style="min-width: 0;">
    <!-- Toolbar -->
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex-shrink-0 shadow-sm">
      <div class="annotation-toolbar flex items-center justify-center px-4 py-2.5">
        <!-- Left Section: Tools & Properties -->
        <div class="flex items-center gap-3">
          <!-- Drawing Tools Group -->
          <div class="flex items-center gap-0.5 px-2 py-1 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
            <button
              v-for="tool in tools"
              :key="tool.name"
              @click="currentTool = tool.name"
              :class="[
                'p-2.5 rounded-md transition-all duration-200 relative group',
                currentTool === tool.name
                  ? 'bg-blue-500 text-white shadow-sm'
                  : 'text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100'
              ]"
              :title="`${tool.label} (${tool.shortcut})`"
              :disabled="readonly"
            >
              <font-awesome-icon :icon="tool.icon" class="text-base" />
              <!-- Active indicator -->
              <div v-if="currentTool === tool.name" class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-blue-500 rounded-full"></div>
            </button>
          </div>
          
          <!-- Divider -->
          <div class="h-8 w-px bg-gray-300 dark:bg-gray-600"></div>
          
          <!-- Color & Stroke Properties -->
          <div class="flex items-center gap-3">
            <!-- Color Picker -->
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Color</span>
              <div class="flex items-center gap-1 px-1.5 py-1 bg-gray-50 dark:bg-gray-900/50 rounded-md">
                <button
                  v-for="color in colorPalette"
                  :key="color"
                  @click="currentStyle.color = color"
                  :class="[
                    'w-7 h-7 rounded-md border-2 transition-all duration-200 hover:scale-110',
                    currentStyle.color === color
                      ? 'border-blue-500 shadow-md scale-110 ring-2 ring-blue-200 dark:ring-blue-800'
                      : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                  ]"
                  :style="{ 
                    backgroundColor: color,
                    boxShadow: color === '#ffffff' ? 'inset 0 0 0 1px rgba(0,0,0,0.1)' : 'none'
                  }"
                  :title="`Color: ${color}`"
                  :disabled="readonly"
                ></button>
                <input
                  v-model="currentStyle.color"
                  type="color"
                  class="w-7 h-7 rounded-md border-2 border-gray-300 dark:border-gray-600 cursor-pointer hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                  title="Custom color"
                  :disabled="readonly"
                />
              </div>
            </div>
            
            <!-- Stroke Width -->
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Size</span>
              <select
                v-model.number="currentStyle.strokeWidth"
                class="pl-3 pr-8 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors cursor-pointer"
                :disabled="readonly"
              >
                <option :value="1">1px</option>
                <option :value="2">2px</option>
                <option :value="3">3px</option>
                <option :value="4">4px</option>
                <option :value="5">5px</option>
                <option :value="6">6px</option>
                <option :value="8">8px</option>
                <option :value="10">10px</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Right Section: View Controls & Actions -->
        <div class="flex items-center gap-3">
          <!-- History Controls -->
          <div class="flex items-center gap-0.5 px-1.5 py-1 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
            <button
              @click="undo"
              :disabled="!canUndo || readonly"
              class="p-2 rounded-md text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
              title="Undo (Ctrl+Z)"
              aria-label="Undo last action"
            >
              <font-awesome-icon :icon="['fas','undo']" class="text-sm" />
            </button>
            <button
              @click="redo"
              :disabled="!canRedo || readonly"
              class="p-2 rounded-md text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
              title="Redo (Ctrl+Shift+Z)"
              aria-label="Redo last undone action"
            >
              <font-awesome-icon :icon="['fas','redo']" class="text-sm" />
            </button>
          </div>
          
          <!-- Divider -->
          <div class="h-8 w-px bg-gray-300 dark:bg-gray-600"></div>
          
          <!-- Zoom Controls -->
          <div class="flex items-center gap-1.5">
            <button
              @click="zoomOut"
              class="p-2 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition-all"
              title="Zoom out (Ctrl+-)"
            >
              <font-awesome-icon :icon="['fas','search-minus']" class="text-sm" />
            </button>
            <div class="relative">
              <select
                :value="Math.round(zoomLevel * 100)"
                @change="setZoomFromDropdown($event.target.value)"
                class="pl-2.5 pr-7 py-1.5 text-sm font-medium border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors cursor-pointer min-w-[70px] appearance-none"
              >
                <option :value="25">25%</option>
                <option :value="50">50%</option>
                <option :value="75">75%</option>
                <option :value="100">100%</option>
                <option :value="150">150%</option>
                <option :value="200">200%</option>
              </select>
              <!-- Display current zoom if not in dropdown options -->
              <div 
                v-if="![25, 50, 75, 100, 150, 200].includes(Math.round(zoomLevel * 100))"
                class="absolute inset-0 pointer-events-none flex items-center pl-2.5 text-sm font-medium text-gray-700 dark:text-gray-300"
              >
                {{ Math.round(zoomLevel * 100) }}%
              </div>
            </div>
            <button
              @click="zoomIn"
              class="p-2 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition-all"
              title="Zoom in (Ctrl++)"
            >
              <font-awesome-icon :icon="['fas','search-plus']" class="text-sm" />
            </button>
            <button
              @click="toggleFit"
              class="p-2 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition-all"
              :title="fitMode === 'width' ? 'Fit to width (Ctrl+0)' : 'Fit to height (Ctrl+0)'"
            >
              <font-awesome-icon :icon="fitMode === 'width' ? ['fas','expand-arrows-alt'] : ['fas','compress-arrows-alt']" class="text-sm" />
            </button>
          </div>
          
          <!-- Divider -->
          <div class="h-8 w-px bg-gray-300 dark:bg-gray-600"></div>
          
          <!-- Help Button -->
          <button
            @click="showKeyboardHelp = !showKeyboardHelp"
            class="p-2 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-blue-500 dark:hover:text-blue-400 transition-all"
            title="Keyboard shortcuts (?)"
            aria-label="Show keyboard shortcuts"
          >
            <font-awesome-icon :icon="['fas','info-circle']" class="text-base" />
          </button>
        </div>
      </div>
    </div>
    
    <!-- Image Viewport -->
    <div 
      ref="viewportRef"
      class="annotation-viewport relative overflow-hidden flex-1" 
      @wheel.prevent="onWheel"
      @mousedown="onViewportMouseDown"
      @mousemove="onViewportMouseMove"
      @mouseup="onViewportMouseUp"
      @mouseleave="onViewportMouseUp"
      @click="onViewportClick"
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
          style="max-width: none;"
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
        
        <!-- Direct Text Input with RichTextEditor -->
        <div
          v-if="isEditingText"
          class="absolute z-20 shadow-lg"
          :style="{
            left: Math.max(0, Math.min(textEditPosition.x, (canvasWidth || 800) - 320)) + 'px',
            top: Math.max(0, Math.min(textEditPosition.y - 20, (canvasHeight || 600) - 200)) + 'px',
            width: '300px'
          }"
          @mousedown.stop
        >
          <RichTextEditor
            ref="textEditElement"
            v-model="textContent"
            placeholder="Type your annotation text..."
            editor-class="rounded-b-none editor-content px-3 py-2 min-h-[100px] max-h-[200px] overflow-y-auto border-x border-b border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <div class="flex justify-end gap-2 px-2 pb-2 bg-white dark:bg-gray-800 border border-t-0 border-gray-300 dark:border-gray-600 rounded-b-md">
            <button
              @click="cancelTextEdit"
              class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="finishTextEdit"
              :disabled="!stripHtmlTags(textContent).trim()"
              class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Save
            </button>
          </div>
        </div>
        
        <!-- Numbered Annotation Markers -->
        <div
          v-for="(annotation, index) in annotations"
          :key="annotation.id"
          class="absolute annotation-marker"
          :style="getAnnotationStyle(annotation)"
          @mousedown="onAnnotationMarkerMouseDown(annotation, $event)"
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
          <div 
            v-if="getOverlayContent(annotation)" 
            class="annotation-comment-box ml-3"
            :style="getCommentBoxStyle(annotation)"
            @mousedown.stop
          >
            <div class="p-2 flex items-start gap-2">
              <div class="flex-shrink-0">
                <Avatar :user="getDisplayUserForAnnotation(annotation)" size="xs" :show-link="false" />
              </div>
              <div class="flex-1 min-w-0">
                <div v-if="editingAnnotationId === annotation.id" class="space-y-2">
                  <RichTextEditor
                    ref="annotationEditorRef"
                    v-model="editingAnnotationContent"
                    placeholder="Edit annotation text..."
                    editor-class="editor-content px-2 py-1 overflow-y-auto border-x border-b border-gray-300 dark:border-gray-600 rounded-b-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-xl focus:outline-none focus:ring-1 focus:ring-blue-500"
                    :editor-style="getEditorStyle(annotation)"
                  />
                  <div class="flex justify-end gap-1">
                    <button @click.stop="cancelAnnotationEdit" class="px-2 py-0.5 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
                      Cancel
                    </button>
                    <button @click.stop="saveAnnotationEdit" :disabled="!stripHtmlTags(editingAnnotationContent).trim()" class="px-2 py-0.5 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                      Save
                    </button>
                  </div>
                </div>
                <div v-else class="flex items-start justify-between gap-2">
                  <div class="text-xl text-gray-800 dark:text-gray-100 break-words">
                    <div class="text-gray-700 dark:text-gray-300" v-html="linkifyText(getOverlayContent(annotation))">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Resize handle -->
            <div 
              v-if="!readonly && editingAnnotationId !== annotation.id"
              class="resize-handle"
              @mousedown.stop="startResizeCommentBox(annotation, $event)"
              title="Drag to resize"
            >
              <font-awesome-icon :icon="['fas', 'grip-lines']" class="text-xs" />
            </div>
          </div>

          
        </div>
      </div>
    </div>
    </div>
    
    <!-- Comments Panel -->
    <div class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 flex flex-col h-full">
      <!-- Header -->
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Comments</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ combinedComments.length }} comment{{ combinedComments.length !== 1 ? 's' : '' }}</p>
      </div>
      
      <!-- Comments List (Scrollable) -->
      <div ref="commentsListRef" class="flex-1 overflow-y-auto p-4 space-y-4">
        <div v-if="combinedComments.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-8">
          <font-awesome-icon :icon="['fas', 'comments']" class="text-4xl mb-2" />
          <p>No comments yet</p>
          <p class="text-sm">Add a text annotation on the canvas or use the panel below</p>
        </div>
        
        <!-- Comment threads with nested replies -->
        <div
          v-for="entry in flattenedComments"
          :key="`${entry.type}-${entry.id}`"
          :ref="el => { if (entry.type === 'comment' && entry.id == highlightedCommentId) commentElementRefs[entry.id] = el }"
          class="relative bg-gray-50 dark:bg-gray-700 rounded-lg p-3 transition-colors cursor-default"
          :class="{
            'hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer': entry.type === 'annotation' || entry.annotation,
            'ring-1 ring-blue-400/60': entry.type === 'annotation' && selectedAnnotation?.id === entry.annotation.id,
            'ring-2 ring-orange-500 dark:ring-orange-600 bg-orange-50 dark:bg-orange-900/30': entry.type === 'comment' && entry.id == highlightedCommentId
          }"
          :style="{ marginLeft: `${entry.level * 16}px` }"
          @click="handleCommentEntryClick(entry)"
        >
          <!-- Tracing line for nested comments -->
          <div
            v-if="entry.level > 0"
            class="absolute left-0 top-0 bottom-0 w-0.5 bg-gray-300 dark:bg-gray-600"
            :style="{ left: '-8px' }"
          ></div>
          <template v-if="entry.type === 'annotation'">
            <div class="flex items-start justify-between mb-2">
              <div class="flex items-center gap-2">
                <Avatar :user="getDisplayUserForAnnotation(entry.annotation)" size="sm" :show-link="false" />
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Text annotation</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(entry.createdAt) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-1" v-if="!readonly && canEdit(entry.annotation) && editingAnnotationId !== entry.annotation.id">
                <button @click.stop="editAnnotation(entry.annotation)" class="text-gray-400 hover:text-blue-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'edit']" class="text-xs" />
                </button>
                <button v-if="canDelete(entry.annotation)" @click.stop="deleteAnnotation(entry.annotation)" class="text-gray-400 hover:text-red-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'trash']" class="text-xs" />
                </button>
              </div>
            </div>
            <div v-if="editingAnnotationId === entry.annotation.id" class="space-y-2">
              <RichTextEditor
                v-model="editingAnnotationContent"
                placeholder="Edit annotation text..."
                editor-class="editor-content px-3 py-2 min-h-[80px] max-h-[200px] overflow-y-auto border-x border-b border-gray-300 dark:border-gray-600 rounded-b-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <div class="flex justify-end gap-2">
                <button @click.stop="cancelAnnotationEdit" class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
                  Cancel
                </button>
                <button @click.stop="saveAnnotationEdit" :disabled="!stripHtmlTags(editingAnnotationContent).trim()" class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                  Save
                </button>
              </div>
            </div>
            <div v-else class="text-sm text-gray-700 dark:text-gray-300 break-words comment-content" v-html="linkifyText(entry.annotation.content)"></div>
            
            <!-- Reply and collapse buttons -->
            <div class="flex items-center gap-3 mt-2">
              <button v-if="!readonly" @click.stop="handleReply(entry)" class="text-xs text-gray-500 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition-colors">
                <font-awesome-icon :icon="['fas', 'reply']" class="mr-1" />
                Reply
              </button>
              <button v-if="entry.replies && entry.replies.length > 0" @click.stop="toggleThreadCollapse(entry)" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                <font-awesome-icon :icon="collapsedThreads[`${entry.type}-${entry.id}`] ? ['fas', 'caret-right'] : ['fas', 'caret-down']" class="mr-1" />
                {{ collapsedThreads[`${entry.type}-${entry.id}`] ? 'Show' : 'Hide' }} {{ entry.replies.length }} {{ entry.replies.length === 1 ? 'reply' : 'replies' }}
              </button>
            </div>
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
              <div class="flex items-center gap-1" v-if="(canEditComment(entry.comment) || canDeleteComment(entry.comment)) && editingCommentId !== entry.comment.id">
                <button v-if="canEditComment(entry.comment)" @click.stop="editComment(entry.comment)" class="text-gray-400 hover:text-blue-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'edit']" class="text-xs" />
                </button>
                <button v-if="canDeleteComment(entry.comment)" @click.stop="deleteComment(entry.comment)" class="text-gray-400 hover:text-red-500 transition-colors">
                  <font-awesome-icon :icon="['fas', 'trash']" class="text-xs" />
                </button>
              </div>
            </div>
            <div v-if="editingCommentId === entry.comment.id" class="space-y-1.5">
              <MentionAutocomplete
                v-model="editingCommentContent"
                :ticket-id="ticketId"
                :image-id="imageId"
                :public-token="publicToken"
                :is-public="isPublic"
                :include-external-users="true"
                placeholder="Edit comment..."
                class="w-full min-h-[60px] max-h-[150px] overflow-y-auto border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                rows="2"
              />
              <div class="flex justify-end gap-1.5">
                <button @click.stop="cancelCommentEdit" class="px-2.5 py-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
                  Cancel
                </button>
                <button @click.stop="saveCommentEdit" :disabled="!editingCommentContent.trim()" class="px-2.5 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                  Save
                </button>
              </div>
            </div>
            <div v-else class="text-sm text-gray-700 dark:text-gray-300 break-words comment-content" v-html="linkifyText(entry.comment.content)"></div>
            <div v-if="entry.comment.annotation_id && entry.annotation" class="mt-2 text-xs text-blue-600 dark:text-blue-400 flex items-center gap-1">
              <font-awesome-icon :icon="['fas', 'link']" />
              <span>Annotation #{{ getAnnotationNumber(entry.comment.annotation_id) }}</span>
            </div>
            
            <!-- Reply and collapse buttons -->
            <div class="flex items-center gap-3 mt-2">
              <button v-if="!readonly" @click.stop="handleReply(entry)" class="text-xs text-gray-500 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition-colors">
                <font-awesome-icon :icon="['fas', 'reply']" class="mr-1" />
                Reply
              </button>
              <button v-if="entry.replies && entry.replies.length > 0" @click.stop="toggleThreadCollapse(entry)" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                <font-awesome-icon :icon="collapsedThreads[`${entry.type}-${entry.id}`] ? ['fas', 'caret-right'] : ['fas', 'caret-down']" class="mr-1" />
                {{ collapsedThreads[`${entry.type}-${entry.id}`] ? 'Show' : 'Hide' }} {{ entry.replies.length }} {{ entry.replies.length === 1 ? 'reply' : 'replies' }}
              </button>
            </div>
          </template>
        </div>
      </div>
      
      <!-- Add Comment Form (Fixed at Bottom) -->
      <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <!-- Reply indicator -->
        <div v-if="selectedAnnotation || replyingToComment" class="px-4 pt-3 pb-2 text-xs text-blue-600 dark:text-blue-400 flex items-center gap-1.5 bg-blue-50 dark:bg-blue-900/20">
          <font-awesome-icon :icon="['fas', 'reply']" class="text-xs" />
          <span v-if="selectedAnnotation">Replying to Annotation #{{ getAnnotationNumber(selectedAnnotation.id) }}</span>
          <span v-else-if="replyingToComment">Replying to {{ getDisplayUserForComment(replyingToComment)?.name || 'comment' }}</span>
          <button @click="selectedAnnotation = null; replyingToComment = null" class="ml-auto text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <font-awesome-icon :icon="['fas', 'times']" class="text-xs" />
          </button>
        </div>
        
        <!-- Input area -->
        <div class="p-2 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-1.5">
            <div class="flex-1">
              <MentionAutocomplete
                v-model="newCommentContent"
                :ticket-id="ticketId"
                :image-id="imageId"
                :public-token="publicToken"
                :is-public="isPublic"
                :include-external-users="true"
                dropdown-position="top"
                placeholder="Add a comment..."
                class="w-full rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 resize-none"
                rows="1"
                @keydown.enter.ctrl="addComment"
              />
            </div>
            <button
              @click="addComment"
              :disabled="!newCommentContent.trim()"
              class="px-2.5 py-1.5 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title="Post comment (Ctrl+Enter)"
            >
              <font-awesome-icon :icon="['fas', 'paper-plane']" class="text-xs" />
            </button>
          </div>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 px-0.5">
            @mention • Ctrl+Enter to post
          </p>
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
        class="bg-white dark:bg-gray-800 p-6 rounded-lg max-w-lg w-full mx-4"
        @click.stop
      >
        <h3 id="text-modal-title" class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add Text Annotation</h3>
        <RichTextEditor
          ref="textInput"
          v-model="textContent"
          placeholder="Enter your annotation text with formatting..."
          editor-class="editor-content px-3 py-2 min-h-[120px] max-h-[250px] overflow-y-auto border-x border-b border-gray-300 dark:border-gray-600 rounded-b-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <div class="flex justify-end gap-2 mt-4">
          <button
            @click="closeTextModal"
            class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="saveTextAnnotation"
            :disabled="!stripHtmlTags(textContent).trim()"
            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Save
          </button>
        </div>
      </div>
    </div>
    
    <!-- Keyboard Shortcuts Help Modal -->
    <div
      v-if="showKeyboardHelp"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 backdrop-blur-sm"
      @click="showKeyboardHelp = false"
      role="dialog"
      aria-labelledby="help-modal-title"
      aria-modal="true"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-500 rounded-lg">
              <font-awesome-icon :icon="['fas','keyboard']" class="text-white text-lg" />
            </div>
            <div>
              <h3 id="help-modal-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Keyboard Shortcuts</h3>
              <p class="text-xs text-gray-600 dark:text-gray-400">Speed up your workflow with these shortcuts</p>
            </div>
          </div>
          <button
            @click="showKeyboardHelp = false"
            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-all"
            aria-label="Close help"
          >
            <font-awesome-icon :icon="['fas','times']" class="text-lg" />
          </button>
        </div>
        
        <!-- Content -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Drawing Tools -->
            <div class="space-y-3">
              <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 bg-blue-500 rounded-full"></div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Drawing Tools</h4>
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'hand-paper']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Grab Tool</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">G</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['far', 'square']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Rectangle</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">R</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['far', 'circle']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Circle</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">C</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'pencil-alt']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Freehand</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">F</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'i-cursor']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Text</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">T</kbd>
                </div>
              </div>
            </div>
            
            <!-- History & View Controls -->
            <div class="space-y-3">
              <!-- History -->
              <div>
                <div class="flex items-center gap-2 mb-3">
                  <div class="w-1 h-5 bg-green-500 rounded-full"></div>
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">History</h4>
                </div>
                <div class="space-y-2">
                  <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-2">
                      <font-awesome-icon :icon="['fas', 'undo']" class="text-gray-400 w-4" />
                      <span class="text-sm text-gray-700 dark:text-gray-300">Undo</span>
                    </div>
                    <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Ctrl+Z</kbd>
                  </div>
                  <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-2">
                      <font-awesome-icon :icon="['fas', 'redo']" class="text-gray-400 w-4" />
                      <span class="text-sm text-gray-700 dark:text-gray-300">Redo</span>
                    </div>
                    <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Ctrl+Shift+Z</kbd>
                  </div>
                </div>
              </div>
              
              <!-- Zoom & View -->
              <div>
                <div class="flex items-center gap-2 mb-3 mt-4">
                  <div class="w-1 h-5 bg-purple-500 rounded-full"></div>
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Zoom & View</h4>
                </div>
                <div class="space-y-2">
                  <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-2">
                      <font-awesome-icon :icon="['fas', 'search-plus']" class="text-gray-400 w-4" />
                      <span class="text-sm text-gray-700 dark:text-gray-300">Zoom In</span>
                    </div>
                    <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Ctrl++</kbd>
                  </div>
                  <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-2">
                      <font-awesome-icon :icon="['fas', 'search-minus']" class="text-gray-400 w-4" />
                      <span class="text-sm text-gray-700 dark:text-gray-300">Zoom Out</span>
                    </div>
                    <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Ctrl+-</kbd>
                  </div>
                  <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="flex items-center gap-2">
                      <font-awesome-icon :icon="['fas', 'expand-arrows-alt']" class="text-gray-400 w-4" />
                      <span class="text-sm text-gray-700 dark:text-gray-300">Fit to Screen</span>
                    </div>
                    <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Ctrl+0</kbd>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Other Actions -->
            <div class="space-y-3 md:col-span-2">
              <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 bg-orange-500 rounded-full"></div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Other Actions</h4>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'trash']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Delete Selected</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Del</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'times-circle']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Clear Selection</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Esc</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'hand-paper']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Pan Canvas</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">Ctrl+Drag</kbd>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center gap-2">
                    <font-awesome-icon :icon="['fas', 'question-circle']" class="text-gray-400 w-4" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">Toggle Help</span>
                  </div>
                  <kbd class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs font-semibold shadow-sm">?</kbd>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
          <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
            <font-awesome-icon :icon="['fas', 'lightbulb']" class="mr-1" />
            Pro tip: Hover over toolbar buttons to see their shortcuts
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

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import Avatar from '@/Components/Avatar.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import MentionAutocomplete from '@/Components/MentionAutocomplete.vue'
import { renderMentions } from '@/Utils/mentionUtils'

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
  canDeleteComment: {
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
  currentUserId: {
    type: [String, Number],
    default: null
  },
  highlightedCommentId: {
    type: [String, Number],
    default: null
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
const commentsListRef = ref(null)
const commentElementRefs = ref({})

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
  strokeWidth: 5,
  fillColor: 'transparent'
})

// Tools configuration
const tools = ref([
  { name: 'grab', label: 'Grab', icon: ['fas', 'hand-paper'], shortcut: 'G' },
  { name: 'rectangle', label: 'Rectangle', icon: ['far', 'square'], shortcut: 'R' },
  { name: 'circle', label: 'Circle', icon: ['far', 'circle'], shortcut: 'C' },
  { name: 'freehand', label: 'Freehand', icon: ['fas', 'pencil-alt'], shortcut: 'F' },
  { name: 'text', label: 'Text', icon: ['fas', 'i-cursor'], shortcut: 'T' }
])

// Color palette
const colorPalette = ref([
  '#3b82f6', // blue
  '#ef4444', // red
  '#f59e0b', // yellow
  '#ffffff', // white
  '#000000' // black
])

// Annotation state
const selectedAnnotation = ref(null)
const hoveredAnnotation = ref(null)
const highlightedAnnotation = ref(null)
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
const replyingToComment = ref(null)
const collapsedThreads = ref({})

// Inline editing state for annotations/comments
const editingAnnotationContent = ref('')
const editingCommentId = ref(null)
const editingCommentContent = ref('')

// Comment box resize state
const commentBoxSizes = ref({})
const isResizingCommentBox = ref(false)
const resizingAnnotationId = ref(null)
const resizeStartSize = ref({ width: 0, height: 0 })
const resizeStartPos = ref({ x: 0, y: 0 })
const resizeAnimationFrame = ref(null)
const pendingResizeUpdate = ref(null)

// Derived annotations/comments for unified panel
// Do NOT include text annotations in the comment panel - they appear on canvas only
const textAnnotations = computed(() => [])

const combinedComments = computed(() => {
  const annotationEntries = []

  const commentEntries = (props.comments || []).map(comment => ({
    type: 'comment',
    id: comment.id,
    comment,
    annotation: props.annotations.find(a => a.id === comment.annotation_id) || null,
    createdAt: comment.created_at || comment.updated_at || comment.createdAt || comment.updatedAt || null,
    parentCommentId: comment.parent_id || comment.parent_comment_id || null,
    replies: []
  }))

  console.log('[AnnotationCanvas] Comment entries:', commentEntries.map(c => ({ id: c.id, parentId: c.parentCommentId, content: c.comment.content?.substring(0, 30) })))

  // Separate top-level entries and replies
  const topLevelEntries = []
  const allEntries = [...annotationEntries, ...commentEntries]
  
  // First pass: identify top-level entries
  allEntries.forEach(entry => {
    if (entry.type === 'annotation' || !entry.parentCommentId) {
      topLevelEntries.push(entry)
    }
  })
  
  console.log('[AnnotationCanvas] Top-level entries:', topLevelEntries.length)
  
  // Second pass: nest replies under their parents
  commentEntries.forEach(entry => {
    if (entry.parentCommentId) {
      // Find parent (could be annotation or comment)
      const parent = allEntries.find(e => 
        (e.type === 'comment' && e.id === entry.parentCommentId) ||
        (e.type === 'annotation' && e.id === entry.parentCommentId)
      )
      console.log('[AnnotationCanvas] Found parent for comment', entry.id, ':', parent ? `${parent.type}-${parent.id}` : 'NOT FOUND')
      if (parent) {
        parent.replies.push(entry)
      } else {
        // Parent not found, treat as top-level
        topLevelEntries.push(entry)
      }
    }
  })
  
  // Sort top-level entries by time
  topLevelEntries.sort((a, b) => {
    const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0
    const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0
    return aTime - bTime
  })
  
  // Sort replies within each thread
  const sortReplies = (entry) => {
    if (entry.replies && entry.replies.length > 0) {
      entry.replies.sort((a, b) => {
        const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0
        const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0
        return aTime - bTime
      })
      entry.replies.forEach(sortReplies)
    }
  }
  topLevelEntries.forEach(sortReplies)
  
  console.log('[AnnotationCanvas] Final structure:', topLevelEntries.map(e => ({ 
    type: e.type, 
    id: e.id, 
    replies: e.replies.length 
  })))
  
  return topLevelEntries
})

// Flatten nested comments for rendering with indentation
const flattenedComments = computed(() => {
  const flattened = []
  
  const flatten = (entry, level = 0, parentId = null) => {
    flattened.push({ ...entry, level, parentId })
    if (entry.replies && entry.replies.length > 0) {
      const threadId = `${entry.type}-${entry.id}`
      const isCollapsed = collapsedThreads.value[threadId]
      if (!isCollapsed) {
        entry.replies.forEach(reply => flatten(reply, level + 1, threadId))
      }
    }
  }
  
  combinedComments.value.forEach(entry => flatten(entry))
  return flattened
})

const handleCommentEntryClick = (entry) => {
  if (entry.type === 'annotation' && entry.annotation) {
    zoomToAnnotation(entry.annotation)
  } else if (entry.type === 'comment' && entry.annotation) {
    zoomToAnnotation(entry.annotation)
  }
}

const toggleThreadCollapse = (entry) => {
  const threadId = `${entry.type}-${entry.id}`
  collapsedThreads.value[threadId] = !collapsedThreads.value[threadId]
}

const handleReply = (entry) => {
  if (entry.type === 'annotation') {
    selectedAnnotation.value = entry.annotation
    replyingToComment.value = null
  } else {
    replyingToComment.value = entry.comment
    selectedAnnotation.value = null
  }
}

// Modal state
const showDeleteConfirmation = ref(false)
const annotationToDelete = ref(null)

// Undo/Redo state
const undoStack = ref([])
const redoStack = ref([])
const canUndo = computed(() => undoStack.value.length > 0)
const canRedo = computed(() => redoStack.value.length > 0)

// Keyboard shortcuts state
const isShiftPressed = ref(false)
const isCtrlPressed = ref(false)
const isAltPressed = ref(false)
const isSpacePressed = ref(false)

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

// Watch for annotation style updates to clean up local resize state
watch(() => props.annotations, (newAnnotations) => {
  // Clean up local resize state for annotations that have been updated from server
  Object.keys(commentBoxSizes.value).forEach(annotationId => {
    const annotation = newAnnotations.find(a => a.id === parseInt(annotationId))
    const localSize = commentBoxSizes.value[annotationId]
    
    // Only clear if server has the SAME size we just set
    if (annotation?.style?.boxWidth === localSize?.width && 
        annotation?.style?.boxHeight === localSize?.height) {
      // Server has our updated size, safe to remove local state
      delete commentBoxSizes.value[annotationId]
    }
  })
}, { deep: true })

// Watch for highlighted annotation changes to redraw canvas
watch(highlightedAnnotation, () => {
  if (imageLoaded.value) {
    redrawCanvas()
  }
})

// Watch for highlighted comment to scroll and zoom
watch(() => props.highlightedCommentId, (commentId) => {
  if (!commentId) return
  
  nextTick(() => {
    // Find the comment in our data
    const comment = props.comments.find(c => c.id == commentId)
    if (!comment) return
    
    // Scroll to the comment in the panel
    const commentElement = commentElementRefs.value[commentId]
    if (commentElement && commentsListRef.value) {
      commentElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
    
    // If comment is linked to an annotation, zoom to it
    if (comment.annotation_id) {
      const annotation = props.annotations.find(a => a.id === comment.annotation_id)
      if (annotation) {
        // Small delay to let scroll happen first
        setTimeout(() => {
          zoomToAnnotation(annotation)
        }, 300)
      }
    }
  })
}, { immediate: true })

const showKeyboardHelp = ref(false)

// Image loading
const imageLoaded = ref(false)

// Refs for accessibility
const textInput = ref(null)
const annotationEditorRef = ref(null)

// Methods
const onImageLoad = () => {
  console.log('=== onImageLoad called ===')
  imageLoaded.value = true
  // Ensure default zoom is 100%
  zoomLevel.value = 1
  console.log('Image loaded - reset zoom to 1')
  // Reset pan so image starts at origin; user can pan/fit afterward
  panOffset.value = { x: 0, y: 0 }
  console.log('Image loaded - reset pan offset to {x: 0, y: 0}')
  nextTick(() => {
    setupCanvas()
    console.log('=== onImageLoad complete - canvas setup done ===')
    
    // Apply initial fit based on current fitMode
    nextTick(() => {
      applyFit(fitMode.value)
      console.log('Applied initial fit mode:', fitMode.value)
    })
  })
}

const onImageError = () => {
  console.error('Failed to load annotation image')
}

// Helper function to linkify URLs and render @mentions
const linkifyText = (text) => {
  if (!text) return ''
  
  // Check if text already contains HTML tags (from RichTextEditor)
  const hasHtmlTags = /<[^>]+>/.test(text)
  
  let processed = text
  
  // If no HTML tags, convert plain text line breaks to HTML <br> tags
  // This handles text from textarea inputs
  if (!hasHtmlTags) {
    processed = text.replace(/\n/g, '<br>')
  }
  
  // Render @mentions with proper styling
  // Note: We don't have access to mentionable users list here, so we'll style all mentions
  processed = processed.replace(/@([a-zA-Z0-9_\-]+(?:\s+[a-zA-Z0-9_\-]+)?)/g, (match) => {
    return `<span class="mention-valid bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-1 rounded font-medium">${match}</span>`
  })
  
  // Then linkify URLs outside of anchor tags
  const urlRegex = /(?<!href=["'])(?<!">)(https?:\/\/[^\s<]+)(?![^<]*<\/a>)/g
  
  const linkified = processed.replace(urlRegex, (url) => {
    // Remove trailing punctuation that might have been captured
    const cleanUrl = url.replace(/[.,;:!?]+$/, '')
    return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline break-all" onclick="event.stopPropagation()">${cleanUrl}</a>`
  })
  
  return linkified
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

const onViewportClick = (event) => {
  // Only deselect if clicking directly on the viewport (not on canvas or annotations)
  // This will fire when clicking on the gray area around the image
  if (event.target === viewportRef.value) {
    selectedAnnotation.value = null
  }
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
  
  // Clear reply when clicking on canvas (not on annotation) with any drawing tool
  if (!['grab', 'select'].includes(currentTool.value)) {
    selectedAnnotation.value = null
  }
  
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
  
  // Handle text tool
  if (currentTool.value === 'text') {
    // Check if clicking on an existing annotation or canvas
    const clickedAnnotation = findAnnotationAtPoint(coords)
    if (clickedAnnotation) {
      selectAnnotation(clickedAnnotation)
    } else {
      selectedAnnotation.value = null
    }
    startTextEdit(coords)
    return
  }
  
  // For grab tool, check if clicking on annotation or canvas
  if (currentTool.value === 'grab') {
    const clickedAnnotation = findAnnotationAtPoint(coords)
    if (clickedAnnotation) {
      selectAnnotation(clickedAnnotation)
    } else {
      selectedAnnotation.value = null
    }
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
  const plainText = stripHtmlTags(textContent.value)
  if (!plainText.trim()) {
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
  const plainText = stripHtmlTags(textContent.value)
  if (!isEditingText.value || !plainText.trim()) {
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
      content: textContent.value // Use HTML content from RichTextEditor
    }
    emit('annotation-updated', updated)
  } else {
    // Create a new TEXT ANNOTATION (also mirrored as a comment by parent after creation)
    saveToUndoStack()
    emit('annotation-created', {
      type: 'text',
      coordinates: canvasCoords,
      style: { ...currentStyle.value },
      content: textContent.value // Use HTML content from RichTextEditor
    })
  }

  cancelTextEdit()
}

const cancelTextEdit = () => {
  isEditingText.value = false
  textEditContent.value = ''
  textContent.value = ''
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

// Helper to strip HTML tags
const stripHtmlTags = (html) => {
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

// Comment management methods
const addComment = () => {
  console.log('[AnnotationCanvas.addComment] Called, content:', newCommentContent.value)
  console.log('[AnnotationCanvas.addComment] Selected annotation:', selectedAnnotation.value?.id || 'none')
  console.log('[AnnotationCanvas.addComment] Replying to comment:', replyingToComment.value?.id || 'none')
  
  // MentionAutocomplete returns plain text, no need to strip HTML
  if (!newCommentContent.value.trim()) {
    console.warn('[AnnotationCanvas.addComment] Empty content, aborting')
    return
  }
  
  const comment = {
    content: newCommentContent.value,
    annotation_id: selectedAnnotation.value?.id || null,
    parent_id: replyingToComment.value?.id || null,
    created_at: new Date().toISOString()
  }
  
  console.log('[AnnotationCanvas.addComment] Comment object:', comment)
  
  // Emit comment directly - authentication is handled by parent component
  console.log('[AnnotationCanvas.addComment] Emitting comment-added event')
  emit('comment-added', comment)
  newCommentContent.value = ''
  selectedAnnotation.value = null
  replyingToComment.value = null
  console.log('[AnnotationCanvas.addComment] Comment emitted, input cleared')
}

const deleteComment = (comment) => {
  if (confirm('Are you sure you want to delete this comment?')) {
    emit('comment-deleted', comment)
  }
}

// Edit annotation inline with RichTextEditor
const editAnnotation = (annotation) => {
  console.log('=== editAnnotation called ===', {
    annotationId: annotation.id,
    annotationType: annotation.type,
    content: annotation.content
  })
  
  editingAnnotationId.value = annotation.id
  editingAnnotationContent.value = annotation.content || ''
  
  console.log('State updated:', {
    editingAnnotationId: editingAnnotationId.value,
    editingAnnotationContent: editingAnnotationContent.value
  })
  
  // Focus the editor after it renders
  nextTick(() => {
    console.log('nextTick callback - checking ref:', {
      refExists: !!annotationEditorRef.value,
      refType: typeof annotationEditorRef.value,
      hasFocusMethod: annotationEditorRef.value && typeof annotationEditorRef.value.focus === 'function'
    })
    
    try {
      if (annotationEditorRef.value) {
        console.log('Attempting to focus editor...')
        annotationEditorRef.value.focus()
        console.log('Focus called successfully')
      } else {
        console.error('annotationEditorRef.value is null or undefined')
      }
    } catch (error) {
      console.error('Error focusing editor:', error)
    }
    
    // Additional check after a short delay
    setTimeout(() => {
      console.log('Delayed check - ref status:', {
        refExists: !!annotationEditorRef.value,
        activeElement: document.activeElement?.tagName,
        activeElementClass: document.activeElement?.className
      })
    }, 100)
  })
}

const saveAnnotationEdit = () => {
  if (!editingAnnotationId.value) return
  
  const plainText = stripHtmlTags(editingAnnotationContent.value)
  if (!plainText.trim()) {
    cancelAnnotationEdit()
    return
  }
  
  const annotation = props.annotations.find(a => a.id === editingAnnotationId.value)
  if (annotation) {
    saveToUndoStack()
    emit('annotation-updated', {
      ...annotation,
      content: editingAnnotationContent.value
    })
  }
  
  cancelAnnotationEdit()
}

const cancelAnnotationEdit = () => {
  editingAnnotationId.value = null
  editingAnnotationContent.value = ''
}

// Edit comment inline with RichTextEditor
const editComment = (comment) => {
  editingCommentId.value = comment.id
  editingCommentContent.value = comment.content || ''
}

const saveCommentEdit = () => {
  if (!editingCommentId.value) return
  
  // MentionAutocomplete returns plain text, no need to strip HTML
  if (!editingCommentContent.value.trim()) {
    cancelCommentEdit()
    return
  }
  
  const comment = props.comments.find(c => c.id === editingCommentId.value)
  if (comment) {
    emit('comment-updated', {
      ...comment,
      content: editingCommentContent.value
    })
  }
  
  cancelCommentEdit()
}

const cancelCommentEdit = () => {
  editingCommentId.value = null
  editingCommentContent.value = ''
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
      // Top-left corner
      return {
        x: ensureNumber(coords.x),
        y: ensureNumber(coords.y)
      }
    case 'circle':
      // Top-left corner of bounding box (center - radius)
      const centerX = ensureNumber(coords.centerX ?? coords.x)
      const centerY = ensureNumber(coords.centerY ?? coords.y)
      const radius = ensureNumber(coords.radius)
      return {
        x: centerX - radius,
        y: centerY - radius
      }
    case 'freehand':
      // Top-left corner of bounding box
      if (Array.isArray(coords.path) && coords.path.length > 0) {
        let minX = Infinity
        let minY = Infinity
        coords.path.forEach(point => {
          minX = Math.min(minX, ensureNumber(point.x))
          minY = Math.min(minY, ensureNumber(point.y))
        })
        return {
          x: minX === Infinity ? 0 : minX,
          y: minY === Infinity ? 0 : minY
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

// Only show overlay content for text annotations with their own content
// Do NOT show comment content in the overlay - comments belong in the comment panel
const getOverlayContent = (annotation) => {
  // Only show content for text annotations that have their own content
  if (annotation?.type === 'text' && annotation?.content) return annotation.content
  return null
}

// Get comment box style with custom size if resized
const getCommentBoxStyle = (annotation) => {
  // PRIORITY 1: Always prefer local state if it exists (during resize or pending save)
  const localSize = commentBoxSizes.value[annotation.id]
  if (localSize) {
    return {
      width: localSize.width ? `${localSize.width}px` : undefined,
      maxWidth: localSize.width ? `${localSize.width}px` : undefined,
      minHeight: localSize.height ? `${localSize.height}px` : undefined
    }
  }
  
  // PRIORITY 2: Use saved box size from database
  if (annotation.style?.boxWidth || annotation.style?.boxHeight) {
    return {
      width: annotation.style.boxWidth ? `${annotation.style.boxWidth}px` : undefined,
      maxWidth: annotation.style.boxWidth ? `${annotation.style.boxWidth}px` : undefined,
      minHeight: annotation.style.boxHeight ? `${annotation.style.boxHeight}px` : undefined
    }
  }
  
  // Default: no custom size
  return {}
}

// Get editor style with dynamic height matching comment box
const getEditorStyle = (annotation) => {
  // Check if there's a custom height
  const localSize = commentBoxSizes.value[annotation.id]
  const savedHeight = annotation.style?.boxHeight
  
  if (localSize?.height) {
    // Use local state height (during resize)
    const editorHeight = Math.max(60, localSize.height - 40) // Subtract padding/margins
    return {
      minHeight: `${editorHeight}px`,
      maxHeight: `${editorHeight}px`
    }
  } else if (savedHeight) {
    // Use saved height from database
    const editorHeight = Math.max(60, savedHeight - 40)
    return {
      minHeight: `${editorHeight}px`,
      maxHeight: `${editorHeight}px`
    }
  }
  
  // Default height
  return {
    minHeight: '60px',
    maxHeight: '150px'
  }
}

// Start resizing comment box
const startResizeCommentBox = (annotation, event) => {
  if (props.readonly) return
  
  event.preventDefault()
  event.stopPropagation()
  
  isResizingCommentBox.value = true
  resizingAnnotationId.value = annotation.id
  resizeStartPos.value = { x: event.clientX, y: event.clientY }
  
  // Get current size from annotation.style, local state, or default
  let currentSize = { width: 360, height: 0 }
  
  if (annotation.style?.boxWidth || annotation.style?.boxHeight) {
    currentSize = {
      width: annotation.style.boxWidth || 360,
      height: annotation.style.boxHeight || 0
    }
  } else if (commentBoxSizes.value[annotation.id]) {
    currentSize = commentBoxSizes.value[annotation.id]
  }
  
  resizeStartSize.value = { ...currentSize }
  
  // Add body class for cursor
  document.body.classList.add('resizing-comment-box')
  
  // Add event listeners
  document.addEventListener('mousemove', onResizeCommentBoxMove)
  document.addEventListener('mouseup', onResizeCommentBoxEnd)
}

// Handle comment box resize movement
const onResizeCommentBoxMove = (event) => {
  if (!isResizingCommentBox.value || !resizingAnnotationId.value) return
  
  // Store the latest mouse position
  pendingResizeUpdate.value = {
    clientX: event.clientX,
    clientY: event.clientY
  }
  
  // Only schedule one animation frame at a time
  if (resizeAnimationFrame.value) return
  
  resizeAnimationFrame.value = requestAnimationFrame(() => {
    if (!pendingResizeUpdate.value || !resizingAnnotationId.value) {
      resizeAnimationFrame.value = null
      return
    }
    
    const deltaX = pendingResizeUpdate.value.clientX - resizeStartPos.value.x
    const deltaY = pendingResizeUpdate.value.clientY - resizeStartPos.value.y
    
    // Calculate new size (minimum 120px width, 40px height)
    const newWidth = Math.max(120, Math.min(600, resizeStartSize.value.width + deltaX))
    const newHeight = Math.max(40, resizeStartSize.value.height + deltaY)
    
    commentBoxSizes.value[resizingAnnotationId.value] = {
      width: newWidth,
      height: newHeight
    }
    
    // Clear the animation frame reference
    resizeAnimationFrame.value = null
  })
}

// End comment box resize
const onResizeCommentBoxEnd = () => {
  if (!isResizingCommentBox.value || !resizingAnnotationId.value) return
  
  const annotationId = resizingAnnotationId.value
  
  // Cancel any pending animation frame and process final update
  if (resizeAnimationFrame.value) {
    cancelAnimationFrame(resizeAnimationFrame.value)
    resizeAnimationFrame.value = null
  }
  
  // Process any pending resize update one final time
  if (pendingResizeUpdate.value) {
    const deltaX = pendingResizeUpdate.value.clientX - resizeStartPos.value.x
    const deltaY = pendingResizeUpdate.value.clientY - resizeStartPos.value.y
    
    const newWidth = Math.max(120, Math.min(600, resizeStartSize.value.width + deltaX))
    const newHeight = Math.max(40, resizeStartSize.value.height + deltaY)
    
    commentBoxSizes.value[annotationId] = {
      width: newWidth,
      height: newHeight
    }
  }
  
  const size = commentBoxSizes.value[annotationId]
  
  isResizingCommentBox.value = false
  resizingAnnotationId.value = null
  pendingResizeUpdate.value = null
  
  // Remove body class
  document.body.classList.remove('resizing-comment-box')
  
  // Remove event listeners
  document.removeEventListener('mousemove', onResizeCommentBoxMove)
  document.removeEventListener('mouseup', onResizeCommentBoxEnd)
  
  // NOW update the database with final size (only on mouse release)
  if (size) {
    const annotation = props.annotations.find(a => a.id === annotationId)
    if (annotation) {
      const updatedStyle = {
        ...(annotation.style || {}),
        boxWidth: size.width,
        boxHeight: size.height
      }
      
      // Emit update event with new style - this triggers database save
      emit('annotation-updated', {
        id: annotation.id,
        style: updatedStyle
      })
      
      // DON'T clear local state immediately - keep it until the annotation prop updates
      // This prevents the visual jerk back to old size
      // The local state will be used as fallback until annotation.style is updated from the server
    }
  }
}

const getDisplayUserForComment = (comment) => {
  if (!comment) return buildUserLike()

  // Check for internal user first
  if (comment.user) {
    return comment.user
  }

  // Check for external user relationship
  if (comment.external_user) {
    return {
      name: comment.external_user.name,
      email: comment.external_user.email,
      is_external: true
    }
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

  // Check for internal user first
  if (annotation.user) {
    return annotation.user
  }

  // Check for external user relationship
  if (annotation.external_user) {
    return {
      name: annotation.external_user.name,
      email: annotation.external_user.email,
      is_external: true
    }
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

const zoomToAnnotation = (annotation) => {
  if (!annotation || !viewportRef.value) return
  
  // Get annotation anchor point
  const anchor = getAnnotationAnchor(annotation)
  
  // Set target zoom level (max 1.0 = 100%, no more than current view)
  const targetZoom = Math.min(1.0, zoomLevel.value)
  
  // Calculate viewport center
  const viewport = viewportRef.value
  const viewportCenterX = viewport.clientWidth / 2
  const viewportCenterY = viewport.clientHeight / 2
  
  // Calculate where the annotation center should be in canvas coordinates
  let annotationCenterX = anchor.x
  let annotationCenterY = anchor.y
  
  // Adjust center based on annotation type
  const coords = annotation.coordinates || {}
  switch (annotation.type) {
    case 'rectangle':
      annotationCenterX += ensureNumber(coords.width) / 2
      annotationCenterY += ensureNumber(coords.height) / 2
      break
    case 'circle':
      // Already centered for circles
      annotationCenterX = ensureNumber(coords.centerX ?? coords.x)
      annotationCenterY = ensureNumber(coords.centerY ?? coords.y)
      break
    case 'freehand':
    case 'text':
      // Use anchor point as-is
      break
  }
  
  // Calculate pan offset to center the annotation
  panOffset.value = {
    x: viewportCenterX - (annotationCenterX * targetZoom),
    y: viewportCenterY - (annotationCenterY * targetZoom)
  }
  
  // Set zoom and highlight
  isAnimating.value = true
  zoomLevel.value = targetZoom
  highlightedAnnotation.value = annotation
  
  setTimeout(() => {
    isAnimating.value = false
  }, 300)
}

// Expose only the methods needed by parent components
defineExpose({
  selectAnnotation,
  zoomToAnnotation
})

// Note: editAnnotation is defined earlier for inline editing in comment panel

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
    // Clear selection if deleting the currently selected annotation
    if (selectedAnnotation.value?.id === annotationToDelete.value.id) {
      selectedAnnotation.value = null
    }
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


// Zoom and Pan methods
const zoomIn = () => {
  const newZoom = Math.min(zoomLevel.value * 1.2, 5)
  zoomTowardCenter(newZoom)
}

const zoomOut = () => {
  const newZoom = Math.max(zoomLevel.value / 1.2, 0.1)
  zoomTowardCenter(newZoom)
}

const setZoomFromDropdown = (value) => {
  const newZoom = parseInt(value) / 100
  zoomTowardCenter(newZoom)
}

const zoomTowardCenter = (newZoom) => {
  if (!viewportRef.value) return
  
  const viewport = viewportRef.value
  const viewportCenterX = viewport.clientWidth / 2
  const viewportCenterY = viewport.clientHeight / 2
  
  // Calculate the point under the viewport center before zoom
  const beforeZoomX = (viewportCenterX - panOffset.value.x) / zoomLevel.value
  const beforeZoomY = (viewportCenterY - panOffset.value.y) / zoomLevel.value
  
  // Calculate the point under the viewport center after zoom
  const afterZoomX = beforeZoomX * newZoom
  const afterZoomY = beforeZoomY * newZoom
  
  // Adjust pan offset to keep the center point in the same position
  panOffset.value = {
    x: viewportCenterX - afterZoomX,
    y: viewportCenterY - afterZoomY
  }
  
  isAnimating.value = true
  zoomLevel.value = newZoom
  
  setTimeout(() => {
    isAnimating.value = false
  }, 300)
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
  console.log('=== applyFit called ===')
  console.log('Mode:', mode)
  
  if (!imageRef.value || !viewportRef.value) {
    console.error('Missing refs:', { 
      imageRef: !!imageRef.value, 
      viewportRef: !!viewportRef.value 
    })
    return
  }
  
  isAnimating.value = true
  const viewport = viewportRef.value
  const image = imageRef.value

  const viewportWidth = viewport.clientWidth
  const viewportHeight = viewport.clientHeight

  console.log('Viewport dimensions:', { 
    width: viewportWidth, 
    height: viewportHeight 
  })

  const imageWidth = image.naturalWidth
  const imageHeight = image.naturalHeight

  console.log('Image natural dimensions:', { 
    width: imageWidth, 
    height: imageHeight 
  })

  let scale
  if (mode === 'width') {
    // Fit to width - scale image so width fills viewport
    scale = viewportWidth / imageWidth
    console.log('Fit to WIDTH - scale:', scale)
  } else {
    // Fit to height - scale image so height fills viewport
    scale = viewportHeight / imageHeight
    console.log('Fit to HEIGHT - scale:', scale)
  }

  const scaledWidth = imageWidth * scale
  const scaledHeight = imageHeight * scale

  console.log('Scaled dimensions:', { 
    width: scaledWidth, 
    height: scaledHeight 
  })

  panOffset.value = {
    x: (viewportWidth - scaledWidth) / 2,
    y: (viewportHeight - scaledHeight) / 2
  }

  console.log('Pan offset:', { x: panOffset.value.x, y: panOffset.value.y })

  zoomLevel.value = scale
  console.log('Zoom level set to:', zoomLevel.value)
  
  setTimeout(() => {
    isAnimating.value = false
    console.log('=== applyFit complete ===')
  }, 300)
}

// Toggle between fitting to width and to height
const toggleFit = () => {
  console.log('toggleFit called - current mode:', fitMode.value)
  fitMode.value = fitMode.value === 'width' ? 'height' : 'width'
  console.log('toggleFit - switching to mode:', fitMode.value)
  applyFit(fitMode.value)
}

const fitToScreen = () => {
  if (!imageRef.value || !viewportRef.value) return
  
  const viewport = viewportRef.value
  const image = imageRef.value
  
  const viewportWidth = viewport.clientWidth
  const viewportHeight = viewport.clientHeight
  
  const imageWidth = image.naturalWidth
  const imageHeight = image.naturalHeight
  
  const scaleX = viewportWidth / imageWidth
  const scaleY = viewportHeight / imageHeight
  const scale = Math.min(scaleX, scaleY, 1) // Don't zoom in beyond 100%
  
  // Center the image
  const scaledWidth = imageWidth * scale
  const scaledHeight = imageHeight * scale
  
  panOffset.value = {
    x: (viewportWidth - scaledWidth) / 2,
    y: (viewportHeight - scaledHeight) / 2
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

  event.preventDefault()
  event.stopPropagation()

  // Allow selection for all users (for commenting), but only allow dragging if user can edit
  if (typeof props.canEdit === 'function' && !props.canEdit(annotation)) {
    // User can't edit this annotation, but they can select it to comment
    selectAnnotation(annotation)
    return
  }

  // User can edit, so allow dragging
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

  // Check if this annotation is highlighted
  const isHighlighted = highlightedAnnotation.value?.id === annotation.id
  
  // Use dark orange (#c2410c) for highlighted annotations
  ctx.value.strokeStyle = isHighlighted ? '#c2410c' : style.color
  ctx.value.lineWidth = isHighlighted ? style.strokeWidth + 2 : style.strokeWidth
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

// ==================== END OF ZOOM & PAN ====================

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
  
  // Log viewport dimensions on mount
  nextTick(() => {
    if (viewportRef.value) {
      console.log('=== Component Mounted ===')
      console.log('Viewport dimensions on mount:', {
        width: viewportRef.value.clientWidth,
        height: viewportRef.value.clientHeight
      })
      
      const container = document.querySelector('.annotation-canvas-container')
      if (container) {
        console.log('Container dimensions:', {
          width: container.clientWidth,
          height: container.clientHeight
        })
      }
    }
    
    if (imageRef.value) {
      console.log('Image element dimensions:', {
        clientWidth: imageRef.value.clientWidth,
        clientHeight: imageRef.value.clientHeight,
        naturalWidth: imageRef.value.naturalWidth,
        naturalHeight: imageRef.value.naturalHeight
      })
    }
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('keyup', handleKeyUp)
  cleanupAnnotationDrag()
  
  // Cleanup resize listeners if still active
  if (isResizingCommentBox.value) {
    if (resizeAnimationFrame.value) {
      cancelAnimationFrame(resizeAnimationFrame.value)
    }
    document.removeEventListener('mousemove', onResizeCommentBoxMove)
    document.removeEventListener('mouseup', onResizeCommentBoxEnd)
    document.body.classList.remove('resizing-comment-box')
  }
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
  transition: all 0.2s ease;
}

/* On-canvas comment box anchored near the marker */
.annotation-comment-box {
  position: absolute;
  left: 0;
  top: 32px; /* Position below the marker */
  background: white;
  color: #111827;
  border: 1px solid rgba(0,0,0,0.1);
  border-radius: 6px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
  padding: 6px 8px 20px 8px;
  max-width: 360px;
  width: fit-content;
  min-width: 120px;
  resize: none;
  overflow: visible;
  position: relative;
  will-change: width, height;
  contain: layout style;
}

.dark .annotation-comment-box {
  background: #111827;
  color: #e5e7eb;
  border-color: rgba(255,255,255,0.1);
}

/* Resize handle for comment boxes */
.resize-handle {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 24px;
  height: 24px;
  cursor: nwse-resize !important;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
  background: linear-gradient(135deg, transparent 50%, rgba(156, 163, 175, 0.25) 50%);
  border-bottom-right-radius: 6px;
  transition: all 0.2s ease;
  z-index: 10;
}

.resize-handle:hover {
  color: #6b7280;
  background: linear-gradient(135deg, transparent 50%, rgba(107, 114, 128, 0.4) 50%);
}

.dark .resize-handle {
  color: #6b7280;
  background: linear-gradient(135deg, transparent 50%, rgba(107, 114, 128, 0.3) 50%);
}

.dark .resize-handle:hover {
  color: #9ca3af;
  background: linear-gradient(135deg, transparent 50%, rgba(156, 163, 175, 0.4) 50%);
}

/* Prevent text selection during resize */
.annotation-comment-box.resizing {
  user-select: none;
}

body.resizing-comment-box {
  cursor: nwse-resize !important;
  user-select: none;
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
  top: 0;
  left: 32px;
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

/* ==================== NEW FEATURE STYLES ==================== */

/* Mini-map */
.minimap-container {
  backdrop-filter: blur(8px);
  animation: slideIn 0.2s ease-out;
}

.minimap-container img {
  pointer-events: none;
  user-select: none;
}

/* Zoom presets dropdown */
.zoom-presets-dropdown {
  animation: slideIn 0.15s ease-out;
  max-height: 300px;
  overflow-y: auto;
}

.zoom-presets-dropdown::-webkit-scrollbar {
  width: 4px;
}

.zoom-presets-dropdown::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 2px;
}

/* Context menu */
.fixed.bg-white.dark\\:bg-gray-800.border {
  animation: fadeIn 0.1s ease-out;
}

/* Touch support */
@media (hover: none) and (pointer: coarse) {
  /* Mobile optimizations */
  .annotation-toolbar {
    position: sticky;
    top: 0;
    z-index: 20;
  }
  
  .annotation-marker {
    transform: translate(-50%, -50%) scale(1.2);
  }
  
  .annotation-number {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }
  
  .annotation-actions {
    opacity: 1 !important;
  }
  
  button, .action-btn {
    min-height: 44px;
    min-width: 44px;
  }
}

/* Status badge animations */
.status-badge-enter-active,
.status-badge-leave-active {
  transition: all 0.2s ease;
}

.status-badge-enter-from {
  opacity: 0;
  transform: scale(0.8);
}

.status-badge-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

/* Threaded comment styles */
.border-l-2 {
  position: relative;
}

.border-l-2::before {
  content: '';
  position: absolute;
  left: -2px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: linear-gradient(to bottom, currentColor 0%, transparent 100%);
  opacity: 0.5;
}

/* Search input with icon */
input[type="text"]:focus {
  outline: none;
}

/* Comment filter badges */
.filter-active {
  position: relative;
}

.filter-active::after {
  content: '';
  position: absolute;
  top: -2px;
  right: -2px;
  width: 8px;
  height: 8px;
  background: #3b82f6;
  border-radius: 50%;
  border: 2px solid white;
}

.dark .filter-active::after {
  border-color: #1f2937;
}

/* Smooth transitions for panel updates */
.space-y-3 > * {
  transition: all 0.2s ease;
}

/* Enhanced hover states */
@media (hover: hover) {
  .minimap-container:hover {
    border-color: #3b82f6;
  }
  
  .zoom-presets-dropdown button:hover {
    transform: translateX(2px);
  }
}

/* Loading skeleton for comments */
@keyframes shimmer {
  0% {
    background-position: -1000px 0;
  }
  100% {
    background-position: 1000px 0;
  }
}

.loading-skeleton {
  animation: shimmer 2s infinite;
  background: linear-gradient(
    to right,
    #f3f4f6 0%,
    #e5e7eb 20%,
    #f3f4f6 40%,
    #f3f4f6 100%
  );
  background-size: 1000px 100%;
}

.dark .loading-skeleton {
  background: linear-gradient(
    to right,
    #374151 0%,
    #4b5563 20%,
    #374151 40%,
    #374151 100%
  );
}

/* Improved focus ring for accessibility */
*:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
  border-radius: 4px;
}

/* Smooth color transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}

/* Prevent transition on page load */
.annotation-canvas-container.loading * {
  transition: none !important;
}

/* Comment content formatting */
.comment-content {
  line-height: 1.5;
}

.comment-content p {
  margin-bottom: 0.5rem;
}

.comment-content p:last-child {
  margin-bottom: 0;
}

.comment-content strong,
.comment-content b {
  font-weight: 600;
  color: inherit;
}

.comment-content em,
.comment-content i {
  font-style: italic;
}

.comment-content ul,
.comment-content ol {
  margin: 0.5rem 0;
  padding-left: 1.5rem;
}

.comment-content ul {
  list-style-type: disc;
}

.comment-content ol {
  list-style-type: decimal;
}

.comment-content li {
  margin-bottom: 0.25rem;
}

.comment-content br {
  display: block;
  content: "";
  margin-top: 0.25rem;
}

.comment-content h1,
.comment-content h2,
.comment-content h3,
.comment-content h4,
.comment-content h5,
.comment-content h6 {
  font-weight: 600;
  margin-top: 0.75rem;
  margin-bottom: 0.5rem;
}

.comment-content h1 { font-size: 1.25rem; }
.comment-content h2 { font-size: 1.125rem; }
.comment-content h3 { font-size: 1rem; }

.comment-content code {
  background-color: rgba(0, 0, 0, 0.05);
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.875em;
}

.dark .comment-content code {
  background-color: rgba(255, 255, 255, 0.1);
}

.comment-content pre {
  background-color: rgba(0, 0, 0, 0.05);
  padding: 0.5rem;
  border-radius: 0.25rem;
  overflow-x: auto;
  margin: 0.5rem 0;
}

.dark .comment-content pre {
  background-color: rgba(255, 255, 255, 0.1);
}

.comment-content pre code {
  background-color: transparent;
  padding: 0;
}

.comment-content blockquote {
  border-left: 3px solid #d1d5db;
  padding-left: 0.75rem;
  margin: 0.5rem 0;
  color: #6b7280;
}

.dark .comment-content blockquote {
  border-left-color: #4b5563;
  color: #9ca3af;
}

.comment-content hr {
  border: none;
  border-top: 1px solid #e5e7eb;
  margin: 0.75rem 0;
}

.dark .comment-content hr {
  border-top-color: #374151;
}
</style>
