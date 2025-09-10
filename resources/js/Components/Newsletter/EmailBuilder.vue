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
  isApplying.value = false;
  return true;
}

const emit = defineEmits(['update:modelValue', 'update:html-content', 'toggle-html-view']);

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
const editingBlock = ref(null);
const showImageUpload = ref(false);
const showButtonEditor = ref(false);
const showColumnEditor = ref(false);
const showTextEditor = ref(false);
const textEditor = ref(null);
// Image cropper state
const imageCropSrc = ref(null);
const imageCropper = ref(null);
const imageMargin = ref('20px 0');
const imagePadding = ref('0');
const imageFullWidth = ref(false);
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
const headerLogoAlignment = ref('center');
const headerLogoSize = ref('150px');
const headerLogoPadding = ref('10px');
const headerLogoMargin = ref('0 0 20px 0');

const showFooterEditor = ref(false);
// Currently editing block (computed helper)
const currentEditingBlock = computed(() => emailBlocks.value.find(b => b.id === editingBlock.value) || null);
const footerContent = ref('');
const footerBackground = ref('');
const footerCopyright = ref('');
const footerTextColor = ref('#ffffff');
const showTokensDropdown = ref(null);

// Columns editor state
const columnCount = ref(2);
const columnGap = ref('20px');
const columnsContent = ref([]); // array of strings

