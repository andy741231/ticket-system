<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import EmailEditor from '@/Components/WYSIWYG/EmailEditor.vue';
import ColorPicker from '@/Components/Newsletter/ColorPicker.vue';
import VueCropper from 'vue-cropperjs';
import 'cropperjs/dist/cropper.css';
import axios from 'axios';

// FontAwesome icons
import { library } from '@fortawesome/fontawesome-svg-core';
import {
  faPen,
  faCopy,
  faTrash,
  faArrowUp,
  faArrowDown,
  faThLarge,
  faFile,
  faGear,
  faEye,
  faCode,
  faImage,
  faHeading,
  faAlignLeft,
  faTableColumns,
  faMinus,
  faLink as faLinkIcon,
  faFlag,
  faSquare,
  faRulerHorizontal,
  faInbox,
  faList,
} from '@fortawesome/free-solid-svg-icons';

library.add(
  faPen,
  faCopy,
  faTrash,
  faArrowUp,
  faArrowDown,
  faThLarge,
  faFile,
  faGear,
  faEye,
  faCode,
  faImage,
  faHeading,
  faAlignLeft,
  faTableColumns,
  faMinus,
  faLinkIcon,
  faFlag,
  faSquare,
  faRulerHorizontal,
  faInbox,
  faList,
);

const props = defineProps({
  modelValue: {
    type: [String, Object, Array],
    default: '',
  },
  initialHtml: {
    type: String,
    default: '',
  },
  templates: {
    type: Array,
    default: () => [],
  },
  campaignName: {
    type: String,
    default: '',
  },
  campaignId: {
    type: [Number, String],
    default: null,
  },
  tempKey: {
    type: String,
    default: null,
  },
});

// Helpers to load initial modelValue (v-model) into builder
function safeParseJson(val) {
  if (val == null) return null;
  if (typeof val !== 'string') return val;
  try {
    return JSON.parse(val);
  } catch (e) {
    return null;
  }
}

// Canvas Columns DnD handlers
// removed misplaced duplicate canvas handlers

  async function handleLogoLibraryUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('name', file.name.replace(/\.[^/.]+$/, ''));
      formData.append('folder', 'images/newsletters/logos');

      const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
      const uploadUrl = (typeof route === 'function')
        ? route('image.upload')
        : (typeof window !== 'undefined' && typeof window.route === 'function')
          ? window.route('image.upload')
          : '/api/image-upload';

      await axios.post(uploadUrl, formData, {
        headers: {
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
          Accept: 'application/json',
        },
      });
      await fetchHeaderLogos();
    } catch (e) {
      console.error('Upload failed', e);
    }
  }

// Prevent navigation for header logo links inside the canvas while editing
function handleCanvasClick(event, block) {
  try {
    if (!block || block.type !== 'header') return;
    const target = event.target;
    if (!target) return;
    // Check if an anchor was clicked inside the header block
    const anchor = target.closest && target.closest('a');
    if (anchor) {
      event.preventDefault();
      event.stopPropagation();
      return false;
    }
  } catch (e) {
    // no-op
  }
}

function applyStructure(struct) {
  if (!struct) return false;
  // Support both { blocks: [...] } and legacy plain array of blocks
  const blocksIn = Array.isArray(struct) ? struct : (Array.isArray(struct.blocks) ? struct.blocks : null);
  if (!Array.isArray(blocksIn) || blocksIn.length === 0) return false;
  isApplying.value = true;
  emailBlocks.value = blocksIn.map((b) => {
    const id = b.id || generateBlockId();
    const type = b.type || 'text';
    const data = b.data || {};
    const content = b.content || getBlockHtml(type, data);
    return { id, type, data, content, editable: b.editable ?? true, locked: b.locked ?? (type === 'footer') };
  });
  // Ensure only one footer and position it at the end
  dedupeAndNormalizeFooter();
  const changed = normalizeAllPaddings();
  isApplying.value = false;
  if (changed) {
    updateContent();
  }
  return true;
}

const emit = defineEmits(['update:modelValue', 'update:html-content', 'toggle-html-view', 'template-selected']);

// Export the default structure function for external use
defineExpose({
  getDefaultEmailStructure
});
// Guard to prevent recursive update loops when applying external props
const isApplying = ref(false);

// UI State
const editor = ref(null);
const showPreview = ref(false);
const showSourceEditor = ref(false);
const sourceContent = ref('');
const showTemplates = ref(false);
const showColorPicker = ref(false);
const selectedColor = ref('#000000');
const previewMode = ref('desktop'); // 'desktop' | 'mobile'
const activePanel = ref('blocks'); // 'blocks' | 'templates' | 'settings'
const selectedBlockId = ref(null);
const draggedBlock = ref(null);
const dragOverIndex = ref(null);
const columnDragOverIndex = ref(null);
const canvasColumnHover = ref({ blockId: null, colIdx: null });
const editingBlock = ref(null);
const showImageUpload = ref(false);
const imageFileInput = ref(null);
const imageCropper = ref(null);
const imageCropSrc = ref(null);
const imageFullWidth = ref(false);
const showButtonEditor = ref(false);
const showColumnEditor = ref(false);
const showTextEditor = ref(false);
const textEditor = ref(null);
// Nested columns state
const editingNested = ref(null); // { blockId, colIdx, itemIndex }
const nestedDragging = ref(null); // { blockId, colIdx, index }
const nestedDragOver = ref(null); // { blockId, colIdx, index }

// Table list editor state
const showTableListEditor = ref(false);
const tableListRowCount = ref(3);
const tableListGap = ref('10px');
const tableListMaxHeight = ref('60px');
const tableListCol1Width = ref('50%');
const tableListCol2Width = ref('50%');
const tableListContent = ref([]); // array of row content
const lastDroppedByBlock = ref({}); // { [blockId]: { colIdx, itemIndex } }
// Temporary model for the heading editor modal
const textModalContent = ref('');
// Text block additional settings (background, padding)
const textBackground = ref('transparent');
const textPadding = ref('15px 50px');
const textColor = ref('#666666');
// Per-text-block TipTap editors
const blockEditors = ref({});
// Header/Footer editor state
const showHeaderEditor = ref(false);
const headerTitle = ref('');
const headerSubtitle = ref('');
const headerBackground = ref('');
const headerTextColor = ref('');
const headerLogo = ref('');
const headerLogoAlt = ref('');
const headerLogoUrl = ref('');
const headerLogoAlignment = ref('center');
const headerLogoSize = ref('150px');
const headerLogoPadding = ref('10px');
const headerLogos = ref([]); // New logos array
const showLogoLibrary = ref(false); // New logo library modal visibility
const showFooterEditor = ref(false);
const emailCanvasRef = ref(null);
// Currently editing block (computed helper)
const currentEditingBlock = computed(() => emailBlocks.value.find(b => b.id === editingBlock.value) || null);
const footerContent = ref('');
const footerBackground = ref('');
const footerCopyright = ref('');
const footerTextColor = ref('#ffffff');
const showTokensDropdown = ref(null);

// Block styling controls
const showBlockSettings = ref(false);
const blockMargin = ref('0');
const blockPadding = ref('15px 35px');
const blockBorder = ref('none');
const blockBorderColor = ref('#e5e7eb');
const blockBorderWidth = ref('1px');
const blockBorderStyle = ref('solid');

// Columns editor state
const columnCount = ref(2);
const columnGap = ref('20px');
const columnsContent = ref([]); // array of strings

// Table list nested state
const tableListDragging = ref(null); // { blockId, rowIdx }
const tableListDragOver = ref(null); // { blockId, rowIdx }
const canvasTableListHover = ref({ blockId: null, rowIdx: null });
const editingTableListNested = ref(null); // { blockId, rowIdx, itemIndex }

// Email structure and content
const emailBlocks = ref([
  {
    id: 'header',
    type: 'header',
    data: {
      title: 'Newsletter Title',
      subtitle: 'Your weekly dose of updates',
      background: '#c8102e',
      textColor: '#ffffff',
      padding: '30px 12px',
      fullWidth: false,
      logo: '',
      logoAlt: '',
      logoUrl: '',
      logoAlignment: 'center',
      logoSize: '150px',
      logoPadding: '10px'
    },
    content: getBlockHtml('header', {
      title: 'Newsletter Title',
      subtitle: 'Your weekly dose of updates',
      background: '#c8102e',
      textColor: '#ffffff',
      padding: '30px 12px',
      fullWidth: false,
      logo: '',
      logoAlt: '',
      logoUrl: '',
      logoAlignment: 'center',
      logoSize: '150px',
      logoPadding: '10px'
    }),
    editable: true,
    locked: false,
  },
  {
    id: 'content',
    type: 'text',
    data: {
      content: '<p>Click to edit this text...</p>',
      fontSize: '16px',
      color: '#666666',
      lineHeight: '1.6',
      background: 'transparent',
      padding: '15px 50px',
      fullWidth: false
    },
    content: getBlockHtml('text', {
      content: '<p>Click to edit this text...</p>',
      fontSize: '16px',
      color: '#666666',
      lineHeight: '1.6',
      background: 'transparent',
      padding: '15px 50px',
      fullWidth: false
    }),
    editable: true,
    locked: false,
  },
  {
    id: 'footer',
    type: 'footer',
    data: {
      content: 'Thanks for reading! Forward this to someone who might find it useful.',
      links: [
        { text: 'Unsubscribe', url: '{{unsubscribe_url}}' },
        { text: 'Update preferences', url: '{{preferences_url}}' },
        { text: 'View in browser', url: '{{browser_url}}' }
      ],
      copyright: '2025 UH Population Health. All rights reserved.',
      background: '#c8102e',
      textColor: '#ffffff'
    },
    content: getBlockHtml('footer', {
      content: 'Thanks for reading! Forward this to someone who might find it useful.',
      links: [
        { text: 'Unsubscribe', url: '{{unsubscribe_url}}' },
        { text: 'Update preferences', url: '{{preferences_url}}' },
        { text: 'View in browser', url: '{{browser_url}}' }
      ],
      copyright: '2025 UH Population Health. All rights reserved.',
      background: '#c8102e',
      textColor: '#ffffff'
    }),
    editable: true,
    locked: true,
  },
]);

const htmlContent = computed(() => {
  return editor.value ? editor.value.getHTML() : '';
});

const jsonContent = computed(() => {
  return editor.value ? editor.value.getJSON() : null;
});

// Draggable Block Types with improved FontAwesome icons
const blockTypes = [
  { type: 'header', label: 'Header', icon: ['fas', 'inbox'] },
  { type: 'heading', label: 'Heading', icon: ['fas', 'heading'] },
  { type: 'text', label: 'Text', icon: ['fas', 'align-left'] },
  { type: 'image', label: 'Image', icon: ['fas', 'image'] },
  { type: 'button', label: 'Button', icon: ['fas', 'square'] },
  { type: 'columns', label: 'Columns', icon: ['fas', 'table-columns'] },
  { type: 'tablelist', label: 'Table List', icon: ['fas', 'list'] },
  { type: 'divider', label: 'Divider', icon: ['fas', 'minus'] },
  { type: 'spacer', label: 'Spacer', icon: ['fas', 'ruler-horizontal'] },
  { type: 'footer', label: 'Footer', icon: ['fas', 'flag'] },
];

// Computed properties for email structure
const emailStructure = computed(() => {
  return {
    blocks: emailBlocks.value,
    settings: {
      backgroundColor: '#f4f4f4',
      contentWidth: '600px',
      fontFamily: "'Source Sans 3', Roboto, Helvetica, Arial, sans-serif",
    },
  };
});

// Derived max output width for images to match newsletter content area
const imageMaxWidth = computed(() => {
  const cw = emailStructure.value?.settings?.contentWidth || '600px';
  const n = parseInt(String(cw).replace('px', ''), 10);
  return Number.isFinite(n) && n > 0 ? n : 600;
});

const finalHtmlContent = computed(() => {
  return `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Newsletter</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
      <style>
        body {
          margin: 0;
          padding: 0;
          font-family: ${emailStructure.value.settings.fontFamily};
          line-height: 1.6;
          background-color: ${emailStructure.value.settings.backgroundColor};
        }
        .newsletter-container {
          max-width: ${emailStructure.value.settings.contentWidth};
          margin: 0 auto;
          background-color: #ffffff;
          border-radius: 8px;
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
          overflow: hidden;
        }
      </style>
    </head>
    <body>
      <div class="newsletter-container">
        ${emailBlocks.value.map(block => block.content).join('')}
      </div>
    </body>
    </html>
  `;
});

// Preview-only HTML (no full document wrapper)
const previewInnerHtml = computed(() => {
  return emailBlocks.value.map(block => block.content).join('');
});

// Export default email structure for use in other components
function getDefaultEmailStructure() {
  return {
    blocks: [
      {
        id: 'header',
        type: 'header',
        data: {
          title: 'Newsletter Title',
          subtitle: 'Your weekly dose of updates',
          background: '#c8102e',
          textColor: '#ffffff',
          padding: '30px 12px',
          fullWidth: false,
          logo: '',
          logoAlt: '',
          logoUrl: '',
          logoAlignment: 'center',
          logoSize: '150px',
          logoPadding: '10px'
        },
        editable: true,
        locked: false
      },
      {
        id: 'content',
        type: 'text',
        data: {
          content: '<p>Start writing your email content here...</p>',
          fontSize: '16px',
          color: '#666666',
          lineHeight: '1.6',
          background: 'transparent',
          padding: '15px 50px',
          fullWidth: false
        },
        editable: true,
        locked: false
      },
      {
        id: 'footer',
        type: 'footer',
        data: {
          content: 'Thanks for reading! Forward this to someone who might find it useful.',
          links: [
            { text: 'Unsubscribe', url: '{{unsubscribe_url}}' },
            { text: 'Update preferences', url: '{{preferences_url}}' },
            { text: 'View in browser', url: '{{browser_url}}' }
          ],
          copyright: '2025 UH Population Health. All rights reserved.',
          background: '#c8102e',
          textColor: '#ffffff'
        },
        editable: true,
        locked: true
      }
    ],
    settings: {
      width: '600px',
      backgroundColor: '#ffffff',
      contentBackgroundColor: '#ffffff',
      textColor: '#1f2937',
      fontFamily: 'Arial, sans-serif',
      linkColor: '#3b82f6'
    }
  };
}

// Block Management Functions
function generateBlockId() {
  return 'block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
}

function addBlock(blockType, insertIndex = null) {
  const newBlock = createBlock(blockType);
  if (blockType === 'footer') {
    // Ensure only one footer exists: remove existing footers, then append the new one at the end
    for (let i = emailBlocks.value.length - 1; i >= 0; i--) {
      if (emailBlocks.value[i].type === 'footer') {
        emailBlocks.value.splice(i, 1);
      }
    }
    emailBlocks.value.push(newBlock);
  } else if (insertIndex !== null) {
    emailBlocks.value.splice(insertIndex, 0, newBlock);
  } else {
    // Insert before footer if it exists (for non-footer blocks)
    const footerIndex = emailBlocks.value.findIndex(b => b.type === 'footer');
    if (footerIndex > -1) {
      emailBlocks.value.splice(footerIndex, 0, newBlock);
    } else {
      emailBlocks.value.push(newBlock);
    }
  }
  
  updateContent();
}

