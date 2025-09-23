<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TicketEditor from '@/Components/WYSIWYG/TicketEditor.vue';
import FileUploader from '@/Components/FileUploader.vue';
import MultiSelectCheckbox from '@/Components/MultiSelectCheckbox.vue';
import AnnotationInterface from '@/Components/Annotation/AnnotationInterface.vue';
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

// Track created ticket for annotations
const createdTicket = ref(null);

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
                                <InputLabel class="text-uh-slate dark:text-uh-cream mb-2" value="Attachments" />
                                <FileUploader
                                    :temp-mode="true"
                                    :existing-files="tempFiles"
                                    @uploaded="handleFilesUploaded"
                                    @removed="handleFileRemoved"
                                    class="mt-2"
                                />
                                <InputError :message="form.errors.temp_file_ids" class="mt-2" />
                            </div>

                            <!-- Annotation System -->
                            <div class="mt-6">
                                <InputLabel class="text-uh-slate dark:text-uh-cream mb-2" value="Image Annotations" />
                                <template v-if="!createdTicket">
                                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg mb-4">
                                        <div class="flex items-start">
                                            <i class="fas fa-info-circle text-blue-500 mr-2 mt-0.5"></i>
                                            <span class="text-blue-700 dark:text-blue-300">
                                                Please create the ticket first to enable image annotations. After you click "Create Ticket", this section will unlock so you can add screenshots or upload files and annotate them.
                                            </span>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                            <span class="text-green-700 dark:text-green-300 font-medium">
                                                Ticket created successfully! You can now add image annotations.
                                            </span>
                                        </div>
                                    </div>
                                    <AnnotationInterface
                                        :ticket-id="createdTicket.id"
                                        :can-review-annotations="false"
                                        :readonly="false"
                                        :allow-delete="false"
                                    />
                                </template>
                            </div>

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
