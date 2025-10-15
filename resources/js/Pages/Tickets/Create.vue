<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TicketEditor from '@/Components/WYSIWYG/TicketEditor.vue';
import FileUploader from '@/Components/FileUploader.vue';
import MultiSelectCheckbox from '@/Components/MultiSelectCheckbox.vue';
import ProofUploadModal from '@/Components/ProofUploadModal.vue';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    priorities: {
        type: Array,
        default: () => ['Low', 'Medium', 'High'],
    },
    users: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    title: '',
    description: '',
    priority: 'Medium',
    due_date: '',
    assigned_user_ids: [],
    temp_file_ids: [],
    temp_image_ids: [],
});

// Track Tailwind dark mode to sync with the datepicker theme
const isDark = ref(false);
let darkObserver;

onMounted(() => {
    const updateDark = () => {
        isDark.value = document.documentElement.classList.contains('dark');
    };
    updateDark();
    darkObserver = new MutationObserver(updateDark);
    darkObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

onUnmounted(() => {
    if (darkObserver) darkObserver.disconnect();
});

// Store temporary file IDs that need to be associated with the ticket after creation
const tempFiles = ref([]);

// Store temporary proof image IDs
const tempImageIds = ref([]);

// Track created ticket for annotations
const createdTicket = ref(null);

// Proof modal state
const showProofModal = ref(false);

// Temp proof images
const tempProofImages = ref([]);

const loadTempProofImages = async () => {
    try {
        const response = await axios.get('/api/temp-images');
        tempProofImages.value = response.data.data || [];
        // Update form with temp image IDs
        form.temp_image_ids = tempProofImages.value.map(img => img.id);
    } catch (error) {
        console.error('Failed to load temp proof images:', error);
    }
};

onMounted(() => {
    loadTempProofImages();
});

const handleFilesUploaded = (files) => {
    // Store the file IDs to be associated with the ticket and update previews
    files.forEach(file => {
        form.temp_file_ids.push(file.id);
    });
    // Merge newly uploaded files into the tempFiles list for preview
    tempFiles.value = [...tempFiles.value, ...files];
};

const handleFileRemoved = (file) => {
    // Remove the file ID from the temp files array
    form.temp_file_ids = form.temp_file_ids.filter(id => id !== file.id);
    // Also remove the file object from the tempFiles previews
    tempFiles.value = tempFiles.value.filter(f => f.id !== file.id);
};

const handleTempImagesUpdated = (imageIds) => {
    // Update the temp image IDs to be associated with the ticket
    tempImageIds.value = imageIds;
    form.temp_image_ids = imageIds;
};

const handleProofUploaded = async () => {
    // Reload proof images after upload
    await loadTempProofImages();
};

const deleteTempProofImage = async (imageId) => {
    if (!confirm('Are you sure you want to delete this proof image?')) {
        return;
    }
    try {
        await axios.delete(`/api/temp-images/${imageId}`);
        await loadTempProofImages();
    } catch (error) {
        console.error('Failed to delete temp proof image:', error);
        alert('Failed to delete proof image. Please try again.');
    }
};

// Handle form submission
const submit = () => {
    form.post(route('tickets.store'), {
        onSuccess: (page) => {
            // Extract the created ticket from the response
            if (page.props && page.props.ticket) {
                createdTicket.value = page.props.ticket;
            }
            // Don't reset form immediately to allow annotations
        },
        onError: () => {
            // Handle errors
        }
    });
};

// Cancel and go back
const cancel = () => {
    router.visit(route('tickets.index'));
};
</script>

<template>
    <Head title="Create New Ticket" />

    <AuthenticatedLayout>
        <template #header>
            
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Title -->
                            <div>
                                <InputLabel class="text-uh-slate dark:text-uh-cream" for="title" value="Title" />
                                <TextInput
                                    id="title"
                                    type="text"
                                    class="mt-1 block w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-uh-slate dark:text-uh-cream"
                                    v-model="form.title"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <!-- Priority -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel class="text-uh-slate dark:text-uh-cream" for="priority" value="Priority" />
                                    <select
                                        id="priority"
                                        v-model="form.priority"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-uh-teal dark:focus:border-uh-teal focus:ring-uh-teal dark:focus:ring-uh-teal rounded-md shadow-sm"
                                    >
                                        <option v-for="priority in priorities" :key="priority" :value="priority">
                                            {{ priority }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.priority" />
                                </div>
                                <!-- Due Date -->
                                <div>
                                    <InputLabel class="text-uh-slate dark:text-uh-cream" for="due_date" value="Due Date" />
                                    <Datepicker
                                        id="due_date"
                                        v-model="form.due_date" 
                                        model-type="yyyy-MM-dd"
                                        :enable-time-picker="false"
                                        :dark="isDark"
                                        :teleport="true"
                                        :auto-apply="true"
                                        :hide-input-icon="false"
                                        input-class-name="mt-1 block w-full bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-300 placeholder-gray-500 dark:placeholder-gray-400 focus:border-uh-teal dark:focus:border-uh-teal focus:ring-uh-teal dark:focus:ring-uh-teal rounded-md shadow-sm h-10 px-3"
                                        placeholder="Select a due date"
                                    />
                                    <InputError class="mt-2" :message="form.errors.due_date" />
                                </div>
                            </div>

                            <!-- Assign User -->
                            <div>
                                <InputLabel class="text-uh-slate dark:text-uh-cream" for="assigned_user_ids" value="Assign To" />
                                <MultiSelectCheckbox
                                    id="assigned_user_ids"
                                    v-model="form.assigned_user_ids"
                                    :options="users"
                                    label-key="name"
                                    value-key="id"
                                    placeholder="Select assignees"
                                />
                                <InputError class="mt-2" :message="form.errors.assigned_user_ids" />
                            </div>

                            <!-- Description (Tiptap Editor) -->
                            <div>
                                <TicketEditor
                                    v-model="form.description"
                                    :error="form.errors.description"
                                    label="Description"
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <!-- File Uploader -->
                            <div class="mt-6">
                                <FileUploader
                                    :temp-mode="true"
                                    :existing-files="tempFiles"
                                    @uploaded="handleFilesUploaded"
                                    @removed="handleFileRemoved"
                                    class="mt-2"
                                />
                                <InputError :message="form.errors.temp_file_ids" class="mt-2" />
                            </div>

                            <!-- Proof Images (Temp Mode) -->
                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-2">
                                    <PrimaryButton
                                        type="button"
                                        @click="showProofModal = true"
                                    >
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Proof
                                    </PrimaryButton>
                                </div>
                                
                                
                                <!-- Proof Images List -->
                                <div v-if="tempProofImages.length > 0" class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    <div 
                                        v-for="image in tempProofImages" 
                                        :key="image.id" 
                                        class="relative group bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-500 transition-colors"
                                    >
                                        <div class="aspect-square p-2">
                                            <img 
                                                v-if="image.status === 'completed'"
                                                :src="image.image_url" 
                                                :alt="image.name || image.original_name || 'Proof image'" 
                                                class="w-full h-full object-cover rounded"
                                            />
                                            <div v-else-if="image.status === 'processing'" class="w-full h-full flex items-center justify-center">
                                                <div class="text-center">
                                                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Processing...</p>
                                                </div>
                                            </div>
                                            <div v-else class="w-full h-full flex items-center justify-center">
                                                <p class="text-xs text-red-500">Failed</p>
                                            </div>
                                        </div>
                                        <div class="p-2">
                                            <p class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" :title="image.name || image.original_name">
                                                {{ image.name || image.original_name || 'Untitled' }}
                                            </p>
                                        </div>
                                        <!-- Delete button -->
                                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-600/90 hover:bg-red-600 text-white shadow"
                                                title="Delete proof"
                                                @click="deleteTempProofImage(image.id)"
                                            >
                                                <font-awesome-icon icon="trash" class="text-xs" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                               
                            </div>

                            <!-- Proof Upload Modal -->
                            <ProofUploadModal
                                :show="showProofModal"
                                :temp-mode="true"
                                @close="showProofModal = false"
                                @uploaded="handleProofUploaded"
                            />

                            <!-- Submit Button -->
                            <div class="flex items-center justify-end mt-6 space-x-4">
                                <SecondaryButton type="button" @click="cancel" :disabled="form.processing">
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton 
                                    type="submit"
                                    :class="{ 'opacity-25': form.processing }" 
                                    :disabled="form.processing"
                                >
                                    Create Ticket
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Basic editor styles */
.ProseMirror {
    outline: none;
    min-height: 150px;
}

.ProseMirror p {
    margin: 0.5em 0;
}

.ProseMirror ul,
.ProseMirror ol {
    padding: 0 1rem;
    margin: 0.5em 0;
}

.ProseMirror h1,
.ProseMirror h2,
.ProseMirror h3,
.ProseMirror h4,
.ProseMirror h5,
.ProseMirror h6 {
    line-height: 1.1;
    margin: 1em 0 0.5em 0;
}

.ProseMirror h1 { font-size: 2em; }
.ProseMirror h2 { font-size: 1.5em; }
.ProseMirror h3 { font-size: 1.17em; }
.ProseMirror h4 { font-size: 1em; }
.ProseMirror h5 { font-size: 0.83em; }
.ProseMirror h6 { font-size: 0.67em; }

.ProseMirror code {
    background-color: rgba(97, 97, 97, 0.1);
    border-radius: 0.25em;
    box-decoration-break: clone;
    color: #616161;
    font-size: 0.9rem;
    padding: 0.25em;
}

.dark .ProseMirror code {
    background-color: rgba(255, 255, 255, 0.1);
    color: #e0e0e0;
}

.ProseMirror pre {
    background: #0d0d0d;
    border-radius: 0.5rem;
    color: #fff;
    font-family: 'JetBrainsMono', monospace;
    padding: 0.75rem 1rem;
    margin: 0.5em 0;
}

.ProseMirror pre code {
    background: none;
    color: inherit;
    font-size: 0.8rem;
    padding: 0;
}

.ProseMirror blockquote {
    border-left: 3px solid #999;
    padding-left: 1rem;
    margin: 0.5em 0;
    font-style: italic;
}

.ProseMirror hr {
    border: none;
    border-top: 2px solid #999;
    margin: 1rem 0;
}
</style>