function removeBlock(blockId) {
  const index = emailBlocks.value.findIndex(b => b.id === blockId);
  if (index > -1) {
    // Allow deletion of footer blocks - remove the locked check
    emailBlocks.value.splice(index, 1);
    // Clean up any editor instance tied to this block
    if (blockEditors.value[blockId]) {
      try { blockEditors.value[blockId].destroy(); } catch (e) {}
      delete blockEditors.value[blockId];
    }
    updateContent();
  }
}

// Ensure a single footer exists, prefer the last one, and move it to the end
function dedupeAndNormalizeFooter() {
  const footerIndexes = emailBlocks.value
    .map((b, idx) => ({ type: b.type, idx }))
    .filter(x => x.type === 'footer')
    .map(x => x.idx);
  if (footerIndexes.length <= 1) return; // nothing to do

  // Keep the last footer; remove earlier ones
  const lastIdx = footerIndexes[footerIndexes.length - 1];
  for (let i = footerIndexes.length - 2; i >= 0; i--) {
    emailBlocks.value.splice(footerIndexes[i], 1);
  }
  // After removals, re-find the last footer and move it to the end if needed
  const currentFooterIdx = emailBlocks.value.findIndex(b => b.type === 'footer');
  if (currentFooterIdx > -1 && currentFooterIdx !== emailBlocks.value.length - 1) {
    const [footer] = emailBlocks.value.splice(currentFooterIdx, 1);
    emailBlocks.value.push(footer);
  }
}

function moveBlock(blockId, direction) {
  const index = emailBlocks.value.findIndex(b => b.id === blockId);
  if (index === -1) return;

  const newIndex = direction === 'up' ? index - 1 : index + 1;
  if (newIndex < 0 || newIndex >= emailBlocks.value.length) return;

  const [block] = emailBlocks.value.splice(index, 1);
  emailBlocks.value.splice(newIndex, 0, block);
  updateContent();
}

function updateBlockContent(blockId, content) {
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (block) {
    block.content = content;
    updateContent();
  }
}

function updateContent() {
  if (isApplying.value) return;
  const structure = JSON.stringify(emailStructure.value);
  emit('update:modelValue', structure);
  emit('update:html-content', finalHtmlContent.value);
}

// Drag and Drop Functions
function onDragStart(event, blockType) {
  draggedBlock.value = blockType;
  event.dataTransfer.effectAllowed = 'copy';
}

function onDragEnd() {
  // Clear any visual placeholder and dragged state
  dragOverIndex.value = null;
  draggedBlock.value = null;
  columnDragOverIndex.value = null;
  canvasColumnHover.value = { blockId: null, colIdx: null };
}

function onDragOver(event, insertIndex = null) {
  event.preventDefault();
  event.dataTransfer.dropEffect = 'copy';
  if (draggedBlock.value && insertIndex !== null) {
    dragOverIndex.value = insertIndex;
  }
}

function onDragEnter(insertIndex) {
  if (draggedBlock.value) {
    dragOverIndex.value = insertIndex;
  }
}

function onDragLeave(insertIndex) {
  // Only clear if we're leaving the currently highlighted zone
  if (dragOverIndex.value === insertIndex) {
    dragOverIndex.value = null;
  }
}

function onDrop(event, insertIndex = null) {
  event.preventDefault();
  if (draggedBlock.value) {
    const targetIndex = insertIndex !== null ? insertIndex : (dragOverIndex.value ?? emailBlocks.value.length);
    addBlock(draggedBlock.value, targetIndex);
    draggedBlock.value = null;
    dragOverIndex.value = null;
  }
}

function onDropAt(event, insertIndex) {
  onDrop(event, insertIndex);
}

function clearDragOver() {
  dragOverIndex.value = null;
}

// Column drop zone handlers (for Columns Editor modal)
function onColumnDragOver(event, colIdx) {
  event.preventDefault();
  columnDragOverIndex.value = colIdx;
}

function onColumnDragLeave(colIdx) {
  if (columnDragOverIndex.value === colIdx) {
    columnDragOverIndex.value = null;
  }
}

function onColumnDrop(event, colIdx) {
  event.preventDefault();
  try {
    if (!draggedBlock.value) return;
    // Create a new block from the dragged type and take its rendered HTML
    const newBlock = createBlock(draggedBlock.value);
    const html = newBlock?.content || '';
    const existing = columnsContent.value[colIdx] || '';
    // Append if existing content present, otherwise set
    columnsContent.value[colIdx] = existing ? existing + html : html;
  } finally {
    draggedBlock.value = null;
    columnDragOverIndex.value = null;
  }
}

// Canvas Columns DnD handlers (for drop zones on the email canvas)
function onCanvasColumnDragOver(event, blockId, colIdx) {
  event.preventDefault();
  canvasColumnHover.value = { blockId, colIdx };
}

function onCanvasColumnDragLeave(blockId, colIdx) {
  const cur = canvasColumnHover.value || {};
  if (cur.blockId === blockId && cur.colIdx === colIdx) {
    canvasColumnHover.value = { blockId: null, colIdx: null };
  }
}

function onCanvasColumnDrop(event, blockId, colIdx) {
  event.preventDefault();
  event.stopPropagation();
  try {
    if (!draggedBlock.value) return;
    const newBlock = createBlock(draggedBlock.value);
    const blk = emailBlocks.value.find(b => b.id === blockId);
    if (!blk) return;
    // Normalize columns to items-based structure
    const cols = normalizeColumnsItems(blk.data?.columns || []);
    const item = {
      id: generateBlockId(),
      type: newBlock.type,
      data: newBlock.data,
      content: newBlock.content,
    };
    cols[colIdx].items.push(item);
    lastDroppedByBlock.value[blockId] = { colIdx, itemIndex: cols[colIdx].items.length - 1 };
    updateBlockData(blockId, { columns: cols });
  } finally {
    draggedBlock.value = null;
    canvasColumnHover.value = { blockId: null, colIdx: null };
  }
}

// Helpers for nested columns items
function normalizeColumnsItems(columns) {
  const cols = Array.isArray(columns) ? columns : [];
  // Ensure 2 columns
  const two = [cols[0] || {}, cols[1] || {}];
  return two.map((c, i) => {
    const items = Array.isArray(c.items) ? c.items : [];
    // If legacy content string exists and no items, convert to a single text item
    if (!items.length && typeof c.content === 'string' && c.content.trim() !== '') {
      return {
        items: [{ id: generateBlockId(), type: 'text', data: { content: c.content, background: 'transparent', padding: '15px 12px', fullWidth: false }, content: c.content }],
      };
    }
    return { items };
  });
}

function getNestedItem(blockId, colIdx, itemIndex) {
  const blk = emailBlocks.value.find(b => b.id === blockId);
  if (!blk) return null;
  const cols = normalizeColumnsItems(blk.data?.columns || []);
  return (cols[colIdx]?.items || [])[itemIndex] || null;
}

function updateNestedItem(blockId, colIdx, itemIndex, newDataPartial) {
  const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
  if (blkIdx === -1) return;
  const blk = emailBlocks.value[blkIdx];
  const cols = normalizeColumnsItems(blk.data?.columns || []);
  const items = cols[colIdx].items;
  if (!items || !items[itemIndex]) return;
  const item = items[itemIndex];
  item.data = { ...(item.data || {}), ...(newDataPartial || {}) };
  item.content = getBlockHtml(item.type, item.data);
  updateBlockData(blockId, { columns: cols });
}

function removeNestedItem(blockId, colIdx, itemIndex) {
  const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
  if (blkIdx === -1) return;
  const blk = emailBlocks.value[blkIdx];
  const cols = normalizeColumnsItems(blk.data?.columns || []);
  cols[colIdx].items.splice(itemIndex, 1);
  updateBlockData(blockId, { columns: cols });
}

function moveNestedItem(blockId, colIdx, fromIndex, toIndex) {
  if (fromIndex === toIndex) return;
  const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
  if (blkIdx === -1) return;
  const blk = emailBlocks.value[blkIdx];
  const cols = normalizeColumnsItems(blk.data?.columns || []);
  const arr = cols[colIdx].items;
  if (!arr || fromIndex < 0 || fromIndex >= arr.length || toIndex < 0 || toIndex > arr.length) return;
  const [it] = arr.splice(fromIndex, 1);
  // Insert before toIndex
  arr.splice(toIndex, 0, it);
  updateBlockData(blockId, { columns: cols });
}

// Nested drag handlers
function onNestedDragStart(event, blockId, colIdx, index) {
  nestedDragging.value = { blockId, colIdx, index };
  event.dataTransfer.effectAllowed = 'move';
}

function onNestedDragOver(event, blockId, colIdx, index) {
  event.preventDefault();
  event.stopPropagation();
  nestedDragOver.value = { blockId, colIdx, index };
}

function onNestedDrop(event, blockId, colIdx, index) {
  event.preventDefault();
  event.stopPropagation();
  // Reorder existing item
  if (nestedDragging.value) {
    const d = nestedDragging.value;
    if (d.blockId === blockId && d.colIdx === colIdx) {
      moveNestedItem(blockId, colIdx, d.index, index);
    }
    nestedDragging.value = null;
    nestedDragOver.value = null;
    return;
  }
  // Insert a new block at this position
  if (draggedBlock.value) {
    const newBlock = createBlock(draggedBlock.value);
    const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
    if (blkIdx !== -1) {
      const blk = emailBlocks.value[blkIdx];
      const cols = normalizeColumnsItems(blk.data?.columns || []);
      const arr = cols[colIdx].items;
      const item = { id: generateBlockId(), type: newBlock.type, data: newBlock.data, content: newBlock.content };
      const safeIndex = Math.max(0, Math.min(index, arr.length));
      arr.splice(safeIndex, 0, item);
      lastDroppedByBlock.value[blockId] = { colIdx, itemIndex: safeIndex };
      updateBlockData(blockId, { columns: cols });
    }
    draggedBlock.value = null;
  }
}

function onNestedDragEnd() {
  nestedDragging.value = null;
  nestedDragOver.value = null;
}

// Open nested item editor
function openNestedEditor(blockId, colIdx, itemIndex) {
  const item = getNestedItem(blockId, colIdx, itemIndex);
  if (!item) return;
  editingNested.value = { blockId, colIdx, itemIndex };
  if (item.type === 'text' || item.type === 'heading') {
    textModalContent.value = item.data?.content || '';
    textBackground.value = item.data?.background || 'transparent';
    textColor.value = item.data?.color || (item.type === 'heading' ? '#333333' : '#666666');
    showTextEditor.value = true;
  } else if (item.type === 'image') {
    imageCropSrc.value = item.data?.src || null;
    imageFullWidth.value = item.data?.fullWidth || false;
    showImageUpload.value = true;
  } else if (item.type === 'button') {
    buttonText.value = item.data?.text || 'Click Here';
    buttonUrl.value = item.data?.url || '#';
    buttonBackground.value = item.data?.background || '#c8102e';
    showButtonEditor.value = true;
  } else {
    // Fallback: treat as text content
    textModalContent.value = item.data?.content || item.content || '';
    showTextEditor.value = true;
  }
}

// Helper: get normalized items for a block's column index (0-based)
function columnsItems(block, zeroBasedColIdx) {
  try {
    const cols = normalizeColumnsItems(block?.data?.columns || []);
    const arr = cols[zeroBasedColIdx]?.items;
    return Array.isArray(arr) ? arr : [];
  } catch (e) {
    return [];
  }
}

// Open last nested item in a specific column, or open Columns modal if none
function openLastNestedOrColumns(block, colIdx) {
  try {
    const items = columnsItems(block, colIdx);
    if (items.length > 0) {
      openNestedEditor(block.id, colIdx, items.length - 1);
    } else {
      editingBlock.value = block.id;
      columnGap.value = block.data?.gap || '20px';
      showColumnEditor.value = true;
    }
  } catch (e) {}
}

// Open last nested item across both columns; fallback to Columns modal
function openLastNestedAcross(block) {
  try {
    // Prefer the last dropped item if available
    const last = lastDroppedByBlock.value?.[block.id];
    if (last) {
      const items = columnsItems(block, last.colIdx);
      if (items && items[last.itemIndex]) {
        openNestedEditor(block.id, last.colIdx, last.itemIndex);
        return;
      }
    }
    const left = columnsItems(block, 0);
    const right = columnsItems(block, 1);
    if (right.length > 0) {
      openNestedEditor(block.id, 1, right.length - 1);
      return;
    }
    if (left.length > 0) {
      openNestedEditor(block.id, 0, left.length - 1);
      return;
    }
    editingBlock.value = block.id;
    columnGap.value = block.data?.gap || '20px';
    showColumnEditor.value = true;
  } catch (e) {}
}

// --- Table List Helper Functions ---
function normalizeTableListRows(rows) {
  const rowsArr = Array.isArray(rows) ? rows : [];
  return rowsArr.map((r, i) => {
    const items = Array.isArray(r.items) ? r.items : [];
    return { items, content: r.content || '' };
  });
}

function getTableListItem(blockId, rowIdx, itemIndex) {
  const blk = emailBlocks.value.find(b => b.id === blockId);
  if (!blk) return null;
  const rows = normalizeTableListRows(blk.data?.rows || []);
  return (rows[rowIdx]?.items || [])[itemIndex] || null;
}

function updateTableListItem(blockId, rowIdx, itemIndex, newDataPartial) {
  const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
  if (blkIdx === -1) return;
  const blk = emailBlocks.value[blkIdx];
  const rows = normalizeTableListRows(blk.data?.rows || []);
  const items = rows[rowIdx].items;
  if (!items || !items[itemIndex]) return;
  const item = items[itemIndex];
  item.data = { ...(item.data || {}), ...(newDataPartial || {}) };
  item.content = getBlockHtml(item.type, item.data);
  updateBlockData(blockId, { rows });
}

function removeTableListItem(blockId, rowIdx, itemIndex) {
  const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
  if (blkIdx === -1) return;
  const blk = emailBlocks.value[blkIdx];
  const rows = normalizeTableListRows(blk.data?.rows || []);
  rows[rowIdx].items.splice(itemIndex, 1);
  updateBlockData(blockId, { rows });
}

function openTableListEditor(blockId) {
  editingBlock.value = blockId;
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (block) {
    tableListRowCount.value = block.data?.rowCount || 3;
    tableListGap.value = block.data?.gap || '10px';
    tableListMaxHeight.value = block.data?.maxHeight || '60px';
    tableListCol1Width.value = block.data?.col1Width || '50%';
    tableListCol2Width.value = block.data?.col2Width || '50%';
    showTableListEditor.value = true;
  }
}

function saveTableListChanges() {
  updateBlockData(editingBlock.value, {
    rowCount: tableListRowCount.value,
    gap: tableListGap.value,
    maxHeight: tableListMaxHeight.value,
    col1Width: tableListCol1Width.value,
    col2Width: tableListCol2Width.value
  });
  showTableListEditor.value = false;
  editingBlock.value = null;
}

function cancelTableListEdit() {
  showTableListEditor.value = false;
  editingBlock.value = null;
}

function openTableListNestedEditor(blockId, rowIdx, itemIndex) {
  const item = getTableListItem(blockId, rowIdx, itemIndex);
  if (!item) return;
  editingTableListNested.value = { blockId, rowIdx, itemIndex };
  if (item.type === 'text' || item.type === 'heading') {
    textModalContent.value = item.data?.content || '';
    textBackground.value = item.data?.background || 'transparent';
    textColor.value = item.data?.color || (item.type === 'heading' ? '#333333' : '#666666');
    showTextEditor.value = true;
  } else if (item.type === 'image') {
    imageCropSrc.value = item.data?.src || null;
    imageFullWidth.value = item.data?.fullWidth || false;
    showImageUpload.value = true;
  } else if (item.type === 'button') {
    buttonText.value = item.data?.text || 'Click Here';
    buttonUrl.value = item.data?.url || '#';
    buttonBackground.value = item.data?.background || '#c8102e';
    showButtonEditor.value = true;
  } else {
    textModalContent.value = item.data?.content || item.content || '';
    showTextEditor.value = true;
  }
}

