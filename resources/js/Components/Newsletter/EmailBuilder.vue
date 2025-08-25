<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import EmailEditor from '@/Components/WYSIWYG/EmailEditor.vue';

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
    type: [String, Object],
    default: '',
  },
  templates: {
    type: Array,
    default: () => [],
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
  if (!struct || !Array.isArray(struct.blocks)) return false;
  emailBlocks.value = struct.blocks.map((b) => {
    const id = b.id || generateBlockId();
    const type = b.type || 'text';
    const data = b.data || {};
    const content = b.content || getBlockHtml(type, data);
    return { id, type, data, content, editable: b.editable ?? true, locked: b.locked ?? (type === 'footer') };
  });
  updateContent();
  return true;
}

const emit = defineEmits(['update:modelValue', 'update:html-content', 'toggle-html-view']);

// UI State
const editor = ref(null);
const showPreview = ref(false);
const showTemplates = ref(false);
const showColorPicker = ref(false);
const selectedColor = ref('#000000');
const previewMode = ref('desktop'); // 'desktop' | 'mobile'
const activePanel = ref('blocks'); // 'blocks' | 'templates' | 'settings'
const selectedBlockId = ref(null);
const draggedBlock = ref(null);
const editingBlock = ref(null);
const showImageUpload = ref(false);
const showButtonEditor = ref(false);
const showColumnEditor = ref(false);
const showTextEditor = ref(false);
const textEditor = ref(null);
// Temporary model for the heading editor modal
const textModalContent = ref('');
// Text block additional settings (background, padding)
const textBackground = ref('transparent');
const textPadding = ref('15px 0');
// Per-text-block TipTap editors
const blockEditors = ref({});
// Header/Footer editor state
const showHeaderEditor = ref(false);
const headerTitle = ref('');
const headerSubtitle = ref('');
const headerBackground = ref('');
const headerTextColor = ref('');

const showFooterEditor = ref(false);
// Currently editing block (computed helper)
const currentEditingBlock = computed(() => emailBlocks.value.find(b => b.id === editingBlock.value) || null);
const footerContent = ref('');
const footerBackground = ref('');
const footerCopyright = ref('');

// Columns editor state
const columnCount = ref(2);
const columnGap = ref('20px');
const columnsContent = ref([]); // array of strings

// Email structure and content
const emailBlocks = ref([
  {
    id: 'header',
    type: 'header',
    content: '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;"><h1 style="margin: 0; font-size: 28px; font-weight: 300;">Newsletter Title</h1><p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">Your weekly dose of updates</p></div>',
    editable: true,
    locked: false,
  },
  {
    id: 'footer',
    type: 'footer',
    content: '<div style="background-color: #f8f9fa; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;"><div>Thanks for reading! Forward this to someone who might find it useful.</div><p style="margin: 5px 0; color: #999; font-size: 14px;">Unsubscribe | Update preferences | View in browser</p><p style="margin: 5px 0; color: #999; font-size: 14px;">&copy; 2025 Your Company Name. All rights reserved.</p></div>',
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

// Draggable Block Types with FontAwesome icons
const blockTypes = [
  { type: 'header', label: 'Header', icon: ['fas', 'heading'] },
  { type: 'text', label: 'Text', icon: ['fas', 'align-left'] },
  { type: 'heading', label: 'Heading', icon: ['fas', 'heading'] },
  { type: 'image', label: 'Image', icon: ['fas', 'image'] },
  { type: 'button', label: 'Button', icon: ['fas', 'square'] },
  { type: 'columns', label: 'Columns', icon: ['fas', 'table-columns'] },
  { type: 'divider', label: 'Divider', icon: ['fas', 'minus'] },
  { type: 'social', label: 'Social Links', icon: ['fas', 'link'] },
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
      fontFamily: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
    },
  };
});

