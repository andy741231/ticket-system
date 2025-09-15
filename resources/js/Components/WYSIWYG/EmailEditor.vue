<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TiptapLink from '@tiptap/extension-link';
import TextIndent from '@/Extensions/TextIndent';
import Image from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableRow } from '@tiptap/extension-table-row';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import { Color } from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import { Placeholder } from '@tiptap/extension-placeholder';
import ParagraphWithClass from '@/Extensions/ParagraphWithClass';
import { ref, onBeforeUnmount, watch, nextTick, onMounted } from 'vue';
import ColorPicker from '@/Components/Newsletter/ColorPicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faBold,
  faItalic,
  faUnderline,
  faStrikethrough,
  faAlignLeft,
  faAlignCenter,
  faAlignRight,
  faAlignJustify,
  faListUl,
  faListOl,
  faQuoteRight,
  faIndent,
  faOutdent,
  faLink,
  faImage,
  faFont,
  faHighlighter,
  faTextHeight,
  faArrowLeft,
  faArrowRight,
} from '@fortawesome/free-solid-svg-icons';

// Add icons to the library
library.add(
  faBold,
  faItalic,
  faUnderline,
  faStrikethrough,
  faAlignLeft,
  faAlignCenter,
  faAlignRight,
  faAlignJustify,
  faListUl,
  faListOl,
  faQuoteRight,
  faIndent,
  faOutdent,
  faLink,
  faImage,
  faFont,
  faHighlighter,
  faTextHeight,
  faArrowLeft,
  faArrowRight
);

// Extend Image to support dynamic width/height/class/style attributes
const ResizableImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: null,
      },
      width: {
        default: null,
        renderHTML: attributes => {
          if (!attributes.width) return {};
          return { width: attributes.width };
        },
      },
      height: {
        default: null,
        renderHTML: attributes => {
          if (!attributes.height) return {};
          return { height: attributes.height };
        },
      },
      style: {
        default: null,
      },
    };
  },
});

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: 'Content',
  },
  error: {
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

// Text color state for toolbar ColorPicker
const textColor = ref('#000000');

watch(textColor, (val) => {
  if (!editor?.value) return;
  editor.value.chain().focus().setColor(val || '#000000').run();
});

const emit = defineEmits(['update:modelValue']);

// Toast notification state
const showImagePasteToast = ref(false);

// Reactive data for format dropdown
const currentFormat = ref('paragraph');

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      paragraph: false,
      heading: {
        levels: [1, 2, 3],
      },
      bulletList: {
        keepMarks: true,
        keepAttributes: false,
        HTMLAttributes: {
          class: 'list-disc pl-5',
        },
      },
      orderedList: {
        keepMarks: true,
        keepAttributes: false,
        HTMLAttributes: {
          class: 'list-decimal pl-5',
        },
      },
      blockquote: {
        HTMLAttributes: {
          class: 'border-l-4 border-gray-300 pl-4 py-1 my-2',
        },
      },
      hardBreak: true,
      strike: true,
      link: false, // Disable default Link extension
      underline: false, // Disable default Underline extension
    }),
    // Override paragraph to allow class attribute (for drop cap)
    ParagraphWithClass,
    Underline,
    TiptapLink.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: 'text-blue-600 hover:underline',
      },
    }),
    // Enable Image extension with resizable attributes; paste of images is still prevented via handlePaste below
    ResizableImage,
    Table.configure({
      resizable: true,
      HTMLAttributes: {
        class: 'border-collapse border border-gray-300',
      },
    }),
    TableRow,
    TableHeader,
    TableCell,
    Highlight.configure({
      multicolor: true,
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph', 'blockquote'],
      alignments: ['left', 'center', 'right', 'justify'],
    }),
    TextIndent,
    TextStyle,
    Color,
    Placeholder.configure({
      placeholder: 'Write something...',
      emptyEditorClass: 'is-editor-empty',
      emptyNodeClass: 'is-empty',
    }),
  ],
  content: props.modelValue,
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML());
  },
  editorProps: {
    handlePaste: (view, event, slice) => {
      // Check if the paste contains images
      const hasImages = slice.content.descendants((node) => {
        if (node.type.name === 'image') {
          return false; // Stop traversal and indicate image found
        }
        return true; // Continue traversal
      });
      
      // Check clipboard for image files
      const clipboardData = event.clipboardData || event.originalEvent?.clipboardData;
      if (clipboardData) {
        const items = Array.from(clipboardData.items);
        const hasImageFiles = items.some(item => item.type.startsWith('image/'));
        
        if (hasImageFiles) {
          // Prevent the paste and show friendly message
          showImagePasteMessage();
          return true; // Prevent default paste behavior
        }
      }
      
      // If no images detected, allow normal paste
      return false;
    },
  },
});

// Watch editor content to update format dropdown
watch(
  () => {
    if (!editor || !editor.value) return null;
    return editor.value.getAttributes('heading')?.level;
  },
  (level) => {
    if (level === undefined) return;
    currentFormat.value = level ? `heading${level}` : 'paragraph';
  }
);

