<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FileUploader from '@/Components/FileUploader.vue';
import FileIcon from '@/Components/FileIcon.vue';
import { ref, computed } from 'vue';
import { useHasAny } from '@/Extensions/useAuthz';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        default: () => ({
            update: false,
            delete: false,
            changeStatus: false,
            changeStatusAll: false,
        }),
    },
    isAssignee: {
        type: Boolean,
        default: false,
    },
    authUserId: {
        type: Number,
        default: null,
    },
});

const canManageTickets = useHasAny(['tickets.ticket.manage']);

// Debug: Log ticket data to console
console.log('Ticket data:', props.ticket);
console.log('Attachments:', props.ticket.attachments);

const statusClasses = {
    'Received': 'dark:text-uh-cream bg-uh-teal/20 text-uh-forest',
    'Approved': 'dark:text-uh-cream bg-uh-gold/20 text-uh-ocher',
    'Rejected': 'dark:text-uh-cream bg-uh-red/20 text-uh-brick',
    'Completed': 'dark:text-uh-cream bg-uh-green/20 text-uh-forest',
};

const priorityClasses = {
    'Low': 'dark:text-uh-cream bg-uh-slate/20 text-uh-slate',
    'Medium': 'dark:text-uh-cream bg-uh-teal/20 text-uh-forest',
    'High': 'dark:text-uh-cream bg-uh-red/20 text-uh-brick',
};

const statusOptions = {
    'Approved': 'Approve',
    'Rejected': 'Reject',
    'Completed': 'Mark as Completed',
};

// Computed: allowed options based on permissions
const filteredStatusOptions = computed(() => {
    if (props.can?.changeStatusAll) {
        return statusOptions;
    }
    // If user is an assignee (but not full control), only allow completion
    if (props.isAssignee) {
        return { 'Completed': statusOptions['Completed'] };
    }
    return {};
});

const formatDate = (dateString) => {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
    };
    return new Date(dateString).toLocaleDateString(undefined, options);
};
import Modal from '@/Components/Modal.vue';

const showDeleteModal = ref(false);

const deleteTicket = () => {
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    showDeleteModal.value = false;
    router.delete(route('tickets.destroy', props.ticket.id));
};

