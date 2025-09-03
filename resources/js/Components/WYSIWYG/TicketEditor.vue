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
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
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
    faHighlighter
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
    faHighlighter
);

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: 'Description',
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

// Toast notification state
const showImagePasteToast = ref(false);

// Reactive data for format dropdown
const currentFormat = ref('paragraph');



const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3]
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
        Underline,
        TiptapLink.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-blue-600 hover:underline',
            },
        }),
        // Remove Image extension to prevent image pasting
        // Image,
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
watch(() => {
    if (!editor || !editor.value) return null;
    return editor.value.getAttributes('heading')?.level;
}, (level) => {
    if (level === undefined) return;
    currentFormat.value = level ? `heading${level}` : 'paragraph';
});

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
watch(() => props.modelValue, (newValue, oldValue) => {
    if (!editor?.value || !newValue || newValue === oldValue) return;
    
    const isSame = editor.value.getHTML() === newValue;
    if (isSame) return;
    
    editor.value.commands.setContent(newValue, false);
}, { immediate: true });

// Link dialog state
const showLinkDialog = ref(false);
const linkUrl = ref('');
const linkText = ref('');
const openInNewTab = ref(true);
let isEditingLink = false;

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
        editor.value.chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ 
                href: formattedUrl, 
                target: openInNewTab.value ? '_blank' : null,
                rel: 'noopener noreferrer' // Security best practice
            })
            .run();
    }
    
    resetLinkDialog();
};