// Canvas table list drop zone handlers
function onCanvasTableListDragOver(event, blockId, rowIdx) {
  event.preventDefault();
  canvasTableListHover.value = { blockId, rowIdx };
}

function onCanvasTableListDrop(event, blockId, rowIdx) {
  event.preventDefault();
  try {
    const newBlock = createBlock(draggedBlock.value);
    const blk = emailBlocks.value.find(b => b.id === blockId);
    if (!blk) return;
    const rows = normalizeTableListRows(blk.data?.rows || []);
    const item = {
      id: generateBlockId(),
      type: newBlock.type,
      data: newBlock.data,
      content: newBlock.content
    };
    rows[rowIdx].items.push(item);
    lastDroppedByBlock.value[blockId] = { rowIdx, itemIndex: rows[rowIdx].items.length - 1 };
    updateBlockData(blockId, { rows });
  } finally {
    draggedBlock.value = null;
    canvasTableListHover.value = { blockId: null, rowIdx: null };
  }
}

// Table list drag handlers
function onTableListNestedDragStart(event, blockId, rowIdx, index) {
  tableListDragging.value = { blockId, rowIdx, index };
  event.dataTransfer.effectAllowed = 'move';
}

function onTableListNestedDragEnd() {
  tableListDragging.value = null;
  tableListDragOver.value = null;
}

function onTableListNestedDragOver(event, blockId, rowIdx, index) {
  event.preventDefault();
  if (!tableListDragging.value) return;
  tableListDragOver.value = { blockId, rowIdx, index };
}

function onTableListNestedDrop(event, blockId, rowIdx, index) {
  event.preventDefault();
  const dragging = tableListDragging.value;
  if (dragging && dragging.blockId === blockId && dragging.rowIdx === rowIdx) {
    moveTableListItem(blockId, rowIdx, dragging.index, index);
  }
  tableListDragging.value = null;
  tableListDragOver.value = null;
}

function moveTableListItem(blockId, rowIdx, fromIndex, toIndex) {
  const blkIdx = emailBlocks.value.findIndex(b => b.id === blockId);
  if (blkIdx === -1) return;
  const blk = emailBlocks.value[blkIdx];
  const rows = normalizeTableListRows(blk.data?.rows || []);
  const arr = rows[rowIdx].items;
  if (!arr || fromIndex < 0 || fromIndex >= arr.length || toIndex < 0 || toIndex > arr.length) return;
  const [it] = arr.splice(fromIndex, 1);
  arr.splice(toIndex, 0, it);
  updateBlockData(blockId, { rows });
}

function tableListItems(block, rowIdx) {
  try {
    const rows = normalizeTableListRows(block?.data?.rows || []);
    const arr = rows[rowIdx]?.items;
    return Array.isArray(arr) ? arr : [];
  } catch (e) {
    return [];
  }
}

function openLastTableListItem(block) {
  try {
    const last = lastDroppedByBlock.value?.[block.id];
    if (last && last.rowIdx !== undefined) {
      const items = tableListItems(block, last.rowIdx);
      if (items && items[last.itemIndex]) {
        openTableListNestedEditor(block.id, last.rowIdx, last.itemIndex);
        return;
      }
    }
    // Find last row with items
    const rowCount = block.data?.rowCount || 3;
    for (let i = rowCount - 1; i >= 0; i--) {
      const items = tableListItems(block, i);
      if (items.length > 0) {
        openTableListNestedEditor(block.id, i, items.length - 1);
        return;
      }
    }
    // No items, open editor
    openTableListEditor(block.id);
  } catch (e) {}
}

// --- Padding Normalization Utilities ---
const OLD_TEXT_PADDINGS = new Set(['15px 12px', '15px 50px']);
const OLD_HEADING_PADDINGS = new Set(['15px 12px']);
const OLD_IMAGE_PADDINGS = new Set(['15px 12px', '20px 12px']);

function normalizePaddingForBlockType(type, data) {
  if (!data) return data;
  const out = { ...data };
  if (type === 'text') {
    if (!out.padding || OLD_TEXT_PADDINGS.has(String(out.padding))) {
      out.padding = '15px 35px';
    }
  } else if (type === 'heading') {
    if (!out.padding || OLD_HEADING_PADDINGS.has(String(out.padding))) {
      out.padding = '15px 35px';
    }
  } else if (type === 'image') {
    const isFull = !!out.fullWidth;
    const target = isFull ? '0' : '0 35px';
    if (!out.padding || OLD_IMAGE_PADDINGS.has(String(out.padding))) {
      out.padding = target;
    }
  }
  return out;
}

// Returns true if any change was made. Also regenerates block/item content if changed.
function normalizeAllPaddings() {
  let changed = false;
  const blocks = emailBlocks.value || [];
  for (let i = 0; i < blocks.length; i++) {
    const b = blocks[i];
    if (!b) continue;
    // Normalize top-level
    const newData = normalizePaddingForBlockType(b.type, b.data || {});
    if (JSON.stringify(newData) !== JSON.stringify(b.data || {})) {
      b.data = newData;
      b.content = getBlockHtml(b.type, b.data || {});
      changed = true;
    }
    // Normalize nested items inside columns
    if (b.type === 'columns') {
      const cols = normalizeColumnsItems((b.data && b.data.columns) || []);
      let nestedChanged = false;
      for (let c = 0; c < cols.length; c++) {
        const items = cols[c]?.items || [];
        for (let j = 0; j < items.length; j++) {
          const it = items[j];
          const nd = normalizePaddingForBlockType(it.type, it.data || {});
          if (JSON.stringify(nd) !== JSON.stringify(it.data || {})) {
            it.data = nd;
            it.content = getBlockHtml(it.type, it.data);
            nestedChanged = true;
          }
        }
      }
      if (nestedChanged) {
        b.data = { ...(b.data || {}), columns: cols };
        b.content = getBlockHtml(b.type, b.data || {});
        changed = true;
      }
    }
    // Normalize nested items inside table list
    if (b.type === 'tablelist') {
      const rows = normalizeTableListRows((b.data && b.data.rows) || []);
      let nestedChanged = false;
      for (let r = 0; r < rows.length; r++) {
        const items = rows[r]?.items || [];
        for (let j = 0; j < items.length; j++) {
          const it = items[j];
          const nd = normalizePaddingForBlockType(it.type, it.data || {});
          if (JSON.stringify(nd) !== JSON.stringify(it.data || {})) {
            it.data = nd;
            it.content = getBlockHtml(it.type, it.data);
            nestedChanged = true;
          }
        }
      }
      if (nestedChanged) {
        b.data = { ...(b.data || {}), rows };
        b.content = getBlockHtml(b.type, b.data || {});
        changed = true;
      }
    }
  }
  return changed;
}

// Template Functions
function loadTemplate(template) {
  console.log('Loading template:', template);
  
  if (template.content) {
    console.log('Template content type:', typeof template.content);
    console.log('Template content:', template.content);
    
    try {
      let parsed;
      if (typeof template.content === 'string') {
        parsed = JSON.parse(template.content);
      } else {
        parsed = template.content;
      }
      
      console.log('Parsed content:', parsed);
      
      if (parsed.blocks && Array.isArray(parsed.blocks)) {
        console.log('Found blocks structure, loading', parsed.blocks.length, 'blocks');
        // Ensure each block has proper structure and regenerate IDs
        emailBlocks.value = parsed.blocks.map((block) => {
          const newId = generateBlockId();
          return {
            id: newId,
            type: block.type || 'text',
            content: block.content || getBlockHtml(block.type || 'text', block.data || {}),
            data: block.data || {},
            editable: block.editable !== false,
            locked: block.locked || false
          };
        });
        // Normalize duplicate footers and move footer to the end
        dedupeAndNormalizeFooter();
      } else {
        console.log('No blocks structure found, parsing HTML content');
        // Parse HTML content and try to split into meaningful blocks
        const htmlContent = template.html_content || parsed || template.content;
        console.log('HTML content to parse:', htmlContent);
        emailBlocks.value = parseHtmlIntoBlocks(htmlContent);
        // Normalize duplicate footers and move footer to the end
        dedupeAndNormalizeFooter();
      }
    } catch (e) {
      console.log('JSON parse failed:', e);
      // Parse HTML content and try to split into meaningful blocks
      const htmlContent = template.html_content || template.content;
      console.log('Fallback HTML content:', htmlContent);
      emailBlocks.value = parseHtmlIntoBlocks(htmlContent);
      // Normalize duplicate footers and move footer to the end
      dedupeAndNormalizeFooter();
    }
  }
  
  console.log('Final emailBlocks:', emailBlocks.value);
  // Emit selected template to allow parent to react (e.g., override from name/email)
  try { emit('template-selected', template); } catch (e) {}
  showTemplates.value = false;
  updateContent();
}

// Helper function to parse HTML content into individual blocks
function parseHtmlIntoBlocks(htmlContent) {
  console.log('parseHtmlIntoBlocks called with:', htmlContent);
  
  if (!htmlContent) return [];
  
  const blocks = [];
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = htmlContent;

  console.log('tempDiv innerHTML length:', tempDiv.innerHTML?.length || 0);

  // Helper: safe style reads without throwing
  const safeStyle = (el, prop, fallback) => {
    try {
      const s = (el && typeof getComputedStyle === 'function') ? getComputedStyle(el) : null;
      return (s && s[prop]) ? s[prop] : (fallback ?? undefined);
    } catch (e) {
      return fallback;
    }
  };

  // Unwrap common wrappers (html/body/single container/table wrappers)
  let root = tempDiv;
  // html -> body
  if (root.children.length === 1 && root.children[0].tagName?.toLowerCase() === 'html') {
    root = root.children[0];
    const body = root.querySelector('body');
    if (body) root = body;
    console.log('Unwrapped to <body>');
  }
  // Iteratively unwrap single-child containers to reach meaningful children
  let unwraps = 0;
  while (unwraps < 5 && root.children && root.children.length === 1) {
    const only = root.children[0];
    const t = only.tagName?.toLowerCase();
    // Known wrappers: div/section/main/table/tbody/tr/td
    if (['div', 'section', 'main', 'table', 'tbody', 'tr', 'td'].includes(t)) {
      root = only;
      unwraps++;
      console.log('Unwrapped wrapper level', unwraps, 'tag:', t);
    } else {
      break;
    }
  }

  // Build candidate elements depending on wrapper type
  let candidates = [];
  const tag = root.tagName?.toLowerCase();
  if (tag === 'table') {
    // Prefer rows as units
    candidates = Array.from(root.querySelectorAll(':scope > tbody > tr, :scope > tr'));
  } else if (tag === 'tbody') {
    candidates = Array.from(root.querySelectorAll(':scope > tr'));
  } else if (tag === 'tr') {
    candidates = Array.from(root.querySelectorAll(':scope > td'));
  } else if (tag === 'td') {
    candidates = Array.from(root.children);
  } else {
    candidates = Array.from(root.children);
  }

  console.log('Candidate elements count:', candidates.length, 'root tag:', tag);
  console.log('Candidates:', candidates.map(c => ({ tag: c.tagName, class: c.className, id: c.id })));

  if (candidates.length === 0) {
    // As a fallback, treat full HTML as a single text block
    return [{
      id: generateBlockId(),
      type: 'text',
      content: htmlContent,
      data: { content: htmlContent },
      editable: true,
      locked: false
    }];
  }

  // If still a single giant wrapper, try one level deeper split by significant nodes (e.g., sections within a div)
  if (candidates.length === 1) {
    const deeper = candidates[0];
    const deepTag = deeper.tagName?.toLowerCase();
    let deepChildren = [];
    if (deepTag === 'table') {
      deepChildren = Array.from(deeper.querySelectorAll(':scope > tbody > tr, :scope > tr'));
    } else if (deepTag === 'tbody') {
      deepChildren = Array.from(deeper.querySelectorAll(':scope > tr'));
    } else if (deepTag === 'tr') {
      deepChildren = Array.from(deeper.querySelectorAll(':scope > td'));
    } else {
      deepChildren = Array.from(deeper.children);
    }
    if (deepChildren.length > 1) {
      console.log('Performed secondary unwrap. New candidate count:', deepChildren.length);
      candidates = deepChildren;
    }
  }

  // Special handling for newsletter containers - look for direct children that are content blocks
  if (candidates.length <= 8 && candidates.length > 0) {
    console.log('Checking for newsletter container in candidates...');
    
    // Check each candidate for newsletter container
    for (let i = 0; i < candidates.length; i++) {
      const candidate = candidates[i];
      console.log(`Candidate ${i}:`, {
        tag: candidate.tagName,
        class: candidate.className,
        hasNewsletterClass: candidate.classList?.contains('newsletter-container'),
        childrenCount: candidate.children?.length || 0
      });
      
      if (candidate.classList?.contains('newsletter-container')) {
        console.log('Found newsletter container directly, children count:', candidate.children.length);
        if (candidate.children.length > 1) {
          candidates = Array.from(candidate.children);
          console.log('Updated candidates to newsletter container children:', candidates.length);
          break;
        }
      }
      
      // Also check if this candidate contains a newsletter container
      const nestedContainer = candidate.querySelector('.newsletter-container');
      if (nestedContainer) {
        console.log('Found nested newsletter container, children count:', nestedContainer.children.length);
        if (nestedContainer.children.length > 1) {
          candidates = Array.from(nestedContainer.children);
          console.log('Updated candidates to nested newsletter container children:', candidates.length);
          break;
        }
      }
    }
  }

  // Detection helpers
  const isButtonLike = (el) => {
    const link = el.querySelector('a');
    if (!link) return false;
    const cls = (link.getAttribute('class') || '').toLowerCase();
    const style = (link.getAttribute('style') || '').toLowerCase();
    const role = (link.getAttribute('role') || '').toLowerCase();
    return (
      role === 'button' ||
      cls.includes('btn') || cls.includes('button') ||
      style.includes('inline-block') || style.includes('padding') || style.includes('background')
    );
  };

  candidates.forEach((element) => {
    const tagName = element.tagName?.toLowerCase() || 'div';
    const innerHTML = element.innerHTML || '';
    const outerHTML = element.outerHTML || innerHTML || '';

    if (element.querySelector('h1, h2, h3, h4, h5, h6')) {
      // Header-like
      const heading = element.querySelector('h1, h2, h3, h4, h5, h6');
      const title = heading?.textContent?.trim() || '';
      const subtitle = (element.textContent || '').replace(title, '').trim();
      blocks.push({
        id: generateBlockId(),
        type: 'header',
        content: outerHTML,
        data: {
          title,
          subtitle,
          background: safeStyle(element, 'backgroundColor', '#c8102e'),
          textColor: safeStyle(element, 'color', '#ffffff')
        },
        editable: true,
        locked: false
      });
    } else if (element.querySelector('img')) {
      const img = element.querySelector('img');
      blocks.push({
        id: generateBlockId(),
        type: 'image',
        content: outerHTML,
        data: {
          src: img?.getAttribute('src') || '',
          alt: img?.getAttribute('alt') || 'Image',
          width: '100%',
          height: 'auto'
        },
        editable: true,
        locked: false
      });
    } else if (tagName === 'hr') {
      blocks.push({
        id: generateBlockId(),
        type: 'divider',
        content: outerHTML,
        data: { style: 'solid', color: '#e5e7eb', margin: '20px 0' },
        editable: true,
        locked: false
      });
    } else if (isButtonLike(element)) {
      const link = element.querySelector('a');
      blocks.push({
        id: generateBlockId(),
        type: 'button',
        content: outerHTML,
        data: {
          text: (link?.textContent || '').trim(),
          url: link?.getAttribute('href') || '#',
          background: safeStyle(link, 'backgroundColor', '#c8102e'),
          color: safeStyle(link, 'color', '#ffffff')
        },
        editable: true,
        locked: false
      });
    } else if (/(unsubscribe|copyright|&copy;|all rights reserved)/i.test(innerHTML)) {
      blocks.push({
        id: generateBlockId(),
        type: 'footer',
        content: outerHTML,
        data: {
          content: innerHTML,
          background: safeStyle(element, 'backgroundColor', '#c8102e'),
          textColor: safeStyle(element, 'color', '#ffffff')
        },
        editable: true,
        locked: false
      });
    } else if (/Image Placeholder/i.test(innerHTML)) {
      // Recognize our image placeholder markup as an image block (no src yet)
      blocks.push({
        id: generateBlockId(),
        type: 'image',
        content: outerHTML,
        data: {
          src: '',
          alt: 'Image',
          width: '100%',
          height: 'auto'
        },
        editable: true,
        locked: false
      });
    } else {
      blocks.push({
        id: generateBlockId(),
        type: 'text',
        content: outerHTML,
        data: {
          content: innerHTML,
          background: safeStyle(element, 'backgroundColor', 'transparent'),
          padding: '15px 12px'
        },
        editable: true,
        locked: false
      });
    }
  });

  return blocks.length > 0 ? blocks : [{
    id: generateBlockId(),
    type: 'text',
    content: htmlContent,
    data: { content: htmlContent },
    editable: true,
    locked: false
  }];
}

