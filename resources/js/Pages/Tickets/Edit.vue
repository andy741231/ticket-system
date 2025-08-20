<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { watch, ref, onMounted, onUnmounted, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TicketEditor from '@/Components/WYSIWYG/TicketEditor.vue';
import FileUploader from '@/Components/FileUploader.vue';
import MultiSelectCheckbox from '@/Components/MultiSelectCheckbox.vue';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    priorities: {
        type: Array,
        default: () => ['Low', 'Medium', 'High'],
    },
    statuses: {
        type: Array,
        default: () => ['Received', 'Approved', 'Rejected', 'Completed'],
    },
    users: {
        type: Array,
        required: true,
    },
});

// Normalize date for <input type="date"> as YYYY-MM-DD in local time
const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    const d = new Date(dateString);
    if (isNaN(d)) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const form = useForm({
    title: props.ticket.title,
    description: props.ticket.description,
    priority: props.ticket.priority,
    status: props.ticket.status,
    due_date: formatDateForInput(props.ticket.due_date) || '',
    assigned_user_ids: (props.ticket.assignees || []).map(u => u.id),
    _method: 'PUT',
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

// Store the existing files for the ticket
const existingFiles = ref(props.ticket.files || []);

// Handle when files are successfully uploaded
const handleFilesUploaded = (files) => {
    console.log('Files uploaded:', files);
    // Add the new files to the existing files array
    const newFiles = [...existingFiles.value, ...files];
    console.log('New files array:', newFiles);
    existingFiles.value = newFiles;
};

// Handle when a file is removed
const handleFileRemoved = (file) => {
    console.log('Removing file:', file);
    // Remove the file from the existing files array
    const filteredFiles = existingFiles.value.filter(f => f.id !== file.id);
    console.log('Files after removal:', filteredFiles);
    existingFiles.value = filteredFiles;
};

// Debug log for initial props and existingFiles
console.log('Initial ticket files:', props.ticket.files);
console.log('Initial existingFiles ref:', existingFiles.value);

// Add a watcher to track changes to existingFiles
watch(existingFiles, (newVal) => {
    console.log('existingFiles changed:', newVal);
}, { deep: true });

// Update form when ticket prop changes
watch(() => props.ticket, (newTicket) => {
    if (newTicket) {
        form.title = newTicket.title;
        form.priority = newTicket.priority;
        form.status = newTicket.status;
        form.description = newTicket.description;
        form.due_date = formatDateForInput(newTicket.due_date) || '';
        form.assigned_user_ids = (newTicket.assignees || []).map(u => u.id);
    }
}, { deep: true });

// Permission-based UI gating for status field
const canChangeStatus = useHasAny(['tickets.ticket.manage', 'tickets.ticket.update']);

const submit = () => {
    form.put(route('tickets.update', props.ticket.id), {
        onSuccess: () => {
            // Redirect to the ticket show page after successful update
            router.visit(route('tickets.show', props.ticket.id), {
                preserveScroll: true,
                onSuccess: () => {
                    // Clear any form state if needed
                    form.reset();
                },
            });
        },
        preserveScroll: true,
        preserveState: true,
    });
};

// Cancel and go back to the ticket view
const cancel = () => {
    router.visit(route('tickets.show', props.ticket.id), {
        preserveScroll: true,
        preserveState: true
    });
};


</script>

<template>
    <Head :title="`Edit Ticket #${props.ticket.id}`" />

    <AuthenticatedLayout>
        <template #header>
            
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 text-uh-slate dark:text-uh-cream overflow-hidden shadow-sm sm:rounded-lg">
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

                                <!-- Status (requires manage or update permission) -->
                                <div v-if="canChangeStatus">
                                    <InputLabel class="text-uh-slate dark:text-uh-cream" for="status" value="Status" />
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-uh-teal dark:focus:border-uh-teal focus:ring-uh-teal dark:focus:ring-uh-teal rounded-md shadow-sm"
                                    >
                                        <option v-for="status in statuses" :key="status" :value="status">
                                            {{ status }}
                                        </option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.status" />
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
                                        input-class-name="mt-1 block w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-uh-slate dark:text-uh-cream rounded-md shadow-sm"
                                        placeholder="Select a due date"
                                    />
                                    <InputError class="mt-2" :message="form.errors.due_date" />
                                </div>
                                <!-- Assign User -->
                            <div class="">
                                <InputLabel class="text-uh-slate dark:text-uh-cream" for="assigned_user_ids" value="Assign To" />
                                <MultiSelectCheckbox
                                    id="assigned_user_ids"
                                    v-model="form.assigned_user_ids"
                                    :options="props.users"
                                    label-key="name"
                                    value-key="id"
                                    placeholder="Select assignees"
                                />
                                <InputError class="mt-2" :message="form.errors.assigned_user_ids" />
                            </div>
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
                                    :ticket-id="props.ticket.id"
                                    :existing-files="existingFiles"
                                    @uploaded="handleFilesUploaded"
                                    @removed="handleFileRemoved"
                                    class="mb-4"
                                />
                                
                                
                                
                                <InputError :message="form.errors.files" class="mt-2" />
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
                                    Update Ticket
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.ProseMirror {
    outline: none;
}

:deep(.ProseMirror) {
    min-height: 300px;
    padding: 0.75rem;
    border-radius: 0 0 0.375rem 0.375rem;
    background-color: white;
    color: #1f2937;
}

.dark :deep(.ProseMirror) {
    background-color: #111827;
    color: #f9fafb;
}


:deep(.ProseMirror:focus) {
    outline: none;
}

/* Basic Typography */
:deep(.ProseMirror p) {
    margin-bottom: 1rem;
}

:deep(.ProseMirror h1),
:deep(.ProseMirror h2),
:deep(.ProseMirror h3),
:deep(.ProseMirror h4),
:deep(.ProseMirror h5),
:deep(.ProseMirror h6) {
    line-height: 1.2;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

:deep(.ProseMirror h1) { font-size: 2.25rem; }
:deep(.ProseMirror h2) { font-size: 1.875rem; }
:deep(.ProseMirror h3) { font-size: 1.5rem; }
:deep(.ProseMirror h4) { font-size: 1.25rem; }
:deep(.ProseMirror h5) { font-size: 1.125rem; }
:deep(.ProseMirror h6) { font-size: 1rem; }

/* Lists */
:deep(.ProseMirror ul),
:deep(.ProseMirror ol) {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}
:deep(.ProseMirror ul) {
    list-style-type: disc;
}
:deep(.ProseMirror ol) {
    list-style-type: decimal;
}

/* Basic editor styles */
.ProseMirror {
    outline: none;
    min-height: 150px;
}

.ProseMirror a {
    color: #007BFF;
    text-decoration: underline;
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

/* Code Blocks */
:deep(.ProseMirror pre) {
    background: #1f2937; /* bg-gray-800 */
    color: #f9fafb;
    font-family: 'JetBrainsMono', monospace;
    padding: 1rem;
    border-radius: 0.5rem;
    margin: 1rem 0;
    white-space: pre-wrap;
}
.dark :deep(.ProseMirror pre) {
    background: #111827; /* dark:bg-gray-900 */
}
:deep(.ProseMirror pre code) {
    background: none;
    color: inherit;
    padding: 0;
    font-size: 0.85em;
}

/* Horizontal Rule */
:deep(.ProseMirror hr) {
    border: none;
    border-top: 1px solid #d1d5db; /* border-gray-300 */
    margin: 2rem 0;
}
.dark :deep(.ProseMirror hr) {
    border-top-color: #4b5563; /* dark:border-gray-600 */
}

/* Tables */
:deep(.ProseMirror table) {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}
:deep(.ProseMirror th),
:deep(.ProseMirror td) {
    border: 1px solid #d1d5db; /* border-gray-300 */
    padding: 0.5rem 0.75rem;
    vertical-align: top;
}
.dark :deep(.ProseMirror th),
.dark :deep(.ProseMirror td) {
    border-color: #4b5563; /* dark:border-gray-600 */
}
:deep(.ProseMirror th) {
    font-weight: bold;
    background-color: #f9fafb; /* bg-gray-50 */
}
.dark :deep(.ProseMirror th) {
    background-color: #374151; /* dark:bg-gray-700 */
}

/* Task Lists */
:deep(ul[data-type="taskList"]) {
    list-style: none;
    padding: 0;
}

:deep(li[data-type="taskItem"]) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

:deep(li[data-type="taskItem"] > label) {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
}

:deep(li[data-type="taskItem"] > div) {
    flex: 1 1 auto;
}

:deep(li[data-type="taskItem"] input[type="checkbox"]) {
    cursor: pointer;
    width: 1rem;
    height: 1rem;
}

:deep(li[data-type="taskItem"][data-checked="true"]) > div > p {
    text-decoration: line-through;
    color: #9ca3af;
}
.dark :deep(li[data-type="taskItem"][data-checked="true"]) > div > p {
    color: #6b7280;
}
</style>