// Update format based on dropdown selection
const updateFormat = () => {
  if (!editor.value) return;

  const chain = editor.value.chain().focus();

  if (currentFormat.value === 'paragraph') {
    chain.setParagraph().run();
  } else if (currentFormat.value.startsWith('heading')) {
    const level = parseInt(currentFormat.value.replace('heading', ''));
    chain.setHeading({ level }).run();
  }
};

// Update editor content when modelValue changes
watch(
  () => props.modelValue,
  (newValue, oldValue) => {
    if (!editor?.value || newValue === undefined || newValue === oldValue) return;

    const isSame = editor.value.getHTML() === newValue;
    if (isSame) return;

    editor.value.commands.setContent(newValue, false);
  },
  { immediate: true }
);

// Link dialog state
const showLinkDialog = ref(false);
const linkUrl = ref('');
const linkText = ref('');
const openInNewTab = ref(true);
let isEditingLink = false;

// Image upload state for Link Dialog
const isUploading = ref(false);
const uploadError = ref('');
const uploadedUrls = ref([]); // { name, url }

// Image upload modal state
const showImageDialog = ref(false);
const imageUploadError = ref('');
const isImageUploading = ref(false);
const imageUploadedUrls = ref([]);
const selectedImagePosition = ref('none'); // none, float-left, float-right, center, full-width
// Image size controls in dialog
const sizePreset = ref('original'); // original, small, medium, large, custom
const customImageWidth = ref(''); // in px
const customImageHeight = ref(''); // in px
const imageLockRatio = ref(true);

const openLinkDialog = (url = '') => {
  if (!editor?.value) return;

  isEditingLink = !!url;
  linkUrl.value = url || '';
  linkText.value = editor.value.getText();
  showLinkDialog.value = true;
};

const formatUrl = (url) => {
  if (!url) return '';

  // If it's already a valid URL with protocol, return as is
  try {
    new URL(url);
    return url;
  } catch (e) {
    // Not a valid URL, try to fix it
  }

  // If it has a dot but no protocol, assume it's a domain
  if (url.includes('.') && !url.includes(' ')) {
    return `https://${url.replace(/^https?:\/\//, '')}`;
  }

  // If it's a simple word, assume user wants to search
  if (/^[a-zA-Z0-9-_]+$/.test(url)) {
    return `https://www.google.com/search?q=${encodeURIComponent(url)}`;
  }

  // For anything else, try to make it a search query
  return `https://www.google.com/search?q=${encodeURIComponent(url)}`;
};

const confirmLink = () => {
  if (!editor?.value || !linkUrl.value.trim()) return;

  const formattedUrl = formatUrl(linkUrl.value.trim());

  if (formattedUrl === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
  } else {
    const { state } = editor.value;
    const { empty } = state.selection;

    if (empty) {
      // No text selected: insert the URL as text and apply link mark to it
      editor.value
        .chain()
        .focus()
        .insertContent({
          type: 'text',
          text: formattedUrl,
          marks: [
            {
              type: 'link',
              attrs: {
                href: formattedUrl,
                target: openInNewTab.value ? '_blank' : null,
                rel: 'noopener noreferrer',
              },
            },
          ],
        })
        .run();
    } else {
      // Text selected: apply link to the selection
      editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({
          href: formattedUrl,
          target: openInNewTab.value ? '_blank' : null,
          rel: 'noopener noreferrer', // Security best practice
        })
        .run();
    }
  }

  resetLinkDialog();
};

const resetLinkDialog = () => {
  showLinkDialog.value = false;
  linkUrl.value = '';
  linkText.value = '';
  openInNewTab.value = true;
  isEditingLink = false;
  isUploading.value = false;
  uploadError.value = '';
  uploadedUrls.value = [];
};

const resetImageDialog = () => {
  showImageDialog.value = false;
  imageUploadError.value = '';
  isImageUploading.value = false;
  imageUploadedUrls.value = [];
  selectedImagePosition.value = 'none';
  // Reset size controls
  sizePreset.value = 'original';
  customImageWidth.value = '';
  customImageHeight.value = '';
  imageLockRatio.value = true;
};