function createBlock(type) {
  const id = `block_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  
  const blockDefaults = {
    header: { 
      title: 'Newsletter Title', 
      subtitle: 'Your weekly dose of updates',
      background: '#c8102e',
      textColor: '#ffffff',
      padding: '30px 12px',
      fullWidth: false,
      logo: '',
      logoAlt: '',
      logoUrl: '',
      logoAlignment: 'center',
      logoSize: '250px',
      logoPadding: '10px'
    },
    text: { 
      content: '<p>Click to edit this text...</p>',
      fontSize: '16px',
      color: '#666666',
      lineHeight: '1.6',
      background: 'transparent',
      padding: '15px 50px',
      fullWidth: false
    },
    heading: { 
      content: 'Your Heading Here', 
      level: 2,
      fontSize: '22px',
      color: '#333333',
      fontWeight: '600',
      background: 'transparent',
      padding: '15px 12px',
      fullWidth: false
    },
    image: { 
      src: '', 
      alt: 'Image description', 
      width: '100%',
      height: '200px',
      borderRadius: '8px',
      padding: '20px 12px',
      fullWidth: false
    },
    button: { 
      text: 'Click Here', 
      url: '#', 
      background: '#c8102e',
      color: '#ffffff',
      borderRadius: '25px',
      padding: '12px 25px'
    },
    columns: {
      count: 2,
      gap: '20px',
      padding: '20px 12px',
      fullWidth: false,
      columns: [
        { content: '<p>Column 1 content</p>' },
        { content: '<p>Column 2 content</p>' }
      ]
    },
    tablelist: {
      rowCount: 3,
      gap: '10px',
      padding: '10px 35px',
      maxHeight: '60px',
      col1Width: '50%',
      col2Width: '50%',
      rows: [
        { items: [] },
        { items: [] },
        { items: [] },
        { items: [] },
        { items: [] },
        { items: [] }
      ]
    },
    divider: { style: 'solid', color: '#e5e7eb', margin: '20px 0' },
    footer: {
      content: '<p>Thanks for reading! Forward this to someone who might find it useful.</p>',
      links: [
        { text: 'Unsubscribe', url: '{{unsubscribe_url}}' },
        { text: 'Update preferences', url: '{{preferences_url}}' },
        { text: 'View in browser', url: '{{browser_url}}' }
      ],
      copyright: '2025 Your Company Name. All rights reserved.',
      background: '#c8102e',
      textColor: '#ffffff'
    },
    spacer: { height: '20px' }
  };
  
  return {
    id,
    type,
    content: getBlockHtml(type, blockDefaults[type] || {}),
    data: blockDefaults[type] || {}
  };
}

function getBlockHtml(type, data) {
  const getPadding = (blockData) => {
    if (blockData.fullWidth) return '0';
    return blockData.padding || '15px 12px';
  };
  
  const getBlockStyles = (blockData) => {
    const styles = [];
    if (blockData.margin) styles.push(`margin: ${blockData.margin}`);
    if (blockData.border && blockData.border !== 'none') {
      if (blockData.border === 'custom') {
        const width = blockData.borderWidth || '1px';
        const style = blockData.borderStyle || 'solid';
        const color = blockData.borderColor || '#e5e7eb';
        styles.push(`border: ${width} ${style} ${color}`);
      } else {
        styles.push(`border: ${blockData.border}`);
      }
    }
    return styles.length > 0 ? styles.join('; ') + ';' : '';
  };

  switch (type) {
    case 'header':
      data = data || {};
      let logoHtml = '';
      if (data.logo) {
        const logoImg = `<img src="${data.logo}" alt="${data.logoAlt || 'Logo'}" style="max-width: ${data.logoSize || '150px'}; height: auto; padding: ${data.logoPadding || '10px'}; display: block; margin-left: ${data.logoAlignment === 'left' ? '0' : data.logoAlignment === 'right' ? 'auto' : 'auto'}; margin-right: ${data.logoAlignment === 'left' ? 'auto' : data.logoAlignment === 'right' ? '0' : 'auto'};" />`;
        logoHtml = data.logoUrl ? `<a href="${data.logoUrl}" style="display: block; text-align: ${data.logoAlignment || 'center'}; margin: 0 0 20px 0;">${logoImg}</a>` : `<div style="margin: 0 0 20px 0; text-align: ${data.logoAlignment || 'center'};">${logoImg}</div>`;
      }
      const titleHtml = data.title ? `<h1 style="margin: 0; font-size: 28px; font-weight: 300;">${data.title}</h1>` : '';
      const subtitleHtml = data.subtitle ? `<p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">${data.subtitle}</p>` : '';
      const headerStyles = getBlockStyles(data);
      return `<div class="header-block" style="background: ${data.background || '#c8102e'}; color: ${data.textColor || '#ffffff'}; padding: ${getPadding(data)}; text-align: center; border-radius: 8px 8px 0 0; ${headerStyles}">${logoHtml}${titleHtml}${subtitleHtml}</div>`;
    case 'text':
      data = data || {};
      const textStyles = getBlockStyles(data);
      const textMargin = data.margin || '0';
      return `<div style="margin: ${textMargin}; padding: ${data.padding || '15px 35px'}; background-color: ${data.background || 'transparent'}; font-size: ${data.fontSize || '16px'}; line-height: ${data.lineHeight || '1.6'}; color: ${data.color || '#666666'}; ${textStyles}">${data.content || '<p>Click to edit this text...</p>'}</div>`;
    case 'heading':
      data = data || {};
      const headingStyles = getBlockStyles(data);
      return `<div style="padding: ${data.padding || '15px 35px'}; background-color: ${data.background || 'transparent'}; ${headingStyles}"><h${data.level || 2} style="margin: 0; font-size: ${data.fontSize || '22px'}; font-weight: ${data.fontWeight || '600'}; color: ${data.color || '#333333'};">${data.content || 'Your Heading Here'}</h${data.level || 2}></div>`;
    case 'image':
      data = data || {};
      const borderRadius = data.fullWidth ? '0' : (data.borderRadius || '8px');
      const imgPadding = data.fullWidth ? '0' : (data.padding || '0 35px');
      const imageStyles = getBlockStyles(data);
      return data.src ?
        `<div style="padding: ${imgPadding}; ${imageStyles}"><img src="${data.src}" alt="${data.alt || 'Image'}" style="width: 100%; max-width: 100%; height: auto; border-radius: ${borderRadius}; display: block;" /></div>` :
        `<div style="padding: ${imgPadding}; ${imageStyles}"><div style="width: 100%; height: ${data.height || '200px'}; background: linear-gradient(45deg, #e8f2ff 0%, #f0f8ff 100%); border: 2px dashed #cce7ff; border-radius: ${borderRadius}; display: flex; align-items: center; justify-content: center; color: #667eea; font-size: 14px; cursor: pointer;">Image Placeholder (Click to upload)</div></div>`;
    case 'button':
      const buttonStyles = getBlockStyles(data);
      return `<div style="text-align: center; margin: 20px 0; ${buttonStyles}"><a href="${data.url}" style="display: inline-block; padding: ${data.padding}; background: ${data.background}; color: ${data.color}; text-decoration: none; border-radius: ${data.borderRadius}; font-weight: 600; transition: transform 0.2s ease;">${data.text}</a></div>`;
    case 'columns':
      data = data || {};
      const count = 2; // enforce two columns only
      const gap = data.gap || '20px';
      const cols = Array.isArray(data.columns) ? data.columns : [];
      const getInner = (col) => {
        const items = Array.isArray(col?.items) ? col.items : [];
        return items.length
          ? items.map(it => (it && (it.content || getBlockHtml(it.type || 'text', it.data || {})))).join('')
          : ((col && col.content) || '');
      };
      const c0 = getInner(cols[0] || {});
      const c1 = getInner(cols[1] || {});
      // Derive half-gap (px only). If not px, fallback to 20px total gap.
      let gapPx = 20;
      try {
        const m = String(gap).match(/^(\d+)px$/);
        if (m) gapPx = Math.max(0, parseInt(m[1], 10));
      } catch (e) {}
      const halfGap = Math.round(gapPx / 2);
      const tdLeftStyle = `text-align: center; vertical-align: top; padding-right: ${halfGap}px;`;
      const tdRightStyle = `text-align: center; vertical-align: top; padding-left: ${halfGap}px;`;
      return [
        `<div style="padding: ${getPadding(data)}; text-align: center;">`,
        `<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">`,
        `<tr>`,
        `<td align="center" valign="top" width="50%" style="${tdLeftStyle}">${c0}</td>`,
        `<td align="center" valign="top" width="50%" style="${tdRightStyle}">${c1}</td>`,
        `</tr>`,
        `</table>`,
        `</div>`
      ].join('');
    case 'divider':
      data = data || {};
      return `<hr style="border: none; border-top: 1px ${data.style || 'solid'} ${data.color || '#e5e7eb'}; margin: ${data.margin || '20px 0'};" />`;
    case 'footer':
      const linksArr = Array.isArray(data.links) ? data.links : [];
      const footerLinks = linksArr.map(link => 
        `<a href="${(link && link.url) || '#'}" style="color: ${(data && data.textColor) || '#ffffff'}; text-decoration: none;">${(link && link.text) || ''}</a>`
      ).join(' | ');
      return `<div style="background-color: ${(data && data.background) || '#c8102e'}; color: ${(data && data.textColor) || '#ffffff'}; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;"><div>${(data && data.content) || ''}</div><p style="margin: 5px 0; color: ${(data && data.textColor) || '#ffffff'}; font-size: 14px;">${footerLinks}</p><p style="margin: 5px 0; color: ${(data && data.textColor) || '#ffffff'}; font-size: 14px;">&copy; ${(data && data.copyright) || ''}</p></div>`;
    case 'tablelist':
      data = data || {};
      const rowCount = data.rowCount || 3;
      const rowGap = data.gap || '10px';
      const maxHeight = data.maxHeight || '60px';
      const col1Width = data.col1Width || '50%';
      const col2Width = data.col2Width || '50%';
      const rows = Array.isArray(data.rows) ? data.rows : [];
      const getRowInner = (row) => {
        const items = Array.isArray(row?.items) ? row.items : [];
        return items.length
          ? items.map(it => (it && (it.content || getBlockHtml(it.type || 'text', it.data || {})))).join('')
          : ((row && row.content) || '');
      };
      const tableRows = [];
      for (let i = 0; i < rowCount; i++) {
        const col1Content = getRowInner(rows[i * 2] || {});
        const col2Content = getRowInner(rows[i * 2 + 1] || {});
        tableRows.push(
          `<tr><td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; max-height: ${maxHeight}; width: ${col1Width};">${col1Content || `<p style="margin: 0; font-size: 14px; color: #666;">Cell ${i * 2 + 1}</p>`}</td><td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; max-height: ${maxHeight}; width: ${col2Width};">${col2Content || `<p style="margin: 0; font-size: 14px; color: #666;">Cell ${i * 2 + 2}</p>`}</td></tr>`
        );
      }
      return [
        `<div style="padding: ${getPadding(data)};">`,
        `<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; margin-bottom: ${rowGap};">`,
        tableRows.join(''),
        `</table>`,
        `</div>`
      ].join('');
    case 'spacer':
      return `<div style="height: ${data.height};"></div>`;
    default:
      return `<div style="padding: 10px; background: #f9fafb; border: 1px dashed #d1d5db; text-align: center; color: #6b7280;">Unknown block type: ${type}</div>`;
  }
}

// Block editing functions
// Open block settings modal
function openBlockSettings(blockId) {
  editingBlock.value = blockId;
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (block) {
    // Load current styling values
    blockMargin.value = block.data?.margin || '0';
    blockPadding.value = block.data?.padding || '15px 35px';
    blockBorder.value = block.data?.border || 'none';
    blockBorderColor.value = block.data?.borderColor || '#e5e7eb';
    blockBorderWidth.value = block.data?.borderWidth || '1px';
    blockBorderStyle.value = block.data?.borderStyle || 'solid';
    showBlockSettings.value = true;
  }
}

function saveBlockSettings() {
  const borderValue = blockBorder.value === 'custom' 
    ? `${blockBorderWidth.value} ${blockBorderStyle.value} ${blockBorderColor.value}`
    : blockBorder.value;
  
  updateBlockData(editingBlock.value, {
    margin: blockMargin.value,
    padding: blockPadding.value,
    border: blockBorder.value,
    borderColor: blockBorderColor.value,
    borderWidth: blockBorderWidth.value,
    borderStyle: blockBorderStyle.value,
  });
  showBlockSettings.value = false;
  editingBlock.value = null;
}

function cancelBlockSettings() {
  showBlockSettings.value = false;
  editingBlock.value = null;
}