const resetLinkDialog = () => {
    showLinkDialog.value = false;
    linkUrl.value = '';
    linkText.value = '';
    openInNewTab.value = true;
    isEditingLink = false;
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
        editor.value.chain()
            .focus()
            .command(({ tr }) => {
                tr.setNodeMarkup($from.before(), undefined, {
                    ...node.attrs,
                    indent: newIndent
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
        editor.value.chain()
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

onBeforeUnmount(() => {
    if (editor?.value) {
        editor.value.destroy();
    }
});
</script>

<template>
    <div class="w-full">
        <InputLabel v-if="label" :for="$attrs.id || 'editor'" :value="label" class="mb-1 text-uh-slate dark:text-uh-cream" />
        <div class="mt-1">
            <div class="border border-gray-300 dark:border-gray-700 rounded-md shadow-sm overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
                <!-- Menu Bar -->
                <div v-if="editor" class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-2 flex flex-wrap items-center gap-2 text-gray-700 dark:text-gray-300">
                    <!-- Text Format Dropdown -->
                    <div class="flex items-center">
                        <select 
                            v-model="currentFormat" 
                            @change="updateFormat" 
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 h-9"
                        >
                            
                            <option value="heading1">Heading 1</option>
                            <option value="heading2">Heading 2</option>
                            <option value="heading3">Heading 3</option>
                            <option selected value="paragraph">Paragraph</option>
                        </select>
                    </div>

                    <!-- Basic Formatting -->
                    <div class="flex items-center gap-1">
                        <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('bold')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Bold (Ctrl/Cmd + B)">
                            <font-awesome-icon :icon="['fas', 'bold']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('italic')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Italic (Ctrl/Cmd + I)">
                            <font-awesome-icon :icon="['fas', 'italic']" class="w-5 h-5" />
                        </button>
                         <!-- Text Decoration -->
                        <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('underline')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Underline (Ctrl/Cmd + U)">
                            <font-awesome-icon :icon="['fas', 'underline']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('strike')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Strikethrough (Ctrl/Cmd + Shift + X)">
                            <font-awesome-icon :icon="['fas', 'strikethrough']" class="w-5 h-5" />
                        </button>
                    </div>
                
                    <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Alignment -->
                    <div class="flex items-center gap-1">
                        <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'left' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Align Left (Ctrl/Cmd + Shift + L)">
                            <font-awesome-icon :icon="['fas', 'align-left']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'center' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Align Center (Ctrl/Cmd + Shift + E)">
                            <font-awesome-icon :icon="['fas', 'align-center']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'right' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Align Right (Ctrl/Cmd + Shift + R)">
                            <font-awesome-icon :icon="['fas', 'align-right']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'justify' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Justify (Ctrl/Cmd + Shift + J)">
                            <font-awesome-icon :icon="['fas', 'align-justify']" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Text Color & Highlight -->
                    <div class="flex items-center gap-1">
                        <!-- Highlight Button with Color Picker -->
                        <div class="relative group">
                            <button 
                                type="button" 
                                class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
                                :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('highlight')}"
                                title="Highlight Text (Ctrl/Cmd + Alt + H)"
                            >
                                <font-awesome-icon :icon="['fas', 'highlighter']" class="w-5 h-5" />
                            </button>
                            <!-- Color Picker Dropdown -->
                            <div class="absolute z-10 hidden group-hover:block bg-white dark:bg-gray-700 shadow-lg rounded-md p-2 w-48 right-0">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1 mt-2">Highlight Color</div>
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
                        <!-- Text Color Button with Color Picker -->
                        <div class="relative group">
                            <button type="button" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('textStyle')}" title="Text Color">
                                <font-awesome-icon :icon="['fas', 'font']" class="w-5 h-5" />
                            </button>
                            <!-- Color Picker Dropdown -->
                            <div class="absolute z-10 hidden group-hover:block bg-white dark:bg-gray-700 shadow-lg rounded-md p-2 w-48">
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1 mt-2">Text Color</div>
                                
                                <div class="mt-2 flex gap-1">
                                    <button type="button" @click="editor.chain().focus().setColor('#000000').run()" class="w-5 h-5 rounded-full bg-black border border-gray-300"></button>
                                    <button type="button" @click="editor.chain().focus().setColor('#ef4444').run()" class="w-5 h-5 rounded-full bg-red-500"></button>
                                    <button type="button" @click="editor.chain().focus().setColor('#3b82f6').run()" class="w-5 h-5 rounded-full bg-blue-500"></button>
                                    <button type="button" @click="editor.chain().focus().setColor('#10b981').run()" class="w-5 h-5 rounded-full bg-emerald-500"></button>
                                    <button type="button" @click="editor.chain().focus().setColor('#f59e0b').run()" class="w-5 h-5 rounded-full bg-yellow-500"></button>
                                    <button type="button" @click="editor.chain().focus().setColor('#8b5cf6').run()" class="w-5 h-5 rounded-full bg-violet-500"></button>
                                </div>
                                <div class="mt-2">
                                    <input type="color" @input="editor.chain().focus().setColor($event.target.value).run()" value="#000000" class="w-full h-8 cursor-pointer rounded border border-gray-300" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lists & Indentation -->
                    <div class="flex items-center gap-1">
                        <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('bulletList')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Bullet List (Ctrl/Cmd + Shift + 8)">
                            <font-awesome-icon :icon="['fas', 'list-ul']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('orderedList')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Numbered List (Ctrl/Cmd + Shift + 7)">
                            <font-awesome-icon :icon="['fas', 'list-ol']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('blockquote')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Blockquote (Ctrl/Cmd + Shift + B)">
                            <font-awesome-icon :icon="['fas', 'quote-right']" class="w-5 h-5" />
                        </button>
                        <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 mx-1"></div>
                        <button type="button" @click="handleIndent" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Indent (Tab)">
                            <font-awesome-icon :icon="['fas', 'indent']" class="w-5 h-5" />
                        </button>
                        <button type="button" @click="handleOutdent" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Outdent (Shift + Tab)">
                            <font-awesome-icon :icon="['fas', 'outdent']" class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Links & Media -->
                    <div class="flex items-center gap-1">
                        <button type="button" @click="setLink" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('link')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Add Link">
                            <font-awesome-icon :icon="['fas', 'link']" class="w-5 h-5" />
                        </button>
                        <!-- <button type="button" @click="addImage" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Insert Image">
                            <font-awesome-icon :icon="['fas', 'image']" class="w-5 h-5" />
                        </button> -->
                    </div>
                </div>
                
                <!-- Editor Content -->
                <div class="p-4 bg-white dark:bg-gray-900 min-h-[300px] max-h-[600px] overflow-y-auto focus:outline-none" :class="{ 'border-red-500': error }">
                    <EditorContent 
                        :editor="editor" 
                        class="prose dark:prose-invert max-w-none focus:outline-none"
                        :class="{ 'border-red-500': error }"
                    />
                </div>
            </div>
        </div>

        <!-- Link Dialog -->
        <div v-if="showLinkDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="resetLinkDialog">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 mx-4" @keydown.esc="resetLinkDialog">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ isEditingLink ? 'Edit Link' : 'Insert Link' }}</h3>
                
                <div class="">
                    <div>
                        <label for="link-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            URL <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="link-url"
                            v-model="linkUrl"
                            type="text"
                            placeholder="Enter URL or search terms"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keydown.enter.prevent="confirmLink"
                            autofocus
                        >
                    </div>
                    
                    <div class="flex items-center">
                        <input
                            id="open-new-tab"
                            v-model="openInNewTab"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <label for="open-new-tab" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Open in new tab
                        </label>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="resetLinkDialog"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-600 dark:text-white border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
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
                            'bg-gray-400': !linkUrl
                        }"
                        class="px-4 py-2 text-sm font-medium text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ isEditingLink ? 'Update' : 'Insert' }} Link
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Paste Prevention Toast -->
        <div v-if="showImagePasteToast" class="fixed top-4 right-4 z-50 max-w-sm">
            <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 shadow-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            Image Upload Required
                        </h3>
                        <div class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                            <p>Please use the proper file attachment feature instead of pasting images directly. This helps keep your tickets organized!</p>
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
    min-height: 200px;
}

.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: #9ca3af;
    pointer-events: none;
    height: 0;
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