// Upload selected files to images/newsletters via /api/image-upload
const handleFileSelection = async (event) => {
  const files = Array.from(event.target.files || []);
  uploadError.value = '';
  if (!files.length) return;
  
  // Validate file size (15MB = 15 * 1024 * 1024 bytes)
  const maxSize = 15 * 1024 * 1024;
  for (const file of files) {
    if (file.size > maxSize) {
      uploadError.value = `File "${file.name}" exceeds 15MB limit`;
      return;
    }
  }
  
  isUploading.value = true;
  try {
    for (const file of files) {
      const form = new FormData();
      // Laravel controller expects: file, name, folder, campaign_id
      form.append('file', file);
      const baseName = (file.name || 'file')
        .replace(/\.[^/.]+$/, '')
        .replace(/[^A-Za-z0-9-_]+/g, '-');
      form.append('name', baseName);
      form.append('folder', 'images/newsletters');
      if (props.campaignId) {
        form.append('campaign_id', props.campaignId);
      } else if (props.tempKey) {
        form.append('temp_key', props.tempKey);
      }

      const { data } = await axios.post('/api/image-upload', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      if (data?.url) {
        uploadedUrls.value.unshift({ name: file.name, url: data.url });
        // If URL field is empty, auto-populate with the first uploaded URL
        if (!linkUrl.value) linkUrl.value = data.url;
      }
    }
  } catch (e) {
    uploadError.value = e?.response?.data?.message || e.message || 'Upload failed';
  } finally {
    isUploading.value = false;
    // Clear the input so the same file can be re-selected if needed
    if (event?.target) event.target.value = '';
  }
};

const useUploadedUrl = (url) => {
  linkUrl.value = url || '';
};

// Image upload functions
const openImageDialog = () => {
  showImageDialog.value = true;
};

const handleImageFileSelection = async (event) => {
  const files = Array.from(event.target.files || []);
  imageUploadError.value = '';
  if (!files.length) return;
  
  // Validate file size and type
  const maxSize = 15 * 1024 * 1024;
  for (const file of files) {
    if (file.size > maxSize) {
      imageUploadError.value = `File "${file.name}" exceeds 15MB limit`;
      return;
    }
    if (!file.type.startsWith('image/')) {
      imageUploadError.value = `File "${file.name}" is not an image`;
      return;
    }
  }
  
  isImageUploading.value = true;
  try {
    for (const file of files) {
      const form = new FormData();
      form.append('file', file);
      const baseName = (file.name || 'image')
        .replace(/\.[^/.]+$/, '')
        .replace(/[^A-Za-z0-9-_]+/g, '-');
      form.append('name', baseName);
      form.append('folder', 'images/newsletters');
      if (props.campaignId) {
        form.append('campaign_id', props.campaignId);
      } else if (props.tempKey) {
        form.append('temp_key', props.tempKey);
      }

      const { data } = await axios.post('/api/image-upload', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      if (data?.url) {
        imageUploadedUrls.value.unshift({ name: file.name, url: data.url });
      }
    }
  } catch (e) {
    imageUploadError.value = e?.response?.data?.message || e.message || 'Upload failed';
  } finally {
    isImageUploading.value = false;
    if (event?.target) event.target.value = '';
  }
};

const insertImage = (imageUrl) => {
  if (!editor?.value || !imageUrl) return;
  
  let className = '';
  switch (selectedImagePosition.value) {
    case 'float-left':
      className = 'float-left mr-4 mb-2';
      break;
    case 'float-right':
      className = 'float-right ml-4 mb-2';
      break;
    case 'center':
      className = 'mx-auto block';
      break;
    case 'full-width':
      className = 'w-full';
      break;
    default:
      className = '';
  }
  
  // Determine width/height from dialog controls
  let widthAttr = null;
  let heightAttr = null;

  if (sizePreset.value === 'small') {
    widthAttr = 200;
  } else if (sizePreset.value === 'medium') {
    widthAttr = 400;
  } else if (sizePreset.value === 'large') {
    widthAttr = 600;
  } else if (sizePreset.value === 'custom') {
    widthAttr = customImageWidth.value ? parseInt(customImageWidth.value, 10) : null;
    if (!imageLockRatio.value) {
      heightAttr = customImageHeight.value ? parseInt(customImageHeight.value, 10) : null;
    }
  }

  // If full-width, ignore explicit width/height so CSS class can handle sizing
  if (selectedImagePosition.value === 'full-width') {
    widthAttr = null;
    heightAttr = null;
  }

  const attrs = { src: imageUrl, class: className };
  if (widthAttr) attrs.width = widthAttr;
  if (!imageLockRatio.value && heightAttr) attrs.height = heightAttr;

  editor.value.chain().focus().setImage(attrs).run();
  
  resetImageDialog();
};

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    resetLinkDialog();
  } else if (event.key === 'Enter' && event.ctrlKey) {
    event.preventDefault();
    confirmLink();
  }
};

const setLink = () => {
  if (!editor?.value) return;
  const previousUrl = editor.value.getAttributes('link').href;
  openLinkDialog(previousUrl);
};

const addImage = () => {
  if (!editor) return;
  const url = window.prompt('Enter the URL of the image:');
  if (url) {
    editor.chain().focus().setImage({ src: url }).run();
  }
};

const handleIndent = () => {
  try {
    if (!editor.value) {
      console.error('Editor not initialized');
      return;
    }

    // Get current selection and node
    const { state } = editor.value;
    const { selection } = state;
    const { $from } = selection;
    const node = $from.parent;

    // Get current indent level or default to 0
    const currentIndent = node.attrs.indent ? parseInt(node.attrs.indent, 10) : 0;
    const newIndent = Math.min(currentIndent + 1, 4); // Max 4 levels

    // Update the node's attributes directly
    editor.value
      .chain()
      .focus()
      .command(({ tr }) => {
        tr.setNodeMarkup($from.before(), undefined, {
          ...node.attrs,
          indent: newIndent,
        });
        return true;
      })
      .run();
  } catch (error) {
    console.error('Error in handleIndent:', error);
  }
};