function editBlock(blockId) {
  editingBlock.value = blockId;
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (block) {
    if (block.type === 'image') {
      // Preload current image and spacing settings
      imageCropSrc.value = block.data?.src || null;
      imageFullWidth.value = block.data?.fullWidth || false;
      showImageUpload.value = true;
    } else if (block.type === 'button') {
      // Pre-populate button editor with current values
      buttonText.value = block.data.text || 'Click Here';
      buttonUrl.value = block.data.url || '#';
      buttonBackground.value = block.data.background || '#667eea';
      showButtonEditor.value = true;
    } else if (block.type === 'columns') {
      // Initialize columns editor state from block
      const data = block.data || { count: 2, gap: '20px', columns: [{ content: '<p>Column 1 content</p>' }, { content: '<p>Column 2 content</p>' }] };
      // Enforce exactly 2 columns in the editor
      columnCount.value = 2;
      columnGap.value = data.gap || '20px';
      const cols = Array.isArray(data.columns) ? data.columns : [];
      const mapped = cols.map((c, i) => (c && c.content) ? c.content : `<p>Column ${i + 1} content</p>`);
      // Ensure exactly two slots
      columnsContent.value = [
        mapped[0] ?? '<p>Column 1 content</p>',
        mapped[1] ?? '<p>Column 2 content</p>',
      ];
      showColumnEditor.value = true;
    } else if (block.type === 'tablelist') {
      // Initialize table list editor state from block
      tableListRowCount.value = block.data?.rowCount || 3;
      tableListGap.value = block.data?.gap || '10px';
      tableListMaxHeight.value = block.data?.maxHeight || '60px';
      tableListCol1Width.value = block.data?.col1Width || '50%';
      tableListCol2Width.value = block.data?.col2Width || '50%';
      showTableListEditor.value = true;
    } else if (block.type === 'text') {
      // Use EmailEditor in a modal for text blocks, with background and padding settings
      textModalContent.value = block.data?.content || block.content || '';
      textBackground.value = block.data?.background || 'transparent';
      textColor.value = block.data?.color || '#666666';
      // Remove textFullWidth since we're removing the option
      showTextEditor.value = true;
    } else if (block.type === 'heading') {
      // Use EmailEditor in a modal for heading blocks
      textModalContent.value = block.data?.content || block.content || '';
      textBackground.value = block.data?.background || 'transparent';
      textColor.value = block.data?.color || '#333333';
      showTextEditor.value = true;
    } else if (block.type === 'header') {
      headerTitle.value = block.data?.title || '';
      headerSubtitle.value = block.data?.subtitle || '';
      headerBackground.value = block.data?.background || '#c8102e';
      headerTextColor.value = block.data?.textColor || '#ffffff';
      headerLogo.value = block.data?.logo || '';
      headerLogoAlt.value = block.data?.logoAlt || '';
      headerLogoUrl.value = block.data?.logoUrl || '';
      headerLogoAlignment.value = block.data?.logoAlignment || 'center';
      headerLogoSize.value = block.data?.logoSize || '150px';
      headerLogoPadding.value = block.data?.logoPadding || '10px';
      showHeaderEditor.value = true;
    } else if (block.type === 'footer') {
      footerContent.value = block.data?.content || 'Thanks for reading! Forward this to someone who might find it useful.';
      footerBackground.value = block.data?.background || '#c8102e';
      footerCopyright.value = block.data?.copyright || '2025 Your Company Name. All rights reserved.';
      footerTextColor.value = block.data?.textColor || '#ffffff';
      showFooterEditor.value = true;
    }
  }
}

function updateBlockData(blockId, newData) {
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (block) {
    block.data = { ...(block.data || {}), ...newData };
    block.content = getBlockHtml(block.type, block.data);
    updateContent();
  }
}

function handleImageUpload(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      imageCropSrc.value = e.target.result;
      // Force VueCropper to refresh with new image
      nextTick(() => {
        if (imageCropper.value) {
          imageCropper.value.replace(e.target.result);
        }
      });
    };
    reader.readAsDataURL(file);
  }
}

async function cropAndSaveImage() {
  try {
    const canvas = imageCropper.value?.getCroppedCanvas({ width: imageMaxWidth.value });
    if (!canvas) return;

    const blob = await new Promise((resolve, reject) => {
      try {
        canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Failed to generate image blob.'))), 'image/jpeg', 0.92);
      } catch (e) {
        reject(e);
      }
    });

    // Upload to campaign-specific or temp folder via controller routing
    const slug = (props.campaignName || 'newsletter')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'newsletter';
    const uniqueName = `${slug}-${Date.now()}`;

    const formData = new FormData();
    formData.append('file', blob, 'image.jpg');
    formData.append('name', uniqueName);
    formData.append('folder', 'images/newsletters');
    if (props.campaignId) {
      formData.append('campaign_id', props.campaignId);
    } else if (props.tempKey) {
      formData.append('temp_key', props.tempKey);
    }

    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
    const uploadUrl = (typeof route === 'function')
      ? route('image.upload')
      : (typeof window !== 'undefined' && typeof window.route === 'function')
        ? window.route('image.upload')
        : '/api/image-upload';
    const resp = await axios.post(uploadUrl, formData, {
      headers: {
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        Accept: 'application/json',
      },
    });

    let url = resp.data.url;
    if (typeof url === 'string' && url.startsWith('/')) {
      // Fallback normalization to absolute URL
      url = `${window.location.origin}${url}`;
    }
    if (editingTableListNested.value) {
      const { blockId, rowIdx, itemIndex } = editingTableListNested.value;
      updateTableListItem(blockId, rowIdx, itemIndex, {
        src: url,
        width: '100%',
        height: 'auto',
        fullWidth: imageFullWidth.value,
      });
    } else if (editingNested.value) {
      const { blockId, colIdx, itemIndex } = editingNested.value;
      updateNestedItem(blockId, colIdx, itemIndex, {
        src: url,
        width: '100%',
        height: 'auto',
        fullWidth: imageFullWidth.value,
      });
    } else {
      updateBlockData(editingBlock.value, {
        src: url,
        width: '100%',
        height: 'auto',
        fullWidth: imageFullWidth.value,
      });
    }

    // Close modal
    showImageUpload.value = false;
    imageCropSrc.value = null;
    editingNested.value = null;
    editingTableListNested.value = null;
    // Reset file input
    if (imageFileInput.value) {
      imageFileInput.value.value = '';
    }
  } catch (e) {
    // No-op; could add user feedback here
  }
}

// Handle header logo upload
  async function handleHeaderLogoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Check file size (100KB limit)
    const maxSize = 100 * 1024; // 100KB in bytes
    if (file.size > maxSize) {
      // File exceeds size limit, show warning to user
      console.warn(`Logo file size (${(file.size / 1024).toFixed(1)}KB) exceeds 100KB limit. Auto-optimizing...`);
      
      // For now, we'll still upload the file but could implement client-side resizing in the future
      // This would require additional image processing libraries
    }

    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('name', `header-logo-${Date.now()}`);
      formData.append('folder', 'images/newsletters/logos');

      const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
      const uploadUrl = (typeof route === 'function')
        ? route('image.upload')
        : (typeof window !== 'undefined' && typeof window.route === 'function')
          ? window.route('image.upload')
          : '/api/image-upload';
      
      const resp = await axios.post(uploadUrl, formData, {
        headers: {
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
          'Accept': 'application/json',
        },
      });

      let url = resp.data.url;
      if (typeof url === 'string' && url.startsWith('/')) {
        url = `${window.location.origin}${url}`;
      }
      
      // Update the header logo in the UI
      headerLogo.value = url;
      
      // Set default alt text to filename if not already set
      if (!headerLogoAlt.value) {
        headerLogoAlt.value = file.name.replace(/\.[^/.]+$/, ''); // Remove extension
      }
      
      // Find and update the header block in the email content
      const headerBlock = emailBlocks.value.find(block => block.type === 'header');
      if (headerBlock) {
        // Update the header block with the new logo URL and alt text
        headerBlock.data = {
          ...headerBlock.data,
          logo: url,
          logoAlt: headerLogoAlt.value,
          logoUrl: headerLogoUrl.value,
          logoAlignment: headerLogoAlignment.value,
          logoSize: headerLogoSize.value,
          logoPadding: headerLogoPadding.value
        };
        
        // Trigger content update to save the changes
        updateContent();
      }
  } catch (e) {
    console.error('Logo upload failed:', e);
  }
}

// Logo Library Management
async function fetchHeaderLogos() {
  try {
    const resp = await axios.get('/api/newsletter/logos');
    headerLogos.value = Array.isArray(resp.data?.logos) ? resp.data.logos : [];
  } catch (e) {
    console.error('Failed to fetch logos', e);
    headerLogos.value = [];
  }
}

function openLogoLibrary() {
  showLogoLibrary.value = true;
  fetchHeaderLogos();
}

function closeLogoLibrary() {
  showLogoLibrary.value = false;
}

function selectLogoFromLibrary(logo) {
  if (!logo) return;
  headerLogo.value = logo.url;
  if (!headerLogoAlt.value) {
    headerLogoAlt.value = (logo.filename || 'Logo').replace(/\.[^/.]+$/, '');
  }
  showLogoLibrary.value = false;
}

async function deleteLogoFromLibrary(logo) {
  if (!logo || !logo.filename) return;
  if (!confirm(`Delete logo "${logo.filename}"?`)) return;
  try {
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
    await axios.delete(`/api/newsletter/logos/${encodeURIComponent(logo.filename)}`, {
      headers: {
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        Accept: 'application/json',
      },
    });
    await fetchHeaderLogos();
  } catch (e) {
    console.error('Failed to delete logo', e);
  }
}

async function renameLogoFromLibrary(logo) {
  if (!logo || !logo.filename) return;
  const newName = prompt('Enter new filename (with or without extension):', logo.filename);
  if (!newName || newName === logo.filename) return;
  try {
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
    await axios.put(`/api/newsletter/logos/${encodeURIComponent(logo.filename)}/rename`, { new_name: newName }, {
      headers: {
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        Accept: 'application/json',
      },
    });
    await fetchHeaderLogos();
  } catch (e) {
    console.error('Failed to rename logo', e);
  }
}

function closeImageEditor() {
  showImageUpload.value = false;
  imageCropSrc.value = null;
  editingBlock.value = null;
  editingNested.value = null;
  editingTableListNested.value = null;
  // Reset file input
  if (imageFileInput.value) {
    imageFileInput.value.value = '';
  }
}

// WYSIWYG Editor for text blocks
function createTextEditor(blockId, initialContent = '') {
  return new Editor({
    extensions: [
      StarterKit,
      Image.configure({
        HTMLAttributes: {
          style: 'max-width: 100%; height: auto;',
        },
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          style: 'color: #3b82f6; text-decoration: underline;',
        },
      }),
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      TextStyle,
      Color,
    ],
    content: initialContent,
    editorProps: {
      attributes: {
        class: 'prose prose-sm focus:outline-none min-h-[100px] p-3',
      },
    },
    onUpdate: ({ editor }) => {
      updateBlockData(blockId, { content: editor.getHTML() });
    },
  });
}

// Get or create an editor instance for a text block
function getTextEditor(block) {
  const id = block.id;
  const desired = (block.data && block.data.content) ? block.data.content : (block.content || '');
  if (!blockEditors.value[id]) {
    blockEditors.value[id] = createTextEditor(id, desired);
  } else {
    // Keep editor content in sync with block data when changed externally
    try {
      if (blockEditors.value[id].getHTML() !== desired) {
        blockEditors.value[id].commands.setContent(desired, false);
      }
    } catch (e) {}
  }
  return blockEditors.value[id];
}

// Execute simple formatting commands on a block's editor
function execTextCmd(blockId, command) {
  const ed = blockEditors.value[blockId];
  if (!ed) return;
  const chain = ed.chain().focus();
  switch (command) {
    case 'bold':
      chain.toggleBold().run();
      break;
    case 'italic':
      chain.toggleItalic().run();
      break;
    case 'bullet':
      chain.toggleBulletList().run();
      break;
    case 'ordered':
      chain.toggleOrderedList().run();
      break;
    case 'paragraph':
      chain.setParagraph().run();
      break;
    case 'h2':
      chain.toggleHeading({ level: 2 }).run();
      break;
    case 'h3':
      chain.toggleHeading({ level: 3 }).run();
      break;
    default:
      break;
  }
}

function setTextLink(blockId) {
  const ed = blockEditors.value[blockId];
  if (!ed) return;
  const url = window.prompt('Enter URL');
  if (url) {
    ed.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }
}

// Toggle HTML view
function toggleHtmlView() {
  emit('toggle-html-view');
}

// Preview overlay controls
function openPreview() {
  showPreview.value = true;
}
function closePreview() {
  showPreview.value = false;
}
function handlePreviewBackdropClick(event) {
  if (event.target === event.currentTarget) {
    closePreview();
  }
}

// Button editor data
const buttonText = ref('');
const buttonUrl = ref('');
const buttonBackground = ref('');

function saveButtonChanges() {
  if (editingTableListNested.value) {
    const { blockId, rowIdx, itemIndex } = editingTableListNested.value;
    updateTableListItem(blockId, rowIdx, itemIndex, {
      text: buttonText.value,
      url: buttonUrl.value,
      background: buttonBackground.value,
    });
    showButtonEditor.value = false;
    editingTableListNested.value = null;
  } else if (editingNested.value) {
    const { blockId, colIdx, itemIndex } = editingNested.value;
    updateNestedItem(blockId, colIdx, itemIndex, {
      text: buttonText.value,
      url: buttonUrl.value,
      background: buttonBackground.value,
    });
    showButtonEditor.value = false;
    editingNested.value = null;
  } else {
    updateBlockData(editingBlock.value, {
      text: buttonText.value,
      url: buttonUrl.value,
      background: buttonBackground.value
    });
    showButtonEditor.value = false;
    editingBlock.value = null;
  }
}

// Columns editor handlers
function adjustColumnsLength(newCount) {
  const n = Math.max(1, Math.min(4, parseInt(newCount || 1, 10)));
  columnCount.value = n;
  if (columnsContent.value.length < n) {
    for (let i = columnsContent.value.length; i < n; i++) {
      columnsContent.value.push(`<p>Column ${i + 1} content</p>`);
    }
  } else if (columnsContent.value.length > n) {
    columnsContent.value = columnsContent.value.slice(0, n);
  }
}

function saveColumnChanges() {
  // Only update spacing (gap). Content is edited directly on canvas.
  updateBlockData(editingBlock.value, {
    gap: columnGap.value,
  });
  showColumnEditor.value = false;
  editingBlock.value = null;
}

function cancelColumnEdit() {
  showColumnEditor.value = false;
  editingBlock.value = null;
}

// Text editor save/cancel handlers
function saveTextChanges() {
  // Save EmailEditor modal content. If text block, also save background and padding.
  if (editingTableListNested.value) {
    const { blockId, rowIdx, itemIndex } = editingTableListNested.value;
    const nested = getTableListItem(blockId, rowIdx, itemIndex);
    const isHeading = nested?.type === 'heading';
    const isText = nested?.type === 'text';
    const payload = {
      content: textModalContent.value,
      background: textBackground.value,
      color: textColor.value,
    };
    if (isText) {
      payload.padding = '15px 35px';
    }
    updateTableListItem(blockId, rowIdx, itemIndex, payload);
    showTextEditor.value = false;
    editingTableListNested.value = null;
  } else if (editingNested.value) {
    const { blockId, colIdx, itemIndex } = editingNested.value;
    const nested = getNestedItem(blockId, colIdx, itemIndex);
    const isHeading = nested?.type === 'heading';
    const isText = nested?.type === 'text';
    const payload = {
      content: textModalContent.value,
      background: textBackground.value,
      color: textColor.value,
    };
    if (isText) {
      payload.padding = '15px 35px';
    }
    updateNestedItem(blockId, colIdx, itemIndex, payload);
    showTextEditor.value = false;
    editingNested.value = null;
  } else {
    const blk = currentEditingBlock.value;
    if (blk && blk.type === 'text') {
      updateBlockData(editingBlock.value, {
        content: textModalContent.value,
        background: textBackground.value,
        color: textColor.value,
        padding: '15px 35px',
      });
    } else if (blk && blk.type === 'heading') {
      updateBlockData(editingBlock.value, {
        content: textModalContent.value,
        background: textBackground.value,
        color: textColor.value,
      });
    } else {
      updateBlockData(editingBlock.value, { content: textModalContent.value });
    }
    showTextEditor.value = false;
    editingBlock.value = null;
  }
}