// Email structure and content
const emailBlocks = ref([
  {
    id: 'header',
    type: 'header',
    content: '<div style="background: #c8102e; color: #ffffff; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;"><h1 style="margin: 0; font-size: 28px; font-weight: 300;">Newsletter Title</h1><p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Your weekly dose of updates</p></div>',
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
  { type: 'text', label: 'Text', icon: ['fas', 'align-left'] },
  { type: 'heading', label: 'Heading', icon: ['fas', 'heading'] },
  { type: 'image', label: 'Image', icon: ['fas', 'image'] },
  { type: 'button', label: 'Button', icon: ['fas', 'square'] },
  { type: 'columns', label: 'Columns', icon: ['fas', 'table-columns'] },
  { type: 'divider', label: 'Divider', icon: ['fas', 'minus'] },
  { type: 'footer', label: 'Footer', icon: ['fas', 'flag'] },
  { type: 'spacer', label: 'Spacer', icon: ['fas', 'ruler-horizontal'] },
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
          padding: '30px 20px'
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
    } else if (/(unsubscribe|copyright|©|all rights reserved)/i.test(innerHTML)) {
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
      fullWidth: false
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

  switch (type) {
    case 'header':
      data = data || {};
      const logoHtml = data.logo ? `<img src="${data.logo}" alt="Logo" style="max-width: ${data.logoSize || '150px'}; height: auto; margin: ${data.logoMargin || '0 0 20px 0'}; padding: ${data.logoPadding || '10px'}; display: block; margin-left: ${data.logoAlignment === 'left' ? '0' : data.logoAlignment === 'right' ? 'auto' : 'auto'}; margin-right: ${data.logoAlignment === 'left' ? 'auto' : data.logoAlignment === 'right' ? '0' : 'auto'};" />` : '';
      return `<div style="background: ${data.background || '#c8102e'}; color: ${data.textColor || '#ffffff'}; padding: ${getPadding(data)}; text-align: center; border-radius: 8px 8px 0 0;">${logoHtml}<h1 style="margin: 0; font-size: 28px; font-weight: 300;">${data.title || 'Newsletter Title'}</h1><p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">${data.subtitle || 'Your weekly dose of updates'}</p></div>`;
    case 'text':
      data = data || {};
      return `<div style="margin: 0; padding: ${getPadding(data)}; background-color: ${data.background || 'transparent'}; font-size: ${data.fontSize || '16px'}; line-height: ${data.lineHeight || '1.6'}; color: ${data.color || '#666666'};">${data.content || '<p>Click to edit this text...</p>'}</div>`;
    case 'heading':
      data = data || {};
      return `<div style="padding: ${getPadding(data)}; background-color: ${data.background || 'transparent'};"><h${data.level || 2} style="margin: 0; font-size: ${data.fontSize || '22px'}; font-weight: ${data.fontWeight || '600'}; color: ${data.color || '#333333'};">${data.content || 'Your Heading Here'}</h${data.level || 2}></div>`;
    case 'image':
      data = data || {};
      const borderRadius = data.fullWidth ? '0' : (data.borderRadius || '8px');
      return data.src ?
        `<div style="padding: ${getPadding(data)};"><img src="${data.src}" alt="${data.alt || 'Image'}" style="width: 100%; max-width: 100%; height: auto; border-radius: ${borderRadius}; display: block;" /></div>` :
        `<div style="padding: ${getPadding(data)};"><div style="width: 100%; height: ${data.height || '200px'}; background: linear-gradient(45deg, #e8f2ff 0%, #f0f8ff 100%); border: 2px dashed #cce7ff; border-radius: ${borderRadius}; display: flex; align-items: center; justify-content: center; color: #667eea; font-size: 14px; cursor: pointer;">Image Placeholder (Click to upload)</div></div>`;
    case 'button':
      return `<div style="text-align: center; margin: 20px 0;"><a href="${data.url}" style="display: inline-block; padding: ${data.padding}; background: ${data.background}; color: ${data.color}; text-decoration: none; border-radius: ${data.borderRadius}; font-weight: 600; transition: transform 0.2s ease;">${data.text}</a></div>`;
    case 'columns':
      data = data || {};
      const count = data.count || (data.columns ? data.columns.length : 2);
      const gap = data.gap || '20px';
      const widthCalc = `calc(${(100 / (count || 1)).toFixed(3)}% - ${gap})`;
      const cols = Array.isArray(data.columns) ? data.columns : [];
      const columnsHtml = cols.map(col => 
        `<div style="width: ${widthCalc}; display: inline-block; vertical-align: top; margin: 0 calc(${gap} / 2);">${(col && col.content) || ''}</div>`
      ).join('');
      return `<div style="padding: ${getPadding(data)}; text-align: left;">${columnsHtml}</div>`;
    case 'divider':
      data = data || {};
      return `<hr style="border: none; border-top: 1px ${data.style || 'solid'} ${data.color || '#e5e7eb'}; margin: ${data.margin || '20px 0'};" />`;
    case 'footer':
      const linksArr = Array.isArray(data.links) ? data.links : [];
      const footerLinks = linksArr.map(link => 
        `<a href="${(link && link.url) || '#'}" style="color: ${(data && data.textColor) || '#ffffff'}; text-decoration: none;">${(link && link.text) || ''}</a>`
      ).join(' | ');
      return `<div style="background-color: ${(data && data.background) || '#c8102e'}; color: ${(data && data.textColor) || '#ffffff'}; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;"><div>${(data && data.content) || ''}</div><p style="margin: 5px 0; color: ${(data && data.textColor) || '#ffffff'}; font-size: 14px;">${footerLinks}</p><p style="margin: 5px 0; color: ${(data && data.textColor) || '#ffffff'}; font-size: 14px;">&copy; ${(data && data.copyright) || ''}</p></div>`;
    case 'spacer':
      return `<div style="height: ${data.height};"></div>`;
    default:
      return `<div style="padding: 10px; background: #f9fafb; border: 1px dashed #d1d5db; text-align: center; color: #6b7280;">Unknown block type: ${type}</div>`;
  }
}

// Block editing functions
function editBlock(blockId) {
  editingBlock.value = blockId;
  const block = emailBlocks.value.find(b => b.id === blockId);
  if (block) {
    if (block.type === 'image') {
      // Preload current image and spacing settings
      imageCropSrc.value = block.data?.src || null;
      imageMargin.value = block.data?.margin || '20px 0';
      imagePadding.value = block.data?.padding || '0';
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
      columnCount.value = Math.max(1, parseInt(data.count || 2, 10));
      columnGap.value = data.gap || '20px';
      const cols = Array.isArray(data.columns) ? data.columns : [];
      columnsContent.value = cols.map((c, i) => (c && c.content) ? c.content : `<p>Column ${i + 1} content</p>`);
      // Ensure length matches count
      if (columnsContent.value.length < columnCount.value) {
        for (let i = columnsContent.value.length; i < columnCount.value; i++) {
          columnsContent.value.push(`<p>Column ${i + 1} content</p>`);
        }
      } else if (columnsContent.value.length > columnCount.value) {
        columnsContent.value = columnsContent.value.slice(0, columnCount.value);
      }
      showColumnEditor.value = true;
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
      headerTitle.value = block.data?.title || 'Newsletter Title';
      headerSubtitle.value = block.data?.subtitle || 'Your weekly dose of updates';
      headerBackground.value = block.data?.background || '#c8102e';
      headerTextColor.value = block.data?.textColor || '#ffffff';
      headerLogo.value = block.data?.logo || '';
      headerLogoAlignment.value = block.data?.logoAlignment || 'center';
      headerLogoSize.value = block.data?.logoSize || '150px';
      headerLogoPadding.value = block.data?.logoPadding || '10px';
      headerLogoMargin.value = block.data?.logoMargin || '0 0 20px 0';
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

    // Build folder: images/newsletters/YYYY-MM-DD-{slug(campaignName)}
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const dateStr = `${yyyy}-${mm}-${dd}`;
    const slug = (props.campaignName || 'newsletter')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'newsletter';
    const folder = `images/newsletters/${dateStr}-${slug}`;
    const uniqueName = `${slug}-${Date.now()}`;

    const formData = new FormData();
    formData.append('image', blob, 'image.jpg');
    formData.append('name', uniqueName);
    formData.append('folder', folder);

    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
    const uploadUrl = (typeof route === 'function')
      ? route('image.upload')
      : (typeof window !== 'undefined' && typeof window.route === 'function')
        ? window.route('image.upload')
        : '/upload-image';
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
    updateBlockData(editingBlock.value, {
      src: url,
      // Keep image fluid inside the content container; spacing on wrapper
      margin: imageMargin.value,
      padding: imagePadding.value,
      width: '100%',
      height: 'auto',
      fullWidth: imageFullWidth.value,
    });

    // Close modal
    showImageUpload.value = false;
    imageCropSrc.value = null;
  } catch (e) {
    // No-op; could add user feedback here
  }
}

// Handle header logo upload
async function handleHeaderLogoUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  try {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('name', `header-logo-${Date.now()}`);
    formData.append('folder', 'images/newsletters/logos');

    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
    const uploadUrl = (typeof route === 'function')
      ? route('image.upload')
      : (typeof window !== 'undefined' && typeof window.route === 'function')
        ? window.route('image.upload')
        : '/upload-image';
    
    const resp = await axios.post(uploadUrl, formData, {
      headers: {
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        Accept: 'application/json',
      },
    });

    let url = resp.data.url;
    if (typeof url === 'string' && url.startsWith('/')) {
      url = `${window.location.origin}${url}`;
    }
    headerLogo.value = url;
  } catch (e) {
    console.error('Logo upload failed:', e);
  }
}

function closeImageEditor() {
  showImageUpload.value = false;
  imageCropSrc.value = null;
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
  updateBlockData(editingBlock.value, {
    text: buttonText.value,
    url: buttonUrl.value,
    background: buttonBackground.value
  });
  showButtonEditor.value = false;
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
  const cols = columnsContent.value.map(c => ({ content: c }));
  updateBlockData(editingBlock.value, {
    count: columnCount.value,
    gap: columnGap.value,
    columns: cols,
  });
  showColumnEditor.value = false;
}

function cancelColumnEdit() {
  showColumnEditor.value = false;
}

// Text editor save/cancel handlers
function saveTextChanges() {
  // Save EmailEditor modal content. If text block, also save background and padding.
  const blk = currentEditingBlock.value;
  if (blk && blk.type === 'text') {
    updateBlockData(editingBlock.value, {
      content: textModalContent.value,
      background: textBackground.value,
      color: textColor.value,
      padding: '15px 50px',
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
}

function cancelTextEdit() {
  showTextEditor.value = false;
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
    logoAlignment: headerLogoAlignment.value,
    logoSize: headerLogoSize.value,
    logoPadding: headerLogoPadding.value,
    logoMargin: headerLogoMargin.value,
  });
  showHeaderEditor.value = false;
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
            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer  transition-colors dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-600"
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
            class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
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
                @click="selectBlock(block.id)"
              >
                <!-- Block Content (static render; dblclick to edit) -->
                <div
                  :class="`transition-all duration-200 ${
                    selectedBlockId === block.id
                      ? 'ring-2 ring-blue-500 ring-opacity-50'
                      : 'hover:ring-1 hover:ring-gray-300'
                  }`"
                  class="cursor-pointer"
                  v-html="block.content"
                  @dblclick="editBlock(block.id)"
                ></div>
                
                <!-- Block Controls -->
                <div
                  class="absolute top-2 right-2 flex gap-1 bg-white shadow-lg rounded-md p-1 z-10 opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto transition-opacity"
                >
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
          <!-- Spacing settings -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Margin (CSS)</label>
              <input type="text" v-model="imageMargin" class="w-full p-2 border border-gray-300 rounded" placeholder="e.g. 20px 0" />
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Padding (CSS)</label>
              <input type="text" v-model="imagePadding" class="w-full p-2 border border-gray-300 rounded" placeholder="e.g. 0 or 10px" />
            </div>
          </div>
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
          <EmailEditor v-model="textModalContent" label="" />
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
      <div class="bg-white rounded-lg p-6 w-[40rem] max-w-full">
        <h3 class="text-lg font-medium mb-4">Edit Columns</h3>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Number of Columns</label>
              <input type="number" min="1" max="4" :value="columnCount" @input="adjustColumnsLength($event.target.value)" class="w-full p-2 border border-gray-300 rounded" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Column Gap (e.g., 20px)</label>
              <input type="text" v-model="columnGap" class="w-full p-2 border border-gray-300 rounded" />
            </div>
          </div>
          <div class="space-y-3">
            <div v-for="(content, idx) in columnsContent" :key="idx">
              <label class="block text-sm font-medium mb-1">Column {{ idx + 1 }} HTML</label>
              <textarea v-model="columnsContent[idx]" rows="4" class="w-full p-2 border border-gray-300 rounded"></textarea>
            </div>
          </div>
        </div>
        <div class="flex gap-2 mt-5 justify-end">
          <button type="button" @click="cancelColumnEdit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveColumnChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
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
                <ColorPicker v-model="footerTextColor" :showAlpha="false" />
                <input v-model="footerTextColor" type="text" class="flex-1 p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" placeholder="#ffffff or any CSS color" />
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
              <div v-if="headerLogo" class="flex items-center gap-3">
                <img :src="headerLogo" alt="Logo preview" class="w-16 h-16 object-contain border rounded" />
                <button type="button" @click="headerLogo = ''" class="text-red-600 hover:text-red-800">Remove</button>
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
              <div>
                <label class="block text-xs dark:text-gray-300 text-gray-600 mb-1">Margin</label>
                <input v-model="headerLogoMargin" type="text" class="w-full p-1 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded text-sm" placeholder="0 0 20px 0" />
              </div>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Title</label>
            <input v-model="headerTitle" type="text" class="w-full p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 dark:text-gray-300 text-gray-600">Subtitle</label>
            <input v-model="headerSubtitle" type="text" class="w-full p-2 border border-gray-300 dark:text-gray-300 dark:bg-gray-800 rounded" />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button" @click="showHeaderEditor = false" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveHeaderChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
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
          <button type="button" @click="showFooterEditor = false" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
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