const handleOutdent = () => {
  try {
    if (!editor.value) {
      console.error('Editor not initialized');
      return;
    }

    // Get current selection and node
    const { state } = editor.value;
    const { selection } = state;
    const { $from } = selection;
    const node = $from.parent;

    // Get current indent level or default to 0
    const currentIndent = node.attrs.indent ? parseInt(node.attrs.indent, 10) : 0;
    const newIndent = Math.max(0, currentIndent - 1);

    // Update the node's attributes directly
    editor.value
      .chain()
      .focus()
      .command(({ tr }) => {
        const attrs = { ...node.attrs };
        if (newIndent > 0) {
          attrs.indent = newIndent;
        } else {
          delete attrs.indent;
        }
        tr.setNodeMarkup($from.before(), undefined, attrs);
        return true;
      })
      .run();
  } catch (error) {
    console.error('Error in handleOutdent:', error);
  }
};

// Show friendly message when user tries to paste images
const showImagePasteMessage = () => {
  showImagePasteToast.value = true;
  // Auto-hide after 4 seconds
  setTimeout(() => {
    showImagePasteToast.value = false;
  }, 4000);
};

// Hide toast manually
const hideImagePasteToast = () => {
  showImagePasteToast.value = false;
};

// Determine if the current paragraph has the drop cap class
const isDropCapActive = () => {
  if (!editor.value) return false;
  const { selection } = editor.value.state;
  const $pos = selection.$from;
  const parent = $pos.parent;
  if (!parent || parent.type.name !== 'paragraph') return false;
  const cls = parent.attrs?.class || '';
  return String(cls).split(/\s+/).includes('has-dropcap');
};

// Toggle drop cap on the current paragraph
const toggleDropCap = () => {
  if (!editor.value) return;
  const { selection } = editor.value.state;
  const $pos = selection.$from;
  const parent = $pos.parent;
  if (!parent || parent.type.name !== 'paragraph') return;

  const classes = String(parent.attrs?.class || '')
    .split(/\s+/)
    .filter(Boolean);
  const idx = classes.indexOf('has-dropcap');
  if (idx >= 0) {
    classes.splice(idx, 1);
  } else {
    classes.push('has-dropcap');
  }

  editor.value
    .chain()
    .focus()
    .updateAttributes('paragraph', {
      ...parent.attrs,
      class: classes.join(' '),
    })
    .run();
};

onBeforeUnmount(() => {
  if (editor?.value) {
    try { editor.value.off('selectionUpdate', updateImageResizeControl); } catch (e) {}
    try { editor.value.off('transaction', updateImageResizeControl); } catch (e) {}
    editor.value.destroy();
  }
});

// In-editor image resize control
const showImageResizeControl = ref(false);
const resizeWidth = ref(300);

const updateImageResizeControl = () => {
  if (!editor?.value) return;
  const isImageActive = editor.value.isActive('image');
  showImageResizeControl.value = !!isImageActive;
  if (isImageActive) {
    const attrs = editor.value.getAttributes('image') || {};
    if (attrs.width) {
      resizeWidth.value = parseInt(attrs.width, 10) || resizeWidth.value;
    } else {
      // Try to infer from DOM
      const el = document.querySelector('.ProseMirror img.ProseMirror-selectednode');
      if (el) resizeWidth.value = Math.round(el.clientWidth);
    }
  }
};

const applyResizeWidth = () => {
  if (!editor?.value) return;
  const widthVal = parseInt(resizeWidth.value, 10);
  if (!widthVal || widthVal < 10) return;
  editor.value.chain().focus().updateAttributes('image', { width: widthVal, height: null }).run();
};

const onResizeWidthInput = () => {
  applyResizeWidth();
};

const hideResizeControls = () => {
  showImageResizeControl.value = false;
};

onMounted(() => {
  if (!editor?.value) return;
  editor.value.on('selectionUpdate', updateImageResizeControl);
  editor.value.on('transaction', updateImageResizeControl);
});
</script>