function cancelTextEdit() {
  showTextEditor.value = false;
  editingBlock.value = null;
  editingNested.value = null;
  editingTableListNested.value = null;
}

// Modal backdrop click handler
function handleModalBackdropClick(event) {
  if (event.target === event.currentTarget) {
    cancelTextEdit();
  }
}

// Add keyboard event listener for ESC key
function handleKeydown(event) {
  if (event.key === 'Escape') {
    if (showTextEditor.value) cancelTextEdit();
    if (showPreview.value) closePreview();
    if (showSourceEditor.value) cancelSourceEdit();
  }
}

// Add event listeners when component mounts
onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
});

// Remove event listeners when component unmounts
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown);
});

// Source editor overlay helpers
function openSourceEditor() {
  sourceContent.value = finalHtmlContent.value;
  showSourceEditor.value = true;
}

function handleSourceBackdropClick(event) {
  if (event.target === event.currentTarget) {
    cancelSourceEdit();
  }
}

// Source editor functions
function toggleSourceEditor() {
  if (showSourceEditor.value) {
    showSourceEditor.value = false;
  } else {
    sourceContent.value = finalHtmlContent.value;
    showSourceEditor.value = true;
  }
}

function applySourceChanges() {
  try {
    const html = (sourceContent.value || '').trim();
    if (!html) {
      // No content provided; just close the editor without changes
      showSourceEditor.value = false;
      return;
    }
    const blocks = parseHtmlIntoBlocks(html);
    if (Array.isArray(blocks) && blocks.length > 0) {
      emailBlocks.value = blocks;
      updateContent();
    }
  } catch (e) {
    // Optionally, show a toast/error. For now, do nothing.
  } finally {
    showSourceEditor.value = false;
  }
}

function cancelSourceEdit() {
  showSourceEditor.value = false;
}

onBeforeUnmount(() => {
  // Remove event listener
  document.removeEventListener('keydown', handleKeydown);
  
  if (textEditor.value) {
    textEditor.value.destroy();
    textEditor.value = null;
  }
  // Destroy all per-block editors
  Object.values(blockEditors.value).forEach((ed) => {
    try { ed.destroy(); } catch (e) {}
  });
  blockEditors.value = {};
});

// Emit initial content so parent has html on first render
onMounted(() => {
  const parsed = safeParseJson(props.modelValue);
  if (applyStructure(parsed)) {
    return;
  }
  // Prefer initialHtml if provided
  if (typeof props.initialHtml === 'string' && props.initialHtml.trim() !== '') {
    try {
      const blocks = parseHtmlIntoBlocks(props.initialHtml);
      if (Array.isArray(blocks) && blocks.length) {
        isApplying.value = true;
        emailBlocks.value = blocks;
        dedupeAndNormalizeFooter();
        isApplying.value = false;
        return;
      }
    } catch (e) {
      // ignore and fall through
    }
  }
  // Fallback: if modelValue is a non-empty string, try to treat it as HTML
  if (typeof props.modelValue === 'string' && props.modelValue.trim() !== '') {
    try {
      const blocks = parseHtmlIntoBlocks(props.modelValue);
      if (Array.isArray(blocks) && blocks.length) {
        isApplying.value = true;
        emailBlocks.value = blocks;
        dedupeAndNormalizeFooter();
        isApplying.value = false;
        return;
      }
    } catch (e) {
      // ignore and fall through to defaults
    }
  }
  // No valid incoming structure; emit defaults
  updateContent();
});

// Keep builder in sync if parent provides a different structure
watch(
  () => props.modelValue,
  (newVal) => {
    const parsed = safeParseJson(newVal);
    if (applyStructure(parsed)) return;
    if (typeof props.initialHtml === 'string' && props.initialHtml.trim() !== '') {
      try {
        const blocks = parseHtmlIntoBlocks(props.initialHtml);
        if (Array.isArray(blocks) && blocks.length) {
          isApplying.value = true;
          emailBlocks.value = blocks;
          dedupeAndNormalizeFooter();
          isApplying.value = false;
          return;
        }
      } catch (e) {
        // ignore
      }
    }
    if (typeof newVal === 'string' && newVal.trim() !== '') {
      try {
        const blocks = parseHtmlIntoBlocks(newVal);
        if (Array.isArray(blocks) && blocks.length) {
          isApplying.value = true;
          emailBlocks.value = blocks;
          dedupeAndNormalizeFooter();
          isApplying.value = false;
        }
      } catch (e) {
        // ignore
      }
    }
  }
);

// React if initialHtml changes
watch(
  () => props.initialHtml,
  (newHtml) => {
    if (typeof newHtml === 'string' && newHtml.trim() !== '') {
      // Guard: if the incoming HTML matches what this builder just emitted,
      // ignore to prevent a parse/apply feedback loop (notably in campaigns page wrapper)
      if (newHtml === finalHtmlContent.value) {
        return;
      }
      try {
        const blocks = parseHtmlIntoBlocks(newHtml);
        if (Array.isArray(blocks) && blocks.length) {
          isApplying.value = true;
          emailBlocks.value = blocks;
          dedupeAndNormalizeFooter();
          isApplying.value = false;
        }
      } catch (e) {
        // ignore
      }
    }
  }
);

// Header editor save/cancel
function saveHeaderChanges() {
  updateBlockData(editingBlock.value, {
    title: headerTitle.value,
    subtitle: headerSubtitle.value,
    background: headerBackground.value,
    textColor: headerTextColor.value,
    logo: headerLogo.value,
    logoAlt: headerLogoAlt.value,
    logoUrl: headerLogoUrl.value,
    logoAlignment: headerLogoAlignment.value,
    logoSize: headerLogoSize.value,
    logoPadding: headerLogoPadding.value,
  });
  showHeaderEditor.value = false;
  editingBlock.value = null;
}

// Footer editor save/cancel
function saveFooterChanges() {
  updateBlockData(editingBlock.value, {
    content: footerContent.value,
    background: footerBackground.value,
    textColor: footerTextColor.value,
    copyright: footerCopyright.value,
  });
  showFooterEditor.value = false;
  editingBlock.value = null;
}

function cancelHeaderEdit() {
  showHeaderEditor.value = false;
  editingBlock.value = null;
}

function cancelFooterEdit() {
  showFooterEditor.value = false;
  editingBlock.value = null;
}

// Block movement functions
function moveBlockUp(blockId) {
  const index = emailBlocks.value.findIndex(b => b.id === blockId);
  if (index > 0) {
    const block = emailBlocks.value.splice(index, 1)[0];
    emailBlocks.value.splice(index - 1, 0, block);
    updateContent();
  }
}

function moveBlockDown(blockId) {
  const index = emailBlocks.value.findIndex(b => b.id === blockId);
  if (index < emailBlocks.value.length - 1) {
    const block = emailBlocks.value.splice(index, 1)[0];
    emailBlocks.value.splice(index + 1, 0, block);
    updateContent();
  }
}
// Selection handler for blocks
function selectBlock(blockId) {
  selectedBlockId.value = blockId;
}

// Duplicate a block by id, inserting the clone after the original
function duplicateBlock(blockId) {
  const index = emailBlocks.value.findIndex(b => b.id === blockId);
  if (index === -1) return;
  const original = emailBlocks.value[index];
  const clone = JSON.parse(JSON.stringify(original));
  clone.id = generateBlockId();
  emailBlocks.value.splice(index + 1, 0, clone);
  updateContent();
}

// Alias for removing a block
function deleteBlock(blockId) {
  removeBlock(blockId);
}

// Drop handler for between blocks
function onDropBetween(event, insertIndex) {
  onDrop(event, insertIndex);
}

// Settings: personalization tokens support
const personalizationTokens = [
  'first_name',
  'last_name',
  'email',
  'organization',
];

function insertPersonalizationToken(token) {
  const blockId = showTokensDropdown.value;
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (!block) return;

  const placeholder = `{{ ${token} }}`;

  if (block.type === 'text') {
    const content = (block.data?.content || '') + placeholder;
    updateBlockData(block.id, { content });
  } else if (block.type === 'heading') {
    const content = (block.data?.content || '') + placeholder;
    updateBlockData(block.id, { content });
  } else {
    // For other blocks, append a small text paragraph below
    const appended = (block.content || '') + `<p>${placeholder}</p>`;
    updateBlockContent(block.id, appended);
  }
}

// Insert token directly into the WYSIWYG editor
function insertTokenIntoEditor(token) {
  const placeholder = `{{ ${token} }}`;
  textModalContent.value += placeholder;
}
</script>