const finalHtmlContent = computed(() => {
  return `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Newsletter</title>
      <style>
        body {
          margin: 0;
          padding: 20px;
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

// Block Management Functions
function generateBlockId() {
  return 'block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
}

function addBlock(blockType, insertIndex = null) {
  const newBlock = createBlock(blockType);
  if (insertIndex !== null) {
    emailBlocks.value.splice(insertIndex, 0, newBlock);
  } else {
    // Insert before footer if it exists
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
  if (index > -1 && !emailBlocks.value[index].locked) {
    emailBlocks.value.splice(index, 1);
    // Clean up any editor instance tied to this block
    if (blockEditors.value[blockId]) {
      try { blockEditors.value[blockId].destroy(); } catch (e) {}
      delete blockEditors.value[blockId];
    }
    updateContent();
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
  const structure = JSON.stringify(emailStructure.value);
  emit('update:modelValue', structure);
  emit('update:html-content', finalHtmlContent.value);
}

// Drag and Drop Functions
function onDragStart(event, blockType) {
  draggedBlock.value = blockType;
  event.dataTransfer.effectAllowed = 'copy';
}

function onDragOver(event) {
  event.preventDefault();
  event.dataTransfer.dropEffect = 'copy';
}

function onDrop(event, insertIndex) {
  event.preventDefault();
  if (draggedBlock.value) {
    addBlock(draggedBlock.value, insertIndex);
    draggedBlock.value = null;
  }
}

// Template Functions
function loadTemplate(template) {
  if (template.content) {
    try {
      const parsed = JSON.parse(template.content);
      if (parsed.blocks) {
        emailBlocks.value = parsed.blocks;
      } else {
        // Legacy format - convert HTML to blocks
        emailBlocks.value = [
          {
            id: 'legacy-content',
            type: 'text',
            content: template.html_content || template.content,
            editable: true,
            locked: false,
          },
        ];
      }
    } catch (e) {
      // Fallback for HTML content
      emailBlocks.value = [
        {
          id: 'html-content',
          type: 'text',
          content: template.html_content || template.content,
          editable: true,
          locked: false,
        },
      ];
    }
  }
  showTemplates.value = false;
  updateContent();
}

function createBlock(type) {
  const id = `block_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  
  const blockDefaults = {
    header: { 
      title: 'Newsletter Title', 
      subtitle: 'Your weekly dose of updates',
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      textColor: '#ffffff'
    },
    text: { 
      content: '<p>Click to edit this text...</p>',
      fontSize: '16px',
      color: '#666666',
      lineHeight: '1.6',
      background: 'transparent',
      padding: '15px 0'
    },
    heading: { 
      content: 'Your Heading Here', 
      level: 2,
      fontSize: '22px',
      color: '#333333',
      fontWeight: '600'
    },
    image: { 
      src: '', 
      alt: 'Image description', 
      width: '100%',
      height: '200px',
      borderRadius: '8px'
    },
    button: { 
      text: 'Click Here', 
      url: '#', 
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      color: '#ffffff',
      borderRadius: '25px',
      padding: '12px 25px'
    },
    columns: {
      count: 2,
      gap: '20px',
      columns: [
        { content: '<p>Column 1 content</p>' },
        { content: '<p>Column 2 content</p>' }
      ]
    },
    divider: { style: 'solid', color: '#e5e7eb', margin: '20px 0' },
    social: { 
      links: [
        { platform: 'email', url: '#', icon: '📧' },
        { platform: 'twitter', url: '#', icon: '🐦' },
        { platform: 'linkedin', url: '#', icon: '💼' },
        { platform: 'facebook', url: '#', icon: '📘' }
      ],
      alignment: 'center'
    },
    footer: {
      content: '<p>Thanks for reading! Forward this to someone who might find it useful.</p>',
      links: [
        { text: 'Unsubscribe', url: '#' },
        { text: 'Update preferences', url: '#' },
        { text: 'View in browser', url: '#' }
      ],
      copyright: '2025 Your Company Name. All rights reserved.',
      background: '#f8f9fa'
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
  switch (type) {
    case 'header':
      return `
        <div style="background: ${data.background}; color: ${data.textColor}; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;">
          <h1 style="margin: 0; font-size: 28px; font-weight: 300;">${data.title}</h1>
          <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 14px;">${data.subtitle}</p>
        </div>`;
    case 'text':
      return `<div style="margin: 0; padding: ${data.padding || '15px 0'}; background-color: ${data.background || 'transparent'}; font-size: ${data.fontSize}; line-height: ${data.lineHeight}; color: ${data.color};">${data.content}</div>`;
    case 'heading':
      return `<h${data.level || 2} style="margin: 0 0 15px 0; font-size: ${data.fontSize}; font-weight: ${data.fontWeight}; color: ${data.color};">${data.content}</h${data.level || 2}>`;
    case 'image':
      return data.src ? 
        `<img src="${data.src}" alt="${data.alt}" style="width: ${data.width}; height: ${data.height}; border-radius: ${data.borderRadius}; display: block; margin: 20px 0;" />` :
        `<div style="width: 100%; height: ${data.height}; background: linear-gradient(45deg, #e8f2ff 0%, #f0f8ff 100%); border: 2px dashed #cce7ff; border-radius: ${data.borderRadius}; display: flex; align-items: center; justify-content: center; margin: 20px 0; color: #667eea; font-size: 14px; cursor: pointer;" onclick="document.getElementById('imageUpload-${data.id || 'temp'}').click()">Image Placeholder (Click to upload)</div>`;
    case 'button':
      return `<div style="text-align: center; margin: 20px 0;"><a href="${data.url}" style="display: inline-block; padding: ${data.padding}; background: ${data.background}; color: ${data.color}; text-decoration: none; border-radius: ${data.borderRadius}; font-weight: 600; transition: transform 0.2s ease;">${data.text}</a></div>`;
    case 'columns':
      const count = data.count || (data.columns ? data.columns.length : 2);
      const gap = data.gap || '20px';
      const widthCalc = `calc(${(100 / (count || 1)).toFixed(3)}% - ${gap})`;
      const columnsHtml = (data.columns || []).map(col => 
        `<div style="width: ${widthCalc}; display: inline-block; vertical-align: top; margin: 0 calc(${gap} / 2);">${col.content}</div>`
      ).join('');
      return `<div style="margin: 20px 0; text-align: left;">${columnsHtml}</div>`;
    case 'divider':
      return `<hr style="border: none; border-top: 1px ${data.style} ${data.color}; margin: ${data.margin};" />`;
    case 'social':
      const socialLinks = (data.links || []).map(link => 
        `<a href="${link.url}" style="display: inline-block; margin: 0 10px; padding: 8px; background-color: #667eea; color: white; border-radius: 50%; width: 16px; height: 16px; text-align: center; line-height: 16px; text-decoration: none; font-size: 12px;">${link.icon}</a>`
      ).join('');
      return `<div style="text-align: ${data.alignment}; margin: 15px 0;">${socialLinks}</div>`;
    case 'footer':
      const footerLinks = data.links.map(link => 
        `<a href="${link.url}" style="color: #667eea; text-decoration: none;">${link.text}</a>`
      ).join(' | ');
      return `
        <div style="background-color: ${data.background}; padding: 25px 30px; text-align: center; border-top: 1px solid #eee;">
          <div>${data.content}</div>
          <p style="margin: 5px 0; color: #999; font-size: 14px;">${footerLinks}</p>
          <p style="margin: 5px 0; color: #999; font-size: 14px;">${data.copyright}</p>
        </div>`;
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
      textPadding.value = block.data?.padding || '15px 0';
      showTextEditor.value = true;
    } else if (block.type === 'heading') {
      // Use EmailEditor in a modal for heading blocks
      textModalContent.value = block.data?.content || block.content || '';
      showTextEditor.value = true;
    } else if (block.type === 'header') {
      headerTitle.value = block.data?.title || 'Newsletter Title';
      headerSubtitle.value = block.data?.subtitle || 'Your weekly dose of updates';
      headerBackground.value = block.data?.background || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
      headerTextColor.value = block.data?.textColor || '#ffffff';
      showHeaderEditor.value = true;
    } else if (block.type === 'footer') {
      footerContent.value = block.data?.content || 'Thanks for reading! Forward this to someone who might find it useful.';
      footerBackground.value = block.data?.background || '#f8f9fa';
      footerCopyright.value = block.data?.copyright || '2025 Your Company Name. All rights reserved.';
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

function handleImageUpload(event, blockId) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      updateBlockData(blockId, { src: e.target.result });
    };
    reader.readAsDataURL(file);
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
      padding: textPadding.value,
    });
  } else {
    updateBlockData(editingBlock.value, { content: textModalContent.value });
  }
  showTextEditor.value = false;
}