const updateStatus = (status) => {
    if (confirm(`Are you sure you want to ${status.toLowerCase()} this ticket?`)) {
        router.put(route('tickets.status.update', { 
            ticket: props.ticket.id,
            status: status
        }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Refresh the page to show updated status
                router.reload({ only: ['ticket'] });
            }
        });
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const getFileType = (mimeType) => {
    const typeMap = {
        // Images
        'image/jpeg': 'JPEG Image',
        'image/png': 'PNG Image',
        'image/gif': 'GIF Image',
        'image/svg+xml': 'SVG Image',
        'image/webp': 'WebP Image',
        
        // Documents
        'application/pdf': 'PDF Document',
        'application/msword': 'Word Document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word Document',
        'application/vnd.ms-excel': 'Excel Spreadsheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel Spreadsheet',
        'application/vnd.ms-powerpoint': 'PowerPoint Presentation',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint Presentation',
        'text/plain': 'Text File',
        'text/csv': 'CSV File',
        
        // Archives
        'application/zip': 'ZIP Archive',
        'application/x-rar-compressed': 'RAR Archive',
        'application/x-7z-compressed': '7z Archive',
        'application/x-tar': 'TAR Archive',
        'application/gzip': 'GZIP Archive',
        
        // Code
        'application/json': 'JSON File',
        'application/xml': 'XML File',
        'text/html': 'HTML File',
        'text/css': 'CSS File',
        'application/javascript': 'JavaScript File',
        
        // Default
        'application/octet-stream': 'File'
    };
    
    return typeMap[mimeType] || mimeType.split('/').pop().toUpperCase() + ' File';
};

// Helpers for attachment previews
const isImage = (mime) => !!mime && mime.startsWith('image/');
const getFileUrl = (file) => {
    if (!file) return '#';
    return '/storage/' + file.file_path;
};

// Ticket URL and clipboard helpers
const ticketUrl = computed(() => {
    // Ensure absolute URL whether Ziggy returns relative or absolute
    try {
        return new URL(route('tickets.show', props.ticket.id), window.location.origin).toString();
    } catch (e) {
        // Fallback to current origin + pathname
        return `${window.location.origin}${route('tickets.show', props.ticket.id)}`;
    }
});

const copied = ref(false);
const copyTicketUrl = async () => {
    try {
        await navigator.clipboard.writeText(ticketUrl.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch (err) {
        console.error('Failed to copy ticket URL:', err);
    }
};

// Local UI state for status changes from the description header toolbar
const selectedStatus = ref('');
const applyStatus = () => {
    if (selectedStatus.value) {
        updateStatus(selectedStatus.value);
        selectedStatus.value = '';
    }
};

// Comments state and actions
const newComment = ref('');
const posting = ref(false);

const submitComment = () => {
    if (!newComment.value.trim() || posting.value) return;
    posting.value = true;
    router.post(route('tickets.comments.store', { ticket: props.ticket.id }), { body: newComment.value }, {
        preserveScroll: true,
        onSuccess: () => {
            newComment.value = '';
            // Reload only ticket data to refresh comments
            router.reload({ only: ['ticket'] });
        },
        onFinish: () => { posting.value = false; }
    });
};

const canDeleteAnyComment = useHasAny(['tickets.ticket.manage', 'tickets.ticket.delete']);
const canDeleteComment = (comment) => {
    // Owner or users with manage/delete permission
    return (comment.user_id === props.authUserId) || canDeleteAnyComment.value;
};

const deleting = ref(false);
const deleteComment = (comment) => {
    if (deleting.value) return;
    if (!canDeleteComment(comment)) return;
    if (!confirm('Delete this comment?')) return;
    deleting.value = true;
    router.delete(route('tickets.comments.destroy', { ticket: props.ticket.id, comment: comment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['ticket'] });
        },
        onFinish: () => { deleting.value = false; }
    });
};
</script>

<template>
    <Head :title="`Ticket #${ticket.id} - ${ticket.title}`" />

    <AuthenticatedLayout>
        <template #header>
        
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Status Update Bar (for admins) -->
                <div v-if="can.changeStatus" class="flex justify-between items-center p-5 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div>
                    <h2 class="max-w-3xl p-2 font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ ticket.title }}
                    </h2>
                    </div>
                    <!-- Back to Tickets Button -->
                <div class="">
                    <Link 
                        :href="route('tickets.index')" 
                        class="inline-flex items-center px-4 py-2 bg-uh-slate border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-gray focus:bg-uh-gray active:bg-uh-black focus:outline-none focus:ring-2 focus:ring-uh-slate focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        &larr; Back to Tickets
                    </Link>
                </div>
                </div>

                <!-- Ticket Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                            <!-- Main Content -->
                            <div class="md:col-span-2">
                            <div class="flex justify-end items-center">
                
            </div>
                                <!-- Description Section -->
                                <div class="relative">
                                    <!-- Description Card Header Toolbar -->
                                    <div class="mb-3 flex items-center justify-between rounded-lg bg-gray-50 dark:bg-gray-600/60 backdrop-blur px-3 py-2">
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Actions</h3>
                                        <div class="flex items-center gap-2">
                                            <div v-if="can.changeStatus" class="flex items-center gap-2">
                                                <label for="status-select" class="sr-only">Change status</label>
                                                <select
                                                    id="status-select"
                                                    v-model="selectedStatus"
                                                    class="text-sm rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 px-2 py- focus:outline-none focus:ring-2 focus:ring-uh-teal focus:border-uh-teal"
                                                >
                                                    <option value="" disabled>Select action</option>
                                                    <option v-for="(label, status) in filteredStatusOptions" :key="status" :value="status">{{ label }}</option>
                                                </select>
                                                <button
                                                    type="button"
                                                    @click="applyStatus"
                                                    :disabled="!selectedStatus"
                                                    class="px-3 py-2 font-medium rounded-md text-white bg-uh-teal hover:bg-uh-green disabled:opacity-50 disabled:cursor-not-allowed"
                                                    title="Apply selected status"
                                                    aria-label="Apply status"
                                                >
                                                    Apply
                                                </button>
                                            </div>
                                            <Link
                                                v-if="can.update"
                                                :href="route('tickets.edit', ticket.id)"
                                                class=""
                                                :aria-label="'Edit description'"
                                                title="Edit description"
                                            >
                                                <div class="flex items-center gap-2 p-2 px-3 text-gray-100 dark:text-gray-100 rounded-md bg-gray-500 dark:bg-gray-600/60 backdrop-blur hover:bg-gray-600 dark:hover:bg-gray-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l9.06-9.06.92.92L5.92 19.58zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                                    </svg>
                                                    <span>Edit</span>
                                                </div>
                                            </Link>
                                        </div>
                                    </div>
                                    <div class="p-8 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg min-h-[500px] prose dark:prose-invert max-w-none break-words" v-html="ticket.description"></div>
                                </div>
                                
                                <!-- Attachments Section -->
                                <div v-if="ticket.files && ticket.files.length > 0" class="mt-8">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Attachments</h3>
                                    <div class="space-y-3">
                                        <div v-for="file in ticket.files" :key="file.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                                            <a :href="getFileUrl(file)" target="_blank" rel="noopener noreferrer" class="flex items-center min-w-0 flex-1 group">
                                                <div class="flex items-center">
                                                    <span class="mr-3 block h-12 w-12">
                                                        <template v-if="isImage(file.mime_type)">
                                                            <img :src="getFileUrl(file)" :alt="file.original_name || 'attachment'" class="h-12 w-12 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700" />
                                                        </template>
                                                        <template v-else>
                                                            <FileIcon :mime-type="file.mime_type" class="w-12 h-12 text-gray-500 dark:text-gray-400" />
                                                        </template>
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" :title="file.original_name || file.file_path.split('/').pop()">
                                                            {{ file.original_name || file.file_path.split('/').pop() }}
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            {{ formatFileSize(file.size) }} • {{ getFileType(file.mime_type) }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Sidebar -->
                            <div class="space-y-6">
                                <!-- Ticket Info -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ticket Details</h3>
                                    
                                    <div class="space-y-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticket URL</p>
                                            <div class="mt-1 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-0">
                                                <input
                                                    type="text"
                                                    :value="ticketUrl"
                                                    readonly
                                                    :title="ticketUrl"
                                                    @focus="$event.target.select()"
                                                    spellcheck="false"
                                                    class="w-full sm:flex-1 rounded-md sm:rounded-l-md sm:rounded-r-none border border-gray-300 dark:border-gray-600 sm:border-r-0 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-uh-teal focus:border-uh-teal truncate"
                                                />
                                                <button
                                                    @click="copyTicketUrl"
                                                    type="button"
                                                    class="w-full sm:w-auto inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 sm:border-l-0 rounded-md sm:rounded-l-none sm:rounded-r-md bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-uh-teal"
                                                    :aria-label="copied ? 'Copied' : 'Copy URL'"
                                                    :title="copied ? 'Copied!' : 'Copy URL'"
                                                >
                                                    <svg v-if="!copied" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                                        <path d="M16 1H4c-1.1 0-2 .9-2 2v12h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                                                    </svg>
                                                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-uh-teal">
                                                        <path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="flex justify-start items-center">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</p>
                                            <p class="ml-2 text-sm text-gray-900 dark:text-white">
                                                <span :class="[statusClasses[ticket.status], 'px-2 py-1 rounded-full text-xs font-medium']">
                                                    {{ ticket.status }}
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <div class="flex justify-start items-center">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Priority:</p>
                                            <p class="ml-2 text-sm text-gray-900 dark:text-white">
                                                <span :class="[priorityClasses[ticket.priority], 'px-2 py-1 rounded-full text-xs font-medium']">
                                                    {{ ticket.priority }}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="flex justify-start items-center" v-if="ticket.due_date">
                                             <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Due Date:</p>
                                             <p class="ml-2 text-sm text-gray-900 dark:text-white">
                                                 {{ formatDate(ticket.due_date) }}
                                             </p>
                                         </div>
                                        <div class="flex justify-start items-center">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                               Submitted By:
                                            </p>
                                                <!-- commented out for circle icon with user name now 
                                                <div class="ml-2 flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="h-10 w-10 rounded-full bg-uh-teal/20 flex items-center justify-center">
                                                            <span class="text-uh-teal font-medium">
                                                                {{ ticket.user?.name?.charAt(0) || 'U' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                -->
                                        </div>
                                        <div class="ml-2 flex justify-start items-center">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                <template v-if="ticket.user?.email">
                                                    <Link v-if="ticket.user.is_team_member" :href="route('directory.show', ticket.user.team_id)" class="underline hover:text-blue-600 hover:underline">
                                                        {{ ticket.user?.name || 'Unknown User' }}
                                                    </Link>
                                                    <a v-else :href="'mailto:' + ticket.user.email" class="underline hover:text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">
                                                        {{ ticket.user?.name || 'Unknown User' }}
                                                    </a>
                                                </template>
                                                <template v-else>
                                                    {{ ticket.user?.name || 'Unknown User' }}
                                                </template>
                                            </p>
                                        </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mt-2 flex justify-start items-center">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Created: </p>
                                            <p class="ml-2 text-sm text-gray-900 dark:text-white">
                                                {{ formatDate(ticket.created_at) }}
                                            </p>
                                        </div>
                                        
                                        <div v-if="ticket.updated_at !== ticket.created_at" class="mt-2 flex justify-start items-center">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated: </p>
                                            <p class="ml-2 text-sm text-gray-900 dark:text-white">
                                                {{ formatDate(ticket.updated_at) }}
                                            </p>
                                        </div>
                                        <div v-if="ticket.updated_by_user" class="mt-2 flex justify-start items-center">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Modified By:</p>
                                            <p class="ml-2 text-sm text-gray-900 dark:text-white">
                                                <a
                                                    class="underline hover:text-blue-600 hover:underline"
                                                    :href="ticket.updated_by_user?.email ? ('mailto:' + ticket.updated_by_user.email) : '#'"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    {{ ticket.updated_by_user?.name || 'Unknown User' }}
                                                </a>
                                            </p>
                                        </div>
                                        <!-- ticket due date -->
                                        <div class="pl-2 flex mt-2 justify-start p-2items-center bg-uh-cream" v-if="ticket.due_date">
                                             <p class="text-sm font-medium text-gray-500">Due Date:</p>
                                             <p class="ml-2 text-sm text-gray-900 ">
                                                 {{ formatDate(ticket.due_date) }}
                                             </p>
                                         </div>
                                    </div>
                                </div>

                                <!-- User Info -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                        Assignees
                                    </h3>
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <template v-if="ticket.assignees && ticket.assignees.length">
                                            <span v-for="user in ticket.assignees" :key="user.id" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-teal/10 dark:bg-uh-teal/20 text-uh-slate dark:text-uh-cream">
                                                <!-- simple user icon -->
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 mr-1 text-uh-teal dark:text-uh-teal/80">
                                                    <path d="M10 10a4 4 0 100-8 4 4 0 000 8z" />
                                                    <path fill-rule="evenodd" d="M.458 16.042A9.956 9.956 0 0110 12c3.866 0 7.236 2.2 8.942 5.458A.75.75 0 0118.25 19H1.75a.75.75 0 01-1.292-.958z" clip-rule="evenodd" />
                                                </svg>
                                                {{ user.name }}
                                            </span>
                                        </template>
                                        <span v-else class="text-gray-400">Unassigned</span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button
                                        v-if="can.delete"
                                        @click="showDeleteModal = true"
                                        class="inline-flex items-center px-4 py-2 bg-uh-red border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-brick focus:bg-uh-brick active:bg-uh-chocolate focus:outline-none focus:ring-2 focus:ring-uh-red focus:ring-offset-2 transition ease-in-out duration-150"
                                    >
                                        Delete Ticket
                                    </button>

                                    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
                                        <div class="bg-uh-white dark:bg-gray-700 p-6">
                                            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                                Delete Ticket
                                            </h2>

                                            <p class="mt-4 text-gray-600 dark:text-gray-200">
                                                Are you sure you want to delete this ticket? This action cannot be undone.
                                            </p>

                                            <div class="mt-6 flex justify-end space-x-3">
                                                <button
                                                    @click="showDeleteModal = false"
                                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-teal transition ease-in-out duration-150"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    @click="confirmDelete"
                                                    class="px-4 py-2 text-sm font-medium text-white bg-uh-red border border-transparent rounded-md shadow-sm hover:bg-uh-brick focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-red transition ease-in-out duration-150"
                                                >
                                                    Delete Ticket
                                                </button>
                                            </div>
                                        </div>
                                    </Modal>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Comments</h3>

                        <!-- List -->
                        <div v-if="ticket.comments && ticket.comments.length" class="space-y-4 mb-6">
                            <div v-for="comment in ticket.comments" :key="comment.id" class="p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-uh-teal/20 flex items-center justify-center text-uh-teal font-semibold">
                                            {{ (comment.user?.name || 'U').charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ comment.user?.name || 'Unknown User' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(comment.created_at).toLocaleString() }}</div>
                                        </div>
                                    </div>
                                    <button
                                        v-if="canDeleteComment(comment)"
                                        @click="deleteComment(comment)"
                                        class="text-sm text-uh-red hover:text-uh-brick"
                                        :aria-label="`Delete comment by ${comment.user?.name || 'user'}`"
                                    >
                                        Delete
                                    </button>
                                </div>
                                <div class="mt-3 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words">{{ comment.body }}</div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="text-gray-500 dark:text-gray-400 italic mb-6">No comments yet.</div>

                        <!-- Create Form -->
                        <div class="mt-4">
                            <label for="new-comment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Add a comment</label>
                            <textarea
                                id="new-comment"
                                v-model="newComment"
                                rows="3"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-3 focus:outline-none focus:ring-2 focus:ring-uh-teal focus:border-uh-teal"
                                placeholder="Write your comment..."
                                maxlength="5000"
                            ></textarea>
                            <div class="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    @click="submitComment"
                                    :disabled="posting || !newComment.trim()"
                                    class="inline-flex items-center px-4 py-2 bg-uh-teal border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-green focus:bg-uh-green active:bg-uh-forest disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-uh-teal focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    {{ posting ? 'Posting...' : 'Post Comment' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.prose :deep(p) {
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.prose :deep(a) {
    text-decoration: underline;
    color: #1e40af; /* Slightly darker blue for better contrast */
    transition: color 0.2s ease-in-out;
}

.prose :deep(a:hover) {
    color: #1e3a8a; /* Darker blue on hover */
}

.dark .prose :deep(a) {
    color: #60a5fa; /* Lighter blue for dark mode */
}

.dark .prose :deep(a:hover) {
    color: #93c5fd; /* Lighter blue on hover in dark mode */
}

.prose :deep(ul),
.prose :deep(ol) {
    margin-top: 0.5em;
    margin-bottom: 0.5em;
    padding-left: 1.5em;
}

.prose :deep(h1) {
    font-size: 1.875rem; /* 30px */
    font-weight: 700;
    margin: 1.5em 0 0.75em;
    line-height: 1.2;
    color: inherit;
}

.prose :deep(h2) {
    font-size: 1.5rem; /* 24px */
    font-weight: 600;
    margin: 1.33em 0 0.67em;
    line-height: 1.3;
    color: inherit;
}

.prose :deep(h3) {
    font-size: 1.25rem; /* 20px */
    font-weight: 600;
    margin: 1.2em 0 0.6em;
    line-height: 1.4;
    color: inherit;
}

.prose :deep(h4) {
    font-size: 1.125rem; /* 18px */
    font-weight: 600;
    margin: 1.1em 0 0.55em;
    line-height: 1.4;
    color: inherit;
}

.prose :deep(h5) {
    font-size: 1rem;
    font-weight: 600;
    margin: 1em 0 0.5em;
    line-height: 1.4;
    color: inherit;
}

.prose :deep(h6) {
    font-size: 0.875rem;
    font-weight: 600;
    margin: 1em 0 0.5em;
    line-height: 1.4;
    color: inherit;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.8;
}

.prose :deep(pre) {
    background: #1e293b;
    color: #f8fafc;
    padding: 1em;
    border-radius: 0.375rem;
    overflow-x: auto;
    margin: 0.5em 0;
}

.prose :deep(code) {
    background: #e2e8f0;
    color: #0f172a;
    padding: 0.2em 0.4em;
    border-radius: 0.25rem;
    font-size: 0.875em;
}

.dark .prose :deep(code) {
    background: #334155;
    color: #e2e8f0;
}
</style>
