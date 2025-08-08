<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FileUploader from '@/Components/FileUploader.vue';
import FileIcon from '@/Components/FileIcon.vue';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

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
        }),
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.roles?.includes('admin') || false);

// Debug: Log ticket data to console
console.log('Ticket data:', props.ticket);
console.log('Attachments:', props.ticket.attachments);

const statusClasses = {
    'Received': 'bg-uh-teal/20 text-uh-forest',
    'Approved': 'bg-uh-gold/20 text-uh-ocher',
    'Rejected': 'bg-uh-red/20 text-uh-brick',
    'Completed': 'bg-uh-green/20 text-uh-forest',
};

const priorityClasses = {
    'Low': 'bg-uh-slate/20 text-uh-slate',
    'Medium': 'bg-uh-teal/20 text-uh-forest',
    'High': 'bg-uh-red/20 text-uh-brick',
};

const statusOptions = {
    'Approved': 'Approve',
    'Rejected': 'Reject',
    'Completed': 'Mark as Completed',
};

const formatDate = (dateString) => {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    };
    return new Date(dateString).toLocaleDateString(undefined, options);
};

const deleteTicket = () => {
    if (confirm('Are you sure you want to delete this ticket? This action cannot be undone.')) {
        router.delete(route('tickets.destroy', props.ticket.id));
    }
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
</script>

<template>
    <Head :title="`Ticket #${ticket.id} - ${ticket.title}`" />

    <AuthenticatedLayout>
        <template #header>
        
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Status Update Bar (for admins) -->
                <div v-if="can.changeStatus" class="p-5 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div>
                    <h2 class="p-2 font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ ticket.title }}
                    </h2>
                    </div>
                    <!-- Back to Tickets Button -->
                <div class="flex justify-end">
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
                <div class="flex space-x-2">
                    <Link 
                        v-if="can.update"
                        :href="route('tickets.edit', ticket.id)" 
                        class="inline-flex items-center px-4 py-2 bg-uh-teal border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-green focus:bg-uh-green active:bg-uh-forest focus:outline-none focus:ring-2 focus:ring-uh-teal focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Edit Ticket
                    </Link>
                    <button
                        v-if="can.delete"
                        @click="deleteTicket"
                        class="inline-flex items-center px-4 py-2 bg-uh-red border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-brick focus:bg-uh-brick active:bg-uh-chocolate focus:outline-none focus:ring-2 focus:ring-uh-red focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Delete Ticket
                    </button>
                </div>
            </div>
                                <!-- Description Section -->
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Description</h3>
                                <div class="p-8 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg min-h-96 prose dark:prose-invert max-w-none break-words" v-html="ticket.description"></div>
                                
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
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                <span :class="[statusClasses[ticket.status], 'px-2 py-1 rounded-full text-xs font-medium']">
                                                    {{ ticket.status }}
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Priority</p>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                <span :class="[priorityClasses[ticket.priority], 'px-2 py-1 rounded-full text-xs font-medium']">
                                                    {{ ticket.priority }}
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</p>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                {{ formatDate(ticket.created_at) }}
                                            </p>
                                        </div>
                                        
                                        <div v-if="ticket.updated_at !== ticket.created_at">
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</p>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                {{ formatDate(ticket.updated_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- User Info -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                 <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                        Assigned To:
                                    </h3>
                                    <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-uh-teal/20 flex items-center justify-center">
                                                <span class="text-uh-teal font-medium">
                                                    {{ ticket.user?.name?.charAt(0) || 'U' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ ticket.user?.name || 'Unknown User' }}
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ ticket.user?.email || 'No email provided' }}
                                            </p>
                                        </div>
                                </div>
                                <!-- Ticket ID -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                        {{ isAdmin ? 'Submitted By' : 'Your Information' }}
                                    </h3>
                                    
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-uh-teal/20 flex items-center justify-center">
                                                <span class="text-uh-teal font-medium">
                                                    {{ ticket.user?.name?.charAt(0) || 'U' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ ticket.user?.name || 'Unknown User' }}
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ ticket.user?.email || 'No email provided' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Ticket ID:{{ ticket.id }}
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                            Created {{ formatDate(ticket.created_at) }}
                        </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Update Status</h3>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(label, status) in statusOptions"
                                :key="status"
                                @click="updateStatus(status)"
                                :class="{
                                    'px-4 py-2 rounded-md text-sm font-medium': true,
                                    'bg-uh-teal text-white hover:bg-uh-green': status !== 'Rejected',
                                    'bg-uh-red text-white hover:bg-uh-brick': status === 'Rejected',
                                }"
                            >
                                {{ label }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Comments Section (Placeholder for future implementation) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Comments</h3>
                        <p class="text-gray-500 dark:text-gray-400 italic">
                            Comments functionality will be implemented in a future update.
                        </p>
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