function cancelTextEdit() {
  showTextEditor.value = false;
}

onUnmounted(() => {
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
  if (!applyStructure(parsed)) {
    // No valid incoming structure; emit defaults
    updateContent();
  }
});

// Keep builder in sync if parent provides a different structure
watch(
  () => props.modelValue,
  (newVal) => {
    const parsed = safeParseJson(newVal);
    if (!parsed) return;
    // Compare with current to avoid loops
    const currentJson = JSON.stringify(emailStructure.value);
    const incomingJson = typeof newVal === 'string' ? newVal : JSON.stringify(parsed);
    if (incomingJson && incomingJson !== currentJson) {
      applyStructure(parsed);
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
  });
  showHeaderEditor.value = false;
}

// Footer editor save/cancel
function saveFooterChanges() {
  updateBlockData(editingBlock.value, {
    content: footerContent.value,
    background: footerBackground.value,
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
  'company',
];

function insertPersonalizationToken(token) {
  const block = emailBlocks.value.find(b => b.id === selectedBlockId.value);
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
</script>

<template>
  <div class="flex h-screen bg-gray-50">
    <!-- Left Sidebar - Blocks & Templates -->
    <div class="w-80 bg-white border-r border-gray-200 flex flex-col">
      <!-- Sidebar Header -->
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center gap-2 mb-4">
          <button type="button"
            @click="activePanel = 'blocks'"
            :class="`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              activePanel === 'blocks'
                ? 'bg-blue-100 text-blue-700'
                : 'text-gray-600 hover:text-gray-900'
            }`"
          >
            <span class="inline-flex items-center gap-2">
              <font-awesome-icon :icon="['fas','th-large']" class="w-4 h-4" />
              <span>Blocks</span>
            </span>
          </button>
          <button type="button"
            @click="activePanel = 'templates'"
            :class="`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              activePanel === 'templates'
                ? 'bg-blue-100 text-blue-700'
                : 'text-gray-600 hover:text-gray-900'
            }`"
          >
            <span class="inline-flex items-center gap-2">
              <font-awesome-icon :icon="['fas','file']" class="w-4 h-4" />
              <span>Templates</span>
            </span>
          </button>
          <button type="button"
            @click="activePanel = 'settings'"
            :class="`px-3 py-2 text-sm font-medium rounded-md transition-colors ${
              activePanel === 'settings'
                ? 'bg-blue-100 text-blue-700'
                : 'text-gray-600 hover:text-gray-900'
            }`"
          >
            <span class="inline-flex items-center gap-2">
              <font-awesome-icon :icon="['fas','gear']" class="w-4 h-4" />
              <span>Settings</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Sidebar Content -->
      <div class="flex-1 overflow-y-auto p-4">
        <!-- Blocks Panel -->
        <div v-if="activePanel === 'blocks'" class="space-y-2">
          <h3 class="text-sm font-medium text-gray-900 mb-3">Drag blocks to add content</h3>
          <div
            v-for="blockType in blockTypes"
            :key="blockType.type"
            :draggable="true"
            @dragstart="onDragStart($event, blockType.type)"
            @click="addBlock(blockType.type)"
            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors"
          >
            <font-awesome-icon :icon="blockType.icon" class="w-5 h-5 text-gray-700" />
            <span class="text-sm font-medium text-gray-700">{{ blockType.label }}</span>
            <span class="text-xs text-gray-500 ml-auto">Click or drag</span>
          </div>
        </div>

        <!-- Templates Panel -->
        <div v-else-if="activePanel === 'templates'" class="space-y-3">
          <h3 class="text-sm font-medium text-gray-900 mb-3">Load Template</h3>
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

        <!-- Settings Panel -->
        <div v-else-if="activePanel === 'settings'" class="space-y-4">
          <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Email Settings</h3>
          
          <!-- Personalization Tokens -->
          <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Personalization</label>
            <div class="flex flex-wrap gap-1">
              <button type="button"
                v-for="token in personalizationTokens"
                :key="token"
                @click="insertPersonalizationToken(token)"
                class="px-2 py-1 text-xs bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded hover:bg-yellow-200 dark:hover:bg-yellow-800"
              >
                {{ token.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col">
      <!-- Top Toolbar -->
      <div class="bg-white border-b border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <h2 class="text-lg font-semibold text-gray-900">Email Builder</h2>
          </div>
          
          <div class="flex items-center gap-3">
            <!-- Preview Mode Toggle -->
            <button type="button"
              @click="showPreview = !showPreview"
              :class="`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
                showPreview
                  ? 'bg-blue-100 text-blue-700'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`"
            >
              <span class="inline-flex items-center gap-2">
                <font-awesome-icon :icon="showPreview ? ['fas','pen'] : ['fas','eye']" class="w-4 h-4" />
                <span>{{ showPreview ? 'Edit' : 'Preview' }}</span>
              </span>
            </button>
            
            <button type="button"
              @click="toggleHtmlView"
              class="px-4 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors"
            >
              <span class="inline-flex items-center gap-2">
                <font-awesome-icon :icon="['fas','code']" class="w-4 h-4" />
                <span>View Source</span>
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Email Canvas -->
      <div class="flex-1 overflow-auto p-6 bg-gray-50">
        <div
          :class="`mx-auto transition-all duration-300 ${
            previewMode === 'mobile' ? 'max-w-sm' : 'max-w-2xl'
          }`"
        >
          <div
            class="bg-white shadow-lg rounded-lg overflow-hidden"
            @drop="onDrop"
            @dragover="onDragOver"
            style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;"
          >
            <!-- Email Content -->
            <div v-if="!showPreview" class="min-h-96">
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
                  v-if="selectedBlockId === block.id"
                  class="absolute top-2 right-2 flex gap-1 bg-white shadow-lg rounded-md p-1 z-10"
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
                
                <!-- Drop Zone -->
                <div
                  v-if="draggedBlock"
                  class="h-2 bg-blue-200 opacity-0 hover:opacity-100 transition-opacity"
                  @drop="onDropBetween($event, index + 1)"
                  @dragover="onDragOver"
                ></div>
              </div>
              
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
            
            <!-- Preview Mode -->
            <div v-else>
              <div v-html="previewInnerHtml" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Block Editors -->
    <!-- Image Upload Modal -->
    <div v-if="showImageUpload" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-medium mb-4">Upload Image</h3>
        <input
          type="file"
          accept="image/*"
          @change="handleImageUpload($event, editingBlock)"
          class="w-full p-2 border border-gray-300 rounded"
        />
        <div class="flex gap-2 mt-4">
          <button type="button"
            @click="showImageUpload = false"
            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
          >
            Cancel
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
            <label class="block text-sm font-medium mb-1">Background Color</label>
            <input
              v-model="buttonBackground"
              type="color"
              class="w-full p-2 border border-gray-300 rounded"
            />
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
    <div v-if="showTextEditor" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-[32rem] max-w-full">
        <h3 class="text-lg font-medium mb-4">Edit Content</h3>
        <!-- Settings for Text block only -->
        <div v-if="currentEditingBlock && currentEditingBlock.type === 'text'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Background</label>
            <input type="text" v-model="textBackground" class="w-full border rounded px-2 py-1" placeholder="#ffffff or transparent" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Padding</label>
            <input type="text" v-model="textPadding" class="w-full border rounded px-2 py-1" placeholder="e.g. 15px 0" />
          </div>
        </div>
        <div class="border border-gray-300 rounded p-2 bg-white">
          <EmailEditor v-model="textModalContent" label="" />
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button"
            @click="cancelTextEdit"
            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
          >
            Cancel
          </button>
          <button type="button"
            @click="saveTextChanges"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
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
      <div class="bg-white rounded-lg p-6 w-[32rem] max-w-full">
        <h3 class="text-lg font-medium mb-4">Edit Header</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Title</label>
            <input v-model="headerTitle" type="text" class="w-full p-2 border border-gray-300 rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Subtitle</label>
            <input v-model="headerSubtitle" type="text" class="w-full p-2 border border-gray-300 rounded" />
          </div>
          <div class="flex gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium mb-1">Background (CSS)</label>
              <input v-model="headerBackground" type="text" class="w-full p-2 border border-gray-300 rounded" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Text Color</label>
              <input v-model="headerTextColor" type="color" class="p-2 border border-gray-300 rounded" />
            </div>
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
      <div class="bg-white rounded-lg p-6 w-[32rem] max-w-full">
        <h3 class="text-lg font-medium mb-4">Edit Footer</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Content</label>
            <textarea v-model="footerContent" rows="3" class="w-full p-2 border border-gray-300 rounded"></textarea>
          </div>
          <div class="flex gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium mb-1">Background</label>
              <input v-model="footerBackground" type="text" class="w-full p-2 border border-gray-300 rounded" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Copyright</label>
            <input v-model="footerCopyright" type="text" class="w-full p-2 border border-gray-300 rounded" />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <button type="button" @click="showFooterEditor = false" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
          <button type="button" @click="saveFooterChanges" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Full-screen Preview Overlay -->
  <div v-if="showPreview" class="fixed inset-0 bg-black bg-opacity-70 z-50">
    <div class="absolute inset-0 overflow-auto">
      <!-- Overlay Toolbar -->
      <div class="sticky top-0 w-full flex items-center justify-between px-4 py-3 bg-black/30 backdrop-blur-sm">
        <div class="flex items-center gap-2">
          <button type="button"
            :class="`px-3 py-1 text-xs rounded ${previewMode === 'desktop' ? 'bg-blue-600 text-white' : 'bg-white/80 text-gray-800'}`"
            @click="previewMode = 'desktop'"
          >Desktop</button>
          <button type="button"
            :class="`px-3 py-1 text-xs rounded ${previewMode === 'mobile' ? 'bg-blue-600 text-white' : 'bg-white/80 text-gray-800'}`"
            @click="previewMode = 'mobile'"
          >Mobile</button>
        </div>
        <div>
          <button type="button" @click="showPreview = false" class="px-3 py-1 text-sm rounded bg-white text-gray-800 hover:bg-gray-100">Close Preview</button>
        </div>
      </div>

      <!-- Centered Newsletter -->
      <div class="p-6">
        <div :class="previewMode === 'mobile' ? 'max-w-sm mx-auto' : 'max-w-2xl mx-auto'">
          <div class="bg-white shadow-2xl rounded-lg overflow-hidden">
            <div v-html="previewInnerHtml" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

</template>
<style>
.ProseMirror {
  outline: none;
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