<template>
  <div class="flex h-screen bg-gray-50 dark:bg-gray-900 dark:text-gray-100">
    <!-- Left Sidebar - Blocks & Templates -->
    <div class="w-50 bg-white border-r border-gray-200 flex flex-col dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
      <!-- Sidebar Header -->
      <div class="p-4 border-b border-gray-200 dark:border-gray-600 ">
        <div class="flex items-center gap-2 mb-4">
          <button type="button"
            @click="activePanel = 'blocks'"
            :class="`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              activePanel === 'blocks'
                ? 'bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-100'
                : 'text-gray-600 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-600 hover:text-gray-900 dark:hover:text-gray-100'
            }`"
          >
            <span>Blocks</span>
          </button>
          <button type="button"
            @click="activePanel = 'templates'"
            :class="`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              activePanel === 'templates'
                ? 'bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-100'
                : 'text-gray-600 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-600 hover:text-gray-900 dark:hover:text-gray-100'
            }`"
          >
            <span>Templates</span>
          </button>
        </div>
      </div>

      <!-- Sidebar Content -->
      <div class="flex-1 overflow-y-auto p-4">
        <!-- Blocks Panel -->
        <div v-if="activePanel === 'blocks'" class="space-y-2">
          <div
            v-for="blockType in blockTypes"
            :key="blockType.type"
            :draggable="true"
            @dragstart="onDragStart($event, blockType.type)"
            @dragend="onDragEnd"
            @click="addBlock(blockType.type)"
            class="flex justify-center items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer  transition-colors dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-600"
          >
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ blockType.label }}</span>
          </div>
        </div>

        <!-- Templates Panel -->
        <div v-else-if="activePanel === 'templates'" class="space-y-3">
          <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Load Template</h3>
          <div
            v-for="template in templates"
            :key="template.id"
            @click="loadTemplate(template)"
            class="flex justify-center items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
          >
            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ template.name }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ template.description }}</div>
          </div>
          <div v-if="templates.length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            No templates available
          </div>
        </div>

      </div>
    </div>

    <!-- Main Editor Area -->
    <div class="flex-1 flex flex-col">
      <!-- Top Toolbar -->
      <div class="bg-white/80 backdrop-blur border-b border-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
        <div class="px-6 py-3 flex items-center justify-between">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="text-gray-900 dark:text-gray-100 font-semibold">Email Builder</span>
          </div>
          <div class="flex items-center gap-2">
            <button type="button"
              @click="openSourceEditor"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors">
              <span class="inline-flex items-center gap-2">
                <font-awesome-icon :icon="['fas','code']" class="w-4 h-4" />
                <span>Source</span>
              </span>
            </button>
            <button type="button"
              @click="openPreview"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors">
              <span class="inline-flex items-center gap-2">
                <font-awesome-icon :icon="['fas','eye']" class="w-4 h-4" />
                <span>Preview</span>
              </span>
            </button>
          </div>
        </div>
      </div>
      
      

      <!-- Email Canvas -->
      <div class="flex-1 overflow-auto p-6 bg-gray-50 dark:bg-gray-800">
        <div
          :class="`mx-auto transition-all duration-300 ${
            previewMode === 'mobile' ? 'max-w-sm' : 'max-w-2xl'
          }`"
        >
          <div
            class="bg-white shadow-lg rounded-lg overflow-hidden email-canvas"
            ref="emailCanvasRef"
            @drop="onDrop($event)"
            @dragover="onDragOver($event)"
            @dragleave="clearDragOver"
            style="font-family: 'Source Sans 3', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;"
          >
            <!-- Email Content -->
            <div class="min-h-96">
              <!-- Top Drop Zone (index 0) -->
              <div
                v-if="draggedBlock"
                class="transition-all duration-150 rounded mx-2"
                :class="dragOverIndex === 0 ? 'h-3 bg-blue-400/60 opacity-100' : 'h-2 bg-blue-200 opacity-60'"
                @dragenter="onDragEnter(0)"
                @dragleave="onDragLeave(0)"
                @dragover="onDragOver($event, 0)"
                @drop="onDropAt($event, 0)"
              ></div>
              <div
                v-for="(block, index) in emailBlocks"
                :key="block.id"
                class="group relative"
                :class="{ 'py-4': block.type === 'divider' }"
                @click="selectBlock(block.id)"
              >
                <!-- Block Content (static render; dblclick to edit) -->
                <div
                  v-if="block.type !== 'columns' && block.type !== 'tablelist'"
                  :class="`transition-all duration-200 ${
                    selectedBlockId === block.id
                      ? 'ring-2 ring-blue-500 ring-opacity-50'
                      : 'hover:ring-1 hover:ring-gray-300'
                  }`"
                  class="cursor-pointer"
                  v-html="block.content"
                  @click.capture="handleCanvasClick($event, block)"
                  @dblclick="editBlock(block.id)"
                ></div>

                <!-- Interactive Columns Block Rendering on Canvas -->
                <div
                  v-else-if="block.type === 'columns'"
                  :class="`transition-all duration-200 ${
                    selectedBlockId === block.id
                      ? 'ring-2 ring-blue-500 ring-opacity-50'
                      : 'hover:ring-1 hover:ring-gray-300'
                  }`"
                  class="cursor-pointer"
                  @dblclick="openLastNestedAcross(block)"
                >
                  <div
                    class="w-full"
                    :style="{ padding: (block.data && block.data.fullWidth) ? '0' : (block.data?.padding || '20px 12px') }"
                  >
                    <div class="grid grid-cols-2"
                         :style="{ gap: block.data?.gap || '20px' }">
                      <div
                        v-for="colIdx in 2"
                        :key="`${block.id}-col-${colIdx-1}`"
                        class="border border-gray-200 rounded p-0 min-h-[80px] text-center"
                        :class="{
                          'ring-2 ring-blue-400': canvasColumnHover?.blockId === block.id && canvasColumnHover?.colIdx === (colIdx - 1)
                        }"
                        @dragover="onCanvasColumnDragOver($event, block.id, colIdx - 1)"
                        @dragleave="onCanvasColumnDragLeave(block.id, colIdx - 1)"
                        @drop="onCanvasColumnDrop($event, block.id, colIdx - 1)"
                        @dblclick.stop="openLastNestedOrColumns(block, colIdx - 1)"
                      >
                        <template v-if="columnsItems(block, colIdx - 1).length === 0">
                          <div class="text-xs text-gray-400">Drop blocks here</div>
                        </template>

                        <!-- Top insertion drop zone -->
                        <div
                          v-if="draggedBlock || nestedDragging"
                          class="h-2 my-1 rounded transition-all"
                          :class="nestedDragOver?.blockId === block.id && nestedDragOver?.colIdx === (colIdx - 1) && nestedDragOver?.index === 0 ? 'bg-blue-400' : 'bg-transparent'"
                          @dragover="onNestedDragOver($event, block.id, colIdx - 1, 0)"
                          @drop="onNestedDrop($event, block.id, colIdx - 1, 0)"
                        ></div>

                        <!-- Render nested items with controls -->
                        <div v-for="(it, idx) in columnsItems(block, colIdx - 1)" :key="it.id" class="group relative text-center">
                          <!-- Item content (dblclick to edit) -->
                          <div class="inline-block max-w-full text-left"
                               draggable="true"
                               @dragstart="onNestedDragStart($event, block.id, colIdx - 1, idx)"
                               @dragend="onNestedDragEnd"
                               @dblclick.stop="openNestedEditor(block.id, colIdx - 1, idx)"
                          >
                            <div v-html="it.content"></div>
                          </div>

                          <!-- Item toolbar -->
                          <div class="absolute top-1 right-1 flex gap-1 bg-white/90 dark:bg-gray-800/90 shadow rounded px-1 py-0.5 opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto">
                            <button type="button" @click.stop="removeNestedItem(block.id, colIdx - 1, idx)" class="p-0.5 text-[10px] text-red-600 hover:bg-red-50 rounded">Delete</button>
                          </div>

                          <!-- Drop zone after item (for insertion/reorder) -->
                          <div
                            v-if="draggedBlock || nestedDragging"
                            class="h-2 my-1 rounded transition-all"
                            :class="nestedDragOver?.blockId === block.id && nestedDragOver?.colIdx === (colIdx - 1) && nestedDragOver?.index === (idx + 1) ? 'bg-blue-400' : 'bg-transparent'"
                            @dragover="onNestedDragOver($event, block.id, colIdx - 1, idx + 1)"
                            @drop="onNestedDrop($event, block.id, colIdx - 1, idx + 1)"
                          ></div>
                        </div>

                        <!-- Final drop zone at end -->
                        <div
                          v-if="draggedBlock || nestedDragging"
                          class="h-2 my-1 rounded transition-all"
                          :class="nestedDragOver?.blockId === block.id && nestedDragOver?.colIdx === (colIdx - 1) && nestedDragOver?.index === columnsItems(block, colIdx - 1).length ? 'bg-blue-400' : 'bg-transparent'"
                          @dragover="onNestedDragOver($event, block.id, colIdx - 1, columnsItems(block, colIdx - 1).length)"
                          @drop="onNestedDrop($event, block.id, colIdx - 1, columnsItems(block, colIdx - 1).length)"
                        ></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Interactive Table List Block Rendering on Canvas -->
                <div
                  v-else-if="block.type === 'tablelist'"
                  :class="`transition-all duration-200 ${
                    selectedBlockId === block.id
                      ? 'ring-2 ring-blue-500 ring-opacity-50'
                      : 'hover:ring-1 hover:ring-gray-300'
                  }`"
                  class="cursor-pointer"
                  @dblclick="openLastTableListItem(block)"
                >
                  <div
                    class="w-full"
                    :style="{ padding: block.data?.padding || '10px 35px' }"
                  >
                    <table class="w-full border-collapse" :style="{ marginBottom: block.data?.gap || '10px' }">
                      <tbody>
                        <tr
                          v-for="rowIdx in (block.data?.rowCount || 3)"
                          :key="`${block.id}-row-${rowIdx-1}`"
                        >
                          <!-- Column 1 (Cell index: rowIdx * 2 - 2) -->
                          <td
                            class="border-b border-gray-200 align-middle p-2"
                            :style="{ 
                              maxHeight: block.data?.maxHeight || '60px', 
                              overflow: 'hidden',
                              width: block.data?.col1Width || '50%'
                            }"
                            :class="{
                              'ring-2 ring-blue-400': canvasTableListHover?.blockId === block.id && canvasTableListHover?.rowIdx === (rowIdx * 2 - 2)
                            }"
                            @dragover="onCanvasTableListDragOver($event, block.id, rowIdx * 2 - 2)"
                            @drop="onCanvasTableListDrop($event, block.id, rowIdx * 2 - 2)"
                            @dblclick.stop="openTableListEditor(block.id)"
                          >
                            <template v-if="tableListItems(block, rowIdx * 2 - 2).length === 0">
                              <div class="text-xs text-gray-400">Cell {{ rowIdx * 2 - 1 }}</div>
                            </template>

                            <!-- Render nested items with controls -->
                            <div v-for="(it, idx) in tableListItems(block, rowIdx * 2 - 2)" :key="it.id" class="group relative">
                              <!-- Item content (dblclick to edit) -->
                              <div class="max-w-full"
                                   draggable="true"
                                   @dragstart="onTableListNestedDragStart($event, block.id, rowIdx * 2 - 2, idx)"
                                   @dragend="onTableListNestedDragEnd"
                                   @dblclick.stop="openTableListNestedEditor(block.id, rowIdx * 2 - 2, idx)"
                              >
                                <div v-html="it.content"></div>
                              </div>

                              <!-- Item toolbar -->
                              <div class="absolute top-1 right-1 flex gap-1 bg-white/90 dark:bg-gray-800/90 shadow rounded px-1 py-0.5 opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10">
                                <button type="button" @click.stop="removeTableListItem(block.id, rowIdx * 2 - 2, idx)" class="p-0.5 text-[10px] text-red-600 hover:bg-red-50 rounded">Delete</button>
                              </div>
                            </div>
                          </td>

                          <!-- Column 2 (Cell index: rowIdx * 2 - 1) -->
                          <td
                            class="border-b border-gray-200 align-middle p-2"
                            :style="{ 
                              maxHeight: block.data?.maxHeight || '60px', 
                              overflow: 'hidden',
                              width: block.data?.col2Width || '50%'
                            }"
                            :class="{
                              'ring-2 ring-blue-400': canvasTableListHover?.blockId === block.id && canvasTableListHover?.rowIdx === (rowIdx * 2 - 1)
                            }"
                            @dragover="onCanvasTableListDragOver($event, block.id, rowIdx * 2 - 1)"
                            @drop="onCanvasTableListDrop($event, block.id, rowIdx * 2 - 1)"
                            @dblclick.stop="openTableListEditor(block.id)"
                          >
                            <template v-if="tableListItems(block, rowIdx * 2 - 1).length === 0">
                              <div class="text-xs text-gray-400">Cell {{ rowIdx * 2 }}</div>
                            </template>

                            <!-- Render nested items with controls -->
                            <div v-for="(it, idx) in tableListItems(block, rowIdx * 2 - 1)" :key="it.id" class="group relative">
                              <!-- Item content (dblclick to edit) -->
                              <div class="max-w-full"
                                   draggable="true"
                                   @dragstart="onTableListNestedDragStart($event, block.id, rowIdx * 2 - 1, idx)"
                                   @dragend="onTableListNestedDragEnd"
                                   @dblclick.stop="openTableListNestedEditor(block.id, rowIdx * 2 - 1, idx)"
                              >
                                <div v-html="it.content"></div>
                              </div>

                              <!-- Item toolbar -->
                              <div class="absolute top-1 right-1 flex gap-1 bg-white/90 dark:bg-gray-800/90 shadow rounded px-1 py-0.5 opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10">
                                <button type="button" @click.stop="removeTableListItem(block.id, rowIdx * 2 - 1, idx)" class="p-0.5 text-[10px] text-red-600 hover:bg-red-50 rounded">Delete</button>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                
                <!-- Block Controls -->
                <div
                  class="absolute top-2 right-2 flex gap-1 bg-white shadow-lg rounded-md p-1 z-10 opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto transition-opacity"
                >
                  <button type="button"
                    @click.stop="openBlockSettings(block.id)"
                    class="p-1 text-xs text-purple-600 hover:bg-purple-50 rounded"
                    title="Block Settings (Margin, Padding, Border)"
                  >
                    <font-awesome-icon :icon="['fas', 'gear']" class="w-4 h-4" />
                  </button>
                  <button type="button"
                    @click.stop="editBlock(block.id)"
                    class="p-1 text-xs text-blue-600 hover:bg-blue-50 rounded"
                    title="Edit (Double-click block)"
                  >
                    <font-awesome-icon :icon="['fas', 'pen']" class="w-4 h-4" />
                  </button>
                  <button type="button"
                    @click.stop="duplicateBlock(block.id)"
                    class="p-1 text-xs text-green-600 hover:bg-green-50 rounded"
                    title="Duplicate"
                  >
                    <font-awesome-icon :icon="['fas', 'copy']" class="w-4 h-4" />
                  </button>
                  <button type="button"
                    @click.stop="moveBlockUp(block.id)"
                    class="p-1 text-xs text-gray-600 hover:bg-gray-50 rounded"
                    title="Move Up"
                  >
                    <font-awesome-icon :icon="['fas', 'arrow-up']" class="w-4 h-4" />
                  </button>
                  <button type="button"
                    @click.stop="moveBlockDown(block.id)"
                    class="p-1 text-xs text-gray-600 hover:bg-gray-50 rounded"
                    title="Move Down"
                  >
                    <font-awesome-icon :icon="['fas', 'arrow-down']" class="w-4 h-4" />
                  </button>
                  <button type="button"
                    @click.stop="deleteBlock(block.id)"
                    class="p-1 text-xs text-red-600 hover:bg-red-50 rounded"
                    title="Delete"
                  >
                    <font-awesome-icon :icon="['fas', 'trash']" class="w-4 h-4" />
                  </button>
                </div>
                
                <!-- Between Drop Zone (after each block) -->
                <div
                  v-if="draggedBlock"
                  class="transition-all duration-150 rounded mx-2"
                  :class="dragOverIndex === (index + 1) ? 'h-3 bg-blue-400/60 opacity-100' : 'h-2 bg-blue-200 opacity-60'"
                  @dragenter="onDragEnter(index + 1)"
                  @dragleave="onDragLeave(index + 1)"
                  @dragover="onDragOver($event, index + 1)"
                  @drop="onDropAt($event, index + 1)"
                ></div>
              </div>

              <!-- Bottom Drop Zone (after last block) -->
              <div
                v-if="draggedBlock"
                class="transition-all duration-150 rounded mx-2"
                :class="dragOverIndex === emailBlocks.length ? 'h-3 bg-blue-400/60 opacity-100' : 'h-2 bg-blue-200 opacity-60'"
                @dragenter="onDragEnter(emailBlocks.length)"
                @dragleave="onDragLeave(emailBlocks.length)"
                @dragover="onDragOver($event, emailBlocks.length)"
                @drop="onDropAt($event, emailBlocks.length)"
              ></div>
            
              <!-- Empty State -->
              <div
                v-if="emailBlocks.length === 0"
                class="flex items-center justify-center h-96 text-gray-500"
              >
                <div class="text-center">
                  <div class="text-4xl mb-4">
                    <font-awesome-icon :icon="['fas','inbox']" class="w-10 h-10 text-gray-400" />
                  </div>
                  <p class="text-lg font-medium mb-2">Start building your email</p>
                  <p class="text-sm">Drag blocks from the sidebar or click to add them</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Overlay -->
    <div v-if="showPreview" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4" @click="handlePreviewBackdropClick" @keydown.esc="closePreview">
      <div class="bg-white text-gray-700 rounded-lg shadow-xl max-w-3xl w-full max-h-full overflow-auto" @click.stop>
        <div class="flex items-center justify-between p-3 border-b">
          <div class="text-sm font-medium">Preview</div>
        </div>
        <div class="p-4">
          <div class="email-preview" v-html="previewInnerHtml" style="font-family: 'Source Sans 3', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;"></div>
        </div>
      </div>
    </div>

    <!-- Source Editor Overlay -->
    <div v-if="showSourceEditor" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4" @click="handleSourceBackdropClick" @keydown.esc="cancelSourceEdit">
      <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-full overflow-auto" @click.stop>
        <div class="flex items-center justify-between p-3 border-b">
          <div class="text-sm font-medium">HTML Source</div>
        </div>
        <div class="p-4">
          <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-white">HTML Source Code</label>
          <textarea
            v-model="sourceContent"
            class="w-full h-[60vh] font-mono text-sm border border-gray-300 dark:bg-gray-700 rounded p-4 resize-none"
            placeholder="Edit the HTML source code directly..."
          ></textarea>
          <div class="mt-4 flex gap-2 justify-end">
            <button type="button" @click="applySourceChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Apply Changes</button>
            <button type="button" @click="cancelSourceEdit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Block Editors -->
    <!-- Image Upload / Crop Modal -->
    <div v-if="showImageUpload" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-[44rem] max-w-full">
        <h3 class="text-lg font-medium mb-4">Upload & Crop Image</h3>
        <div class="space-y-3">
          <input
            ref="imageFileInput"
            type="file"
            accept="image/*"
            @change="handleImageUpload"
            class="w-full p-2 border border-gray-300 rounded"
          />
          <div v-if="imageCropSrc" class="border border-gray-200 rounded overflow-hidden">
            <VueCropper
              ref="imageCropper"
              :src="imageCropSrc"
              :view-mode="2"
              :background="false"
              :auto-crop-area="1"
              style="max-height: 420px; width: 100%;"
            />
          </div>
          <!-- Spacing settings removed: margin and padding fields no longer displayed -->
          <div>
            <label class="flex items-center">
              <input type="checkbox" v-model="imageFullWidth" class="mr-2" />
              <span class="text-sm text-gray-700">Full width (no padding)</span>
            </label>
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button"
            @click="closeImageEditor"
            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
          >
            Cancel
          </button>
          <button type="button"
            @click="cropAndSaveImage"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Crop & Save
          </button>
        </div>
      </div>
    </div>
    
    <!-- Button Editor Modal -->
    <div v-if="showButtonEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-medium mb-4">Edit Button</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Button Text</label>
            <input
              v-model="buttonText"
              type="text"
              class="w-full p-2 border border-gray-300 rounded"
              placeholder="Click Here"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Link URL</label>
            <input
              v-model="buttonUrl"
              type="url"
              class="w-full p-2 border border-gray-300 rounded"
              placeholder="https://example.com"
            />
          </div>
          <div>
            <ColorPicker v-model="buttonBackground" :showAlpha="true" label="Background Color" />
          </div>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="button"
            @click="saveButtonChanges"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Save
          </button>
          <button type="button"
            @click="showButtonEditor = false"
            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Block Settings Modal -->
    <div v-if="showBlockSettings" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-[32rem] max-w-full">
        <h3 class="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Block Settings</h3>
        <div class="space-y-4">
          <!-- Margin -->
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Margin</label>
            <input
              v-model="blockMargin"
              type="text"
              class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded"
              placeholder="e.g., 0, 10px, 10px 20px"
            />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Space outside the block (top right bottom left)</p>
          </div>

          <!-- Padding -->
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Padding</label>
            <input
              v-model="blockPadding"
              type="text"
              class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded"
              placeholder="e.g., 15px 35px"
            />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Space inside the block (top right bottom left)</p>
          </div>

          <!-- Border Type -->
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Border</label>
            <select
              v-model="blockBorder"
              class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded"
            >
              <option value="none">None</option>
              <option value="custom">Custom</option>
              <option value="1px solid #e5e7eb">1px Solid Gray</option>
              <option value="2px solid #e5e7eb">2px Solid Gray</option>
              <option value="1px dashed #e5e7eb">1px Dashed Gray</option>
              <option value="1px solid #000000">1px Solid Black</option>
            </select>
          </div>

          <!-- Custom Border Settings (shown when 'custom' is selected) -->
          <div v-if="blockBorder === 'custom'" class="space-y-3 pl-4 border-l-2 border-purple-300">
            <div>
              <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Border Width</label>
              <input
                v-model="blockBorderWidth"
                type="text"
                class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded"
                placeholder="e.g., 1px, 2px"
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Border Style</label>
              <select
                v-model="blockBorderStyle"
                class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded"
              >
                <option value="solid">Solid</option>
                <option value="dashed">Dashed</option>
                <option value="dotted">Dotted</option>
                <option value="double">Double</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Border Color</label>
              <div class="flex gap-3">
                <div class="flex-shrink-0">
                  <ColorPicker v-model="blockBorderColor" :showAlpha="false" />
                </div>
                <div class="flex-1">
                  <input
                    v-model="blockBorderColor"
                    type="text"
                    class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded font-mono text-sm"
                    placeholder="#e5e7eb"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Preview -->
          <div class="border-t pt-4">
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Preview</label>
            <div 
              class="bg-gray-50 dark:bg-gray-900 p-4 rounded"
              :style="{
                margin: blockMargin,
                padding: blockPadding,
                border: blockBorder === 'custom' 
                  ? `${blockBorderWidth} ${blockBorderStyle} ${blockBorderColor}`
                  : (blockBorder !== 'none' ? blockBorder : 'none')
              }"
            >
              <div class="text-sm text-gray-600 dark:text-gray-400">Sample block content</div>
            </div>
          </div>
        </div>

        <div class="flex gap-2 mt-6 justify-end">
          <button type="button"
            @click="cancelBlockSettings"
            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-100 rounded hover:bg-gray-400"
          >
            Cancel
          </button>
          <button type="button"
            @click="saveBlockSettings"
            class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700"
          >
            Apply Settings
          </button>
        </div>
      </div>
    </div>

    <!-- Text/Heading Editor Modal -->
    <div v-if="showTextEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click="handleModalBackdropClick" @keydown.esc="cancelTextEdit">
      <div class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-lg p-6 w-[64rem] max-w-full max-h-full overflow-y-auto" @click.stop>
        <h3 class="text-lg font-medium mb-4">Edit Content</h3>
        <!-- Settings for Text block only -->
        <div v-if="currentEditingBlock && currentEditingBlock.type === 'text'" class="space-y-3 mb-3">
          <div>
            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2">Background Color</label>
            <div class="flex gap-3">
              <div class="flex-shrink-0">
                <ColorPicker v-model="textBackground" :showAlpha="true" />
              </div>
              <div class="flex-1">
                <input 
                  type="text" 
                  v-model="textBackground" 
                  class="w-full border dark:bg-gray-800 rounded px-3 py-2 font-mono text-sm" 
                  placeholder="#ffffff or transparent"
                  pattern="^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|transparent|rgba?\(.+\)|hsla?\(.+\))$"
                  title="Enter CSS color value"
                />
              </div>
            </div>
          </div>
         
          <!-- Personalization Tokens -->
          <div class="border-t pt-3">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">Insert Personalization Tokens:</div>
            <div class="flex flex-wrap gap-2">
              <button type="button"
                v-for="token in personalizationTokens"
                :key="token"
                @click="insertTokenIntoEditor(token)"
                class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 transition-colors"
              >
                {{ token.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
              </button>
            </div>
          </div>
        </div>
        <!-- Settings for Heading block -->
        <div v-if="currentEditingBlock && currentEditingBlock.type === 'heading'" class="space-y-3 mb-3">
          <div>
            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2">Background Color</label>
            <div class="flex gap-3">
              <div class="flex-shrink-0">
                <ColorPicker v-model="textBackground" :showAlpha="true" />
              </div>
              <div class="flex-1">
                <input 
                  type="text" 
                  v-model="textBackground" 
                  class="w-full border rounded px-3 py-2 font-mono text-sm" 
                  placeholder="#ffffff or transparent"
                  pattern="^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|transparent|rgba?\(.+\)|hsla?\(.+\))$"
                  title="Enter CSS color value"
                />
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm text-gray-600 dark:text-gray-400 mb-2">Text Color</label>
            <div class="flex gap-3">
              <div class="flex-shrink-0">
                <ColorPicker v-model="textColor" :showAlpha="true" />
              </div>
              <div class="flex-1">
                <input 
                  type="text" 
                  v-model="textColor" 
                  class="w-full border rounded px-3 py-2 font-mono text-sm" 
                  placeholder="#333333"
                  pattern="^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|transparent|rgba?\(.+\)|hsla?\(.+\))$"
                  title="Enter CSS color value"
                />
              </div>
            </div>
          </div>
        </div>
        <div class="border border-gray-300 rounded p-2 bg-white min-h-[200px] max-h-[400px] overflow-y-auto">
          <EmailEditor v-model="textModalContent" label="" :campaign-id="campaignId" :temp-key="tempKey" />
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button"
            @click="cancelTextEdit"
            class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-400"
          >
            Cancel
          </button>
          <button type="button"
            @click="saveTextChanges"
            class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded hover:bg-blue-700"
          >
            Save
          </button>
        </div>
      </div>
    </div>

    <!-- Columns Editor Modal -->
    <div v-if="showColumnEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-[40rem] max-w-full">
        <h3 class="text-lg font-medium mb-4">Edit Columns</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Column Gap (e.g., 20px)</label>
            <input type="text" v-model="columnGap" class="dark:bg-gray-800 w-full p-2 border border-gray-300 rounded" />
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Tip: Drag blocks from the left panel directly into each column on the email canvas. Modal only controls spacing.</div>
        </div>
        <div class="flex gap-2 mt-5 justify-end">
          <button type="button" @click="cancelColumnEdit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveColumnChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
      </div>
    </div>

    <!-- Table List Editor Modal -->
    <div v-if="showTableListEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-[40rem] max-w-full">
        <h3 class="text-lg font-medium mb-4 dark:text-gray-100">Edit Table List</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300">Number of Rows</label>
            <input type="number" v-model.number="tableListRowCount" min="1" max="10" class="dark:bg-gray-700 dark:text-gray-100 w-full p-2 border border-gray-300 dark:border-gray-600 rounded" />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Each row contains 2 columns</p>
          </div>
          
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium mb-1 dark:text-gray-300">Column 1 Width</label>
              <input type="text" v-model="tableListCol1Width" class="dark:bg-gray-700 dark:text-gray-100 w-full p-2 border border-gray-300 dark:border-gray-600 rounded" placeholder="50%" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1 dark:text-gray-300">Column 2 Width</label>
              <input type="text" v-model="tableListCol2Width" class="dark:bg-gray-700 dark:text-gray-100 w-full p-2 border border-gray-300 dark:border-gray-600 rounded" placeholder="50%" />
            </div>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">Use percentages (e.g., 30%, 70%) or pixels (e.g., 200px, 400px)</p>
          
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300">Row Gap (e.g., 10px)</label>
            <input type="text" v-model="tableListGap" class="dark:bg-gray-700 dark:text-gray-100 w-full p-2 border border-gray-300 dark:border-gray-600 rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300">Max Cell Height (e.g., 60px)</label>
            <input type="text" v-model="tableListMaxHeight" class="dark:bg-gray-700 dark:text-gray-100 w-full p-2 border border-gray-300 dark:border-gray-600 rounded" />
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum height for each cell to keep content compact</p>
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 border-t pt-3">
            <strong>Tip:</strong> Drag blocks from the left panel directly into each cell on the email canvas. This creates a compact, 2-column table layout with controlled height and custom widths.
          </div>
        </div>
        <div class="flex gap-2 mt-5 justify-end">
          <button type="button" @click="cancelTableListEdit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveTableListChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
      </div>
    </div>

    <!-- Header Editor Modal -->
    <div v-if="showHeaderEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-100 rounded-lg p-6 w-[40rem] max-w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-medium mb-4">Edit Header</h3>
        <div class="space-y-4">
          <!-- Colors first -->
          <div class="flex gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Background</label>
              <div class="flex items-center gap-3">
                <ColorPicker v-model="headerBackground" :showAlpha="true" />
                <input v-model="headerBackground" type="text" class="flex-1 p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="#c8102e or any CSS background" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Text Color</label>
              <div class="flex items-center gap-3">
                <ColorPicker v-model="headerTextColor" :showAlpha="false" />
                <input v-model="headerTextColor" type="text" class="flex-1 p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="#ffffff or any CSS color" />
              </div>
            </div>
          </div>
          <!-- Logo Upload Section -->
          <div class="border-b pb-4">
            <label class="block text-sm font-medium mb-2">Logo</label>
            <div class="space-y-3">
              <input
                type="file"
                accept="image/*"
                @change="handleHeaderLogoUpload"
                class="w-full p-2 border border-gray-300 rounded"
              />
              <div>
                <button type="button" @click="openLogoLibrary" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded border">Browse Library</button>
              </div>
              <div class="flex items-center gap-2">
                <input 
                  v-model="headerLogo" 
                  type="text" 
                  class="flex-1 p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm" 
                  placeholder="Logo URL or upload an image" 
                />
                <button v-if="headerLogo" type="button" @click="headerLogo = ''" class="text-red-600 hover:text-red-800 text-sm">Clear</button>
              </div>
              <div v-if="headerLogo" class="flex items-center gap-3">
                <img :src="headerLogo" :alt="headerLogoAlt || 'Logo preview'" class="w-16 h-16 object-contain border rounded" />
                <button type="button" @click="headerLogo = ''" class="text-red-600 hover:text-red-800">Remove</button>
              </div>
              <div>
                <label class="block text-xs dark:text-gray-300 text-gray-600 mb-1">Logo Alt Text</label>
                <input v-model="headerLogoAlt" type="text" class="w-full p-1 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm" placeholder="Logo description" />
              </div>
              <div>
                <label class="block text-xs dark:text-gray-300 text-gray-600 mb-1">Logo Click URL</label>
                <input v-model="headerLogoUrl" type="text" class="w-full p-1 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm" placeholder="https://example.com" />
              </div>
              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs dark:text-gray-300 text-gray-600 mb-1">Alignment</label>
                  <select v-model="headerLogoAlignment" class="w-full p-1 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm">
                    <option value="left">Left</option>
                    <option value="center">Center</option>
                    <option value="right">Right</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs dark:text-gray-300 text-gray-600 mb-1">Size</label>
                  <input v-model="headerLogoSize" type="text" class="w-full p-1 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm" placeholder="150px" />
                </div>
                <div>
                  <label class="block text-xs dark:text-gray-300 text-gray-600 mb-1">Padding</label>
                  <input v-model="headerLogoPadding" type="text" class="w-full p-1 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm" placeholder="10px" />
                </div>
              </div>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Title</label>
            <input v-model="headerTitle" type="text" class="w-full p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="Newsletter Title (optional)" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Subtitle</label>
            <input v-model="headerSubtitle" type="text" class="w-full p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="Your weekly dose of updates (optional)" />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button" @click="cancelHeaderEdit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveHeaderChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
      </div>
    </div>

    <!-- Logo Library Modal -->
    <div v-if="showLogoLibrary" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click="closeLogoLibrary">
      <div class="bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 rounded-lg shadow-xl w-[64rem] max-w-full max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h3 class="text-lg font-medium">Logo Library</h3>
          <div class="flex items-center gap-2">
            <input type="file" accept="image/*" @change="handleLogoLibraryUpload" class="text-sm" />
            <button type="button" @click="fetchHeaderLogos" class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded border dark:border-gray-600">Refresh</button>
            <button type="button" @click="closeLogoLibrary" class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded border dark:border-gray-600">Close</button>
          </div>
        </div>
        <div class="p-4">
          <div v-if="headerLogos.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No logos found. Upload a logo to get started.</div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <div v-for="logo in headerLogos" :key="logo.filename" class="border dark:border-gray-700 rounded p-2 flex flex-col">
              <div class="aspect-[4/3] bg-gray-50 dark:bg-gray-900 border dark:border-gray-700 rounded flex items-center justify-center overflow-hidden">
                <img :src="logo.url" :alt="logo.filename" class="object-contain max-h-40" />
              </div>
              <div class="mt-2 text-xs break-all">{{ logo.filename }}</div>
              <div class="mt-2 flex gap-2 flex-wrap">
                <button type="button" @click="selectLogoFromLibrary(logo)" class="px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">Select</button>
                <button type="button" @click="renameLogoFromLibrary(logo)" class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded border dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600">Rename</button>
                <button type="button" @click="deleteLogoFromLibrary(logo)" class="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Editor Modal -->
    <div v-if="showFooterEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-100 rounded-lg p-6 w-[32rem] max-w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-medium mb-4">Edit Footer</h3>
        <div class="space-y-4">
          <!-- Colors first -->
          <div class="flex gap-4">
            <div>
              <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Background</label>
              <div class="flex items-center gap-3">
                <ColorPicker v-model="footerBackground" :showAlpha="true" />
                <input v-model="footerBackground" type="text" class="flex-1 p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="#c8102e or any CSS background" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Text Color</label>
              <div class="flex items-center gap-3">
                <ColorPicker v-model="footerTextColor" :showAlpha="false" />
                <input v-model="footerTextColor" type="text" class="flex-1 p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="#ffffff or any CSS color" />
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Content</label>
            <textarea v-model="footerContent" rows="3" class="w-full p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Copyright</label>
            <input v-model="footerCopyright" type="text" class="w-full p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button" @click="cancelFooterEdit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveFooterChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
      </div>
    </div>
  </div>

  

</template>
<style>
.ProseMirror {
  outline: none;
}

/* Ensure paragraphs have an extra row of space in both the canvas and preview */
.email-canvas p,
.email-preview p {
  margin-top: 0.75em;
  margin-bottom: 0.75em;
}

/* Disable clicks on header logo links inside the editing canvas to prevent misclicks */
.email-canvas .header-block a {
  pointer-events: none;
  cursor: default;
}

/* Drop cap styling (only when explicitly applied via class) */
.email-canvas p.has-dropcap:first-letter,
.email-preview p.has-dropcap:first-letter {
  float: left;
  font-size: 3.5em;
  line-height: 0.8;
  margin: 0.1em 0.2em 0 0;
  color: #333;
  font-weight: bold;
  text-transform: uppercase;
}

.email-canvas p.has-dropcap,
.email-preview p.has-dropcap {
  overflow: hidden; /* Contains the floated drop cap */
}

@media screen and (max-width: 600px) {
  .email-canvas p.has-dropcap:first-letter,
  .email-preview p.has-dropcap:first-letter {
    font-size: 2.5em;
    line-height: 1;
  }
}


/* Optional: reduce margin for consecutive paragraphs inside headings or footers if needed */
.email-canvas h1 + p,
.email-canvas h2 + p,
.email-canvas h3 + p,
.email-preview h1 + p,
.email-preview h2 + p,
.email-preview h3 + p {
  margin-top: 0.5em;
}

.ProseMirror table {
  border-collapse: collapse;
  margin: 0;
  overflow: hidden;
  table-layout: fixed;
  width: 100%;
}

.ProseMirror table td,
.ProseMirror table th {
  border: 1px solid #ced4da;
  box-sizing: border-box;
  min-width: 1em;
  padding: 3px 5px;
  position: relative;
  vertical-align: top;
}

.ProseMirror table th {
  background-color: #f1f3f4;
  font-weight: bold;
  text-align: left;
}

.ProseMirror table .selectedCell:after {
  background: rgba(200, 200, 255, 0.4);
  content: "";
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  pointer-events: none;
  position: absolute;
  z-index: 2;
}
</style>