<template>
  <div class="w-full">
    <InputLabel v-if="label" :for="$attrs.id || 'editor'" :value="label" class="mb-1 text-uh-slate" />
    <div class="mt-1">
      <div class="border border-gray-300 rounded-md shadow-sm overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
        <!-- Menu Bar -->
        <div v-if="editor" class="border-b border-gray-300 bg-gray-50 p-2 flex flex-wrap items-center gap-2 text-gray-700">
          <!-- Text Format Dropdown -->
          <div class="flex items-center">
            <select
              v-model="currentFormat"
              @change="updateFormat"
              class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 h-9"
            >
              <option value="heading1">Heading 1</option>
              <option value="heading2">Heading 2</option>
              <option value="heading3">Heading 3</option>
              <option selected value="paragraph">Paragraph</option>
            </select>
          </div>

          <!-- Basic Formatting -->
          <div class="flex items-center gap-1">
            <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{'bg-gray-200': editor.isActive('bold')}" class="p-2 rounded hover:bg-gray-200" title="Bold (Ctrl/Cmd + B)">
              <font-awesome-icon :icon="['fas', 'bold']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{'bg-gray-200': editor.isActive('italic')}" class="p-2 rounded hover:bg-gray-200" title="Italic (Ctrl/Cmd + I)">
              <font-awesome-icon :icon="['fas', 'italic']" class="w-5 h-5" />
            </button>
            <!-- Text Decoration -->
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{'bg-gray-200': editor.isActive('underline')}" class="p-2 rounded hover:bg-gray-200" title="Underline (Ctrl/Cmd + U)">
              <font-awesome-icon :icon="['fas', 'underline']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{'bg-gray-200': editor.isActive('strike')}" class="p-2 rounded hover:bg-gray-200" title="Strikethrough (Ctrl/Cmd + Shift + X)">
              <font-awesome-icon :icon="['fas', 'strikethrough']" class="w-5 h-5" />
            </button>
          </div>

          <div class="w-px h-5 bg-gray-300 mx-1"></div>

          <!-- Alignment -->
          <div class="flex items-center gap-1">
            <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{ 'bg-gray-200': editor.isActive({ textAlign: 'left' }) }" class="p-2 rounded hover:bg-gray-200" title="Align Left (Ctrl/Cmd + Shift + L)">
              <font-awesome-icon :icon="['fas', 'align-left']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{ 'bg-gray-200': editor.isActive({ textAlign: 'center' }) }" class="p-2 rounded hover:bg-gray-200" title="Align Center (Ctrl/Cmd + Shift + E)">
              <font-awesome-icon :icon="['fas', 'align-center']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{ 'bg-gray-200': editor.isActive({ textAlign: 'right' }) }" class="p-2 rounded hover:bg-gray-200" title="Align Right (Ctrl/Cmd + Shift + R)">
              <font-awesome-icon :icon="['fas', 'align-right']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()" :class="{ 'bg-gray-200': editor.isActive({ textAlign: 'justify' }) }" class="p-2 rounded hover:bg-gray-200" title="Justify (Ctrl/Cmd + Shift + J)">
              <font-awesome-icon :icon="['fas', 'align-justify']" class="w-5 h-5" />
            </button>
          </div>

          <div class="w-px h-5 bg-gray-300 mx-1"></div>

          <!-- Text Color & Highlight -->
          <div class="flex items-center gap-1">
            <!-- Highlight Button with Color Picker -->
            <div class="relative group">
              <button
                type="button"
                class="p-2 rounded hover:bg-gray-200"
                :class="{ 'bg-gray-200': editor.isActive('highlight') }"
                title="Highlight Text (Ctrl/Cmd + Alt + H)"
              >
                <font-awesome-icon :icon="['fas', 'highlighter']" class="w-5 h-5" />
              </button>
              <!-- Color Picker Dropdown -->
              <div class="absolute z-10 hidden group-hover:block bg-white shadow-lg rounded-md p-2 w-48 right-0">
                <div class="text-xs text-gray-500 mb-1 mt-2">Highlight Color</div>
                <div class="flex gap-1">
                  <button type="button" @click="editor.chain().focus().toggleHighlight({ color: '#fef08a' }).run()" class="w-6 h-6 rounded-full bg-yellow-200 hover:ring-2 hover:ring-offset-2 hover:ring-yellow-400" title="Yellow Highlight"></button>
                  <button type="button" @click="editor.chain().focus().toggleHighlight({ color: '#bfdbfe' }).run()" class="w-6 h-6 rounded-full bg-blue-200 hover:ring-2 hover:ring-offset-2 hover:ring-blue-400" title="Blue Highlight"></button>
                  <button type="button" @click="editor.chain().focus().toggleHighlight({ color: '#bbf7d0' }).run()" class="w-6 h-6 rounded-full bg-green-200 hover:ring-2 hover:ring-offset-2 hover:ring-green-400" title="Green Highlight"></button>
                  <button type="button" @click="editor.chain().focus().toggleHighlight({ color: '#fecaca' }).run()" class="w-6 h-6 rounded-full bg-red-200 hover:ring-2 hover:ring-offset-2 hover:ring-red-400" title="Red Highlight"></button>
                  <button type="button" @click="editor.chain().focus().toggleHighlight({ color: '#e9d5ff' }).run()" class="w-6 h-6 rounded-full bg-purple-200 hover:ring-2 hover:ring-offset-2 hover:ring-purple-400" title="Purple Highlight"></button>
                </div>
                <div class="mt-2">
                  <input
                    type="color"
                    @input="editor.chain().focus().setHighlight({ color: $event.target.value }).run()"
                    value="#fef08a"
                    class="w-full h-8 cursor-pointer rounded border border-gray-300"
                    title="Custom Highlight Color"
                  />
                </div>
              </div>
            </div>
            <!-- Text Color with ColorPicker (custom trigger retains font icon) -->
            <div class="flex items-center">
              <ColorPicker v-model="textColor" :showAlpha="true">
                <template #trigger>
                  <button type="button" class="p-2 rounded hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('textStyle') }" title="Text Color">
                    <font-awesome-icon :icon="['fas', 'font']" class="w-5 h-5" />
                  </button>
                </template>
              </ColorPicker>
            </div>
          </div>

          <!-- Lists & Indentation -->
          <div class="flex items-center gap-1">
            <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-gray-200': editor.isActive('bulletList') }" class="p-2 rounded hover:bg-gray-200" title="Bullet List (Ctrl/Cmd + Shift + 8)">
              <font-awesome-icon :icon="['fas', 'list-ul']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-gray-200': editor.isActive('orderedList') }" class="p-2 rounded hover:bg-gray-200" title="Numbered List (Ctrl/Cmd + Shift + 7)">
              <font-awesome-icon :icon="['fas', 'list-ol']" class="w-5 h-5" />
            </button>
            <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'bg-gray-200': editor.isActive('blockquote') }" class="p-2 rounded hover:bg-gray-200" title="Blockquote (Ctrl/Cmd + Shift + B)">
              <font-awesome-icon :icon="['fas', 'quote-right']" class="w-5 h-5" />
            </button>
            <div class="h-6 w-px bg-gray-300 mx-1"></div>
            <button type="button" @click="handleIndent" class="p-2 rounded hover:bg-gray-200" title="Indent (Tab)">
              <font-awesome-icon :icon="['fas', 'indent']" class="w-5 h-5" />
            </button>
            <button type="button" @click="handleOutdent" class="p-2 rounded hover:bg-gray-200" title="Outdent (Shift + Tab)">
              <font-awesome-icon :icon="['fas', 'outdent']" class="w-5 h-5" />
            </button>
          </div>

          <!-- Links & Media -->
          <div class="flex items-center gap-1">
            <button type="button" @click="toggleDropCap" :class="{ 'bg-gray-200': isDropCapActive() }" class="p-2 rounded hover:bg-gray-200" title="Toggle Drop Cap">
              <font-awesome-icon :icon="['fas', 'text-height']" class="w-5 h-5" />
            </button>
            <button type="button" @click="setLink" :class="{ 'bg-gray-200': editor.isActive('link') }" class="p-2 rounded hover:bg-gray-200" title="Add Link">
              <font-awesome-icon :icon="['fas', 'link']" class="w-5 h-5" />
            </button>
            <button type="button" @click="openImageDialog" class="p-2 rounded hover:bg-gray-200" title="Insert Image">
              <font-awesome-icon :icon="['fas', 'image']" class="w-5 h-5" />
            </button>
          </div>

          <!-- Image Resize Controls (visible when an image is selected) -->
          <div v-if="showImageResizeControl" class="flex items-center gap-2 ml-auto">
            <span class="text-xs text-gray-600">Image width</span>
            <input type="range" min="50" max="1200" step="10" v-model.number="resizeWidth" @input="onResizeWidthInput" class="w-40">
            <input type="number" min="10" max="2000" step="1" v-model.number="resizeWidth" @change="applyResizeWidth" class="w-20 rounded border-gray-300 text-sm">
            <button type="button" class="px-2 py-1 text-xs text-gray-600 hover:text-gray-900" @click="hideResizeControls">Hide</button>
          </div>
        </div>

        <!-- Editor Content -->
        <div class="p-4 bg-white text-gray-900 min-h-[300px] max-h-[600px] overflow-y-auto focus:outline-none" :class="{ 'border-red-500': error }">
          <EditorContent :editor="editor" class="prose max-w-none focus:outline-none" :class="{ 'border-red-500': error }" />
        </div>
        <InputError v-if="error" :message="error" class="mt-1" />
      </div>
      <InputError v-if="error" :message="error" class="mt-2" />
    </div>

    <!-- Link Dialog -->
    <div v-if="showLinkDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="resetLinkDialog">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 mx-4" @keydown.esc="resetLinkDialog">
        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ isEditingLink ? 'Edit Link' : 'Insert Link' }}</h3>

        <div>
          <div>
            <label for="link-url" class="block text-sm font-medium text-gray-700 mb-1">
              URL <span class="text-red-500">*</span>
            </label>
            <input
              id="link-url"
              v-model="linkUrl"
              type="text"
              placeholder="Enter URL or search terms"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              @keydown.enter.prevent="confirmLink"
              autofocus
            >
          </div>

          <!-- Uploader -->
          <div class="mt-4">
            <div class="flex items-center justify-between">
              <label for="link-upload" class="block text-sm font-medium text-gray-700">Upload file(s) to campaign folder</label>
              <span class="text-xs text-gray-500">Max 15MB, stored at images/newsletters</span>
            </div>
            <input
              id="link-upload"
              type="file"
              multiple
              class="mt-1 block w-full text-sm text-gray-700"
              @change="handleFileSelection"
            />
            <div v-if="isUploading" class="mt-2 text-sm text-gray-600">Uploading… please wait</div>
            <div v-if="uploadError" class="mt-2 text-sm text-red-600">{{ uploadError }}</div>

            <!-- Uploaded files list -->
            <div v-if="uploadedUrls.length" class="mt-3 max-h-32 overflow-y-auto border rounded-md divide-y">
              <div v-for="item in uploadedUrls" :key="item.url" class="flex items-center justify-between px-2 py-1 text-sm">
                <div class="truncate mr-2" :title="item.url">{{ item.name }}</div>
                <div class="flex items-center gap-2">
                  <button type="button" class="text-indigo-600 hover:underline" @click="useUploadedUrl(item.url)">Use</button>
                  <button type="button" class="text-gray-600 hover:underline" @click="navigator.clipboard.writeText(item.url)">Copy URL</button>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center mt-3">
            <input
              id="open-new-tab"
              v-model="openInNewTab"
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            >
            <label for="open-new-tab" class="ml-2 block text-sm text-gray-700">
              Open in new tab
            </label>
          </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button
            type="button"
            @click="resetLinkDialog"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="confirmLink"
            :disabled="!linkUrl"
            :class="{
              'opacity-50 cursor-not-allowed': !linkUrl,
              'bg-indigo-600 hover:bg-indigo-700': linkUrl,
              'bg-gray-400': !linkUrl,
            }"
            class="px-4 py-2 text-sm font-medium text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            {{ isEditingLink ? 'Update' : 'Insert' }} Link
          </button>
        </div>
      </div>
    </div>

        <!-- Image Upload Dialog -->
        <div v-if="showImageDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="resetImageDialog">
          <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 mx-4" @keydown.esc="resetImageDialog">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Insert Image</h3>

        <!-- Image Position Presets -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Image Position</label>
          <div class="grid grid-cols-5 gap-2">
            
            <!-- None/Default -->
            <button
              type="button"
              @click="selectedImagePosition = 'none'"
              :class="{
                'bg-indigo-100 border-indigo-500': selectedImagePosition === 'none',
                'bg-gray-50 border-gray-300': selectedImagePosition !== 'none'
              }"
              class="p-3 border-2 rounded-lg hover:bg-gray-100 flex flex-col items-center text-xs"
              title="Default"
            >
              <div class="w-6 h-4 bg-gray-400 rounded mb-1"></div>
              <span>Default</span>
            </button>

            <!-- Float Left -->
            <button
              type="button"
              @click="selectedImagePosition = 'float-left'"
              :class="{
                'bg-indigo-100 border-indigo-500': selectedImagePosition === 'float-left',
                'bg-gray-50 border-gray-300': selectedImagePosition !== 'float-left'
              }"
              class="p-3 border-2 rounded-lg hover:bg-gray-100 flex flex-col items-center text-xs"
              title="Float Left"
            >
              <div class="flex items-start w-full">
                <div class="w-3 h-3 bg-gray-400 rounded mr-1"></div>
                <div class="flex-1 space-y-1">
                  <div class="h-1 bg-gray-300 rounded"></div>
                  <div class="h-1 bg-gray-300 rounded"></div>
                </div>
              </div>
              <font-awesome-icon :icon="['fas', 'arrow-left']" class="mt-1 text-gray-600" />
            </button>

            <!-- Float Right -->
            <button
              type="button"
              @click="selectedImagePosition = 'float-right'"
              :class="{
                'bg-indigo-100 border-indigo-500': selectedImagePosition === 'float-right',
                'bg-gray-50 border-gray-300': selectedImagePosition !== 'float-right'
              }"
              class="p-3 border-2 rounded-lg hover:bg-gray-100 flex flex-col items-center text-xs"
              title="Float Right"
            >
              <div class="flex items-start w-full">
                <div class="flex-1 space-y-1 mr-1">
                  <div class="h-1 bg-gray-300 rounded"></div>
                  <div class="h-1 bg-gray-300 rounded"></div>
                </div>
                <div class="w-3 h-3 bg-gray-400 rounded"></div>
              </div>
              <font-awesome-icon :icon="['fas', 'arrow-right']" class="mt-1 text-gray-600" />
            </button>

            <!-- Center -->
            <button
              type="button"
              @click="selectedImagePosition = 'center'"
              :class="{
                'bg-indigo-100 border-indigo-500': selectedImagePosition === 'center',
                'bg-gray-50 border-gray-300': selectedImagePosition !== 'center'
              }"
              class="p-3 border-2 rounded-lg hover:bg-gray-100 flex flex-col items-center text-xs"
              title="Center"
            >
              <div class="w-4 h-3 bg-gray-400 rounded mx-auto mb-1"></div>
              <span>Center</span>
            </button>

            <!-- Full Width -->
            <button
              type="button"
              @click="selectedImagePosition = 'full-width'"
              :class="{
                'bg-indigo-100 border-indigo-500': selectedImagePosition === 'full-width',
                'bg-gray-50 border-gray-300': selectedImagePosition !== 'full-width'
              }"
              class="p-3 border-2 rounded-lg hover:bg-gray-100 flex flex-col items-center text-xs"
              title="Full Width"
            >
              <div class="w-full h-3 bg-gray-400 rounded mb-1"></div>
              <span>Full</span>
            </button>
          </div>
        </div>

        <!-- Image Size Controls -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Image Size</label>
          <div class="flex items-center gap-2 flex-wrap">
            <select v-model="sizePreset" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option value="original">Original</option>
              <option value="small">Small (200px)</option>
              <option value="medium">Medium (400px)</option>
              <option value="large">Large (600px)</option>
              <option value="custom">Custom…</option>
            </select>
            <div v-if="sizePreset === 'custom'" class="flex items-center gap-2">
              <div class="flex items-center gap-1">
                <span class="text-xs text-gray-600">W</span>
                <input type="number" min="10" max="2000" step="1" v-model.number="customImageWidth" class="w-20 rounded border-gray-300 text-sm" placeholder="px" />
              </div>
              <label class="flex items-center gap-1 text-xs text-gray-700">
                <input type="checkbox" v-model="imageLockRatio" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                Lock ratio
              </label>
              <div class="flex items-center gap-1" v-if="!imageLockRatio">
                <span class="text-xs text-gray-600">H</span>
                <input type="number" min="10" max="2000" step="1" v-model.number="customImageHeight" class="w-20 rounded border-gray-300 text-sm" placeholder="px" />
              </div>
            </div>
          </div>
        </div>

        <!-- File Upload -->
        <div class="mb-4">
          <label for="image-upload" class="block text-sm font-medium text-gray-700 mb-1">
            Upload Images <span class="text-red-500">*</span>
          </label>
          <input
            id="image-upload"
            type="file"
            multiple
            accept="image/*"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            @change="handleImageFileSelection"
          />
          <p class="mt-1 text-xs text-gray-500">Max 15MB per file. Supports JPG, PNG, GIF, WebP, SVG</p>
          
          <div v-if="isImageUploading" class="mt-2 text-sm text-gray-600">Uploading… please wait</div>
          <div v-if="imageUploadError" class="mt-2 text-sm text-red-600">{{ imageUploadError }}</div>
        </div>

        <!-- Uploaded Images List -->
        <div v-if="imageUploadedUrls.length" class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Select Image to Insert</label>
          <div class="max-h-32 overflow-y-auto border rounded-md divide-y">
            <div v-for="item in imageUploadedUrls" :key="item.url" class="flex items-center justify-between px-3 py-2">
              <div class="flex items-center">
                <img :src="item.url" :alt="item.name" class="w-8 h-8 object-cover rounded mr-3" />
                <span class="text-sm truncate">{{ item.name }}</span>
              </div>
              <button
                type="button"
                @click="insertImage(item.url)"
                class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
              >
                Insert
              </button>
            </div>
          </div>
        </div>

        <!-- Dialog Actions -->
        <div class="flex justify-end space-x-3">
          <button
            type="button"
            @click="resetImageDialog"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Image Paste Prevention Toast -->
    <div v-if="showImagePasteToast" class="fixed top-4 right-4 z-50 max-w-sm">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 shadow-lg">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <h3 class="text-sm font-medium text-blue-800">
              Image Upload Required
            </h3>
            <div class="mt-1 text-sm text-blue-700">
              <p>Please use the Image Upload button instead of pasting images directly. This helps keep your newsletter optimized!</p>
            </div>
          </div>
          <div class="ml-4 flex-shrink-0">
            <button
              type="button"
              @click="hideImagePasteToast"
              class="inline-flex text-blue-400 hover:text-blue-600 focus:outline-none focus:text-blue-600"
            >
              <span class="sr-only">Close</span>
              <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.ProseMirror {
  outline: none;
  /* Use a fixed min-height so clicks in empty space are within the editable area */
  min-height: 300px;
}

.ProseMirror p {
  margin: 1em 0;
}

/* Drop cap styles */
.ProseMirror p.has-dropcap:first-letter {
  float: left;
  font-size: 3.5em;
  line-height: 0.8;
  margin: 0.1em 0.2em 0 0;
  color: #333;
  font-weight: bold;
  text-transform: uppercase;
}

.ProseMirror p.has-dropcap {
  overflow: hidden; /* Contains the floated drop cap */
}

/* Mobile styles */
@media screen and (max-width: 600px) {
  .ProseMirror p.has-dropcap:first-letter {
    font-size: 2.5em;
    line-height: 1;
  }
}

.ProseMirror img {
  max-width: 100%;
  height: auto;
}

.ProseMirror img.ProseMirror-selectednode {
  outline: 3px solid #3b82f6;
}

.ProseMirror p {
  /* “extra row” between paragraphs */
  margin: 0 0 1rem; /* adjust to taste: 1rem–1.5rem */
  line-height: 1.7; /* optional: improve readability */
}

.ProseMirror p:last-child {
  margin-bottom: 0; /* avoid trailing extra space */
}
</style>
