<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Avatar from '@/Components/Avatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { renderWithLinks } from '@/Utils/textUtils';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FileUploader from '@/Components/FileUploader.vue';
import FileIcon from '@/Components/FileIcon.vue';
import { useHasAny } from '@/Extensions/useAuthz';
import CommentList from '@/Components/Comments/CommentList.vue';
import AnnotationInterface from '@/Components/Annotation/AnnotationInterface.vue';

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
    authUser: {
        type: Object,
        default: () => ({}),
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

// Pin/Unpin comment
const handlePinToggled = (comment) => {
    router.post(route('tickets.comments.pin', { ticket: props.ticket.id, comment: comment.id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['ticket'] });
        }
    });
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

// Modal state for status changes
const showStatusModal = ref(false);
const pendingStatus = ref('');

const updateStatus = (status) => {
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
const selectedStatus = ref(props.ticket.status === 'Received' ? 'Approved' : props.ticket.status);
const applyStatus = () => {
    if (selectedStatus.value) {
        pendingStatus.value = selectedStatus.value;
        showStatusModal.value = true;
    }
};

const cancelStatusChange = () => {
    showStatusModal.value = false;
};

const confirmStatusChange = () => {
    if (pendingStatus.value) {
        updateStatus(pendingStatus.value);
        selectedStatus.value = '';
        pendingStatus.value = '';
    }
    showStatusModal.value = false;
};

// Enhanced comment system
const canDeleteAnyComment = useHasAny(['tickets.ticket.manage', 'tickets.ticket.delete']);

// Comment handlers
const handleCommentPosted = (data) => {
    const formData = data.formData || new FormData();
    if (!formData.has('body')) {
        formData.append('body', data.body || '');
    }
    
    router.post(route('tickets.comments.store', { ticket: props.ticket.id }), formData, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['ticket'] });
        }
    });
};

const handleCommentDeleted = (comment) => {
    if (!confirm('Delete this comment? This action cannot be undone.')) return;
    
    router.delete(route('tickets.comments.destroy', { ticket: props.ticket.id, comment: comment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['ticket'] });
        }
    });
};

const handleCommentEdited = (data) => {
    router.put(route('tickets.comments.update', { ticket: props.ticket.id, comment: data.comment.id }), {
        body: data.body
    }, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['ticket'] });
        }
    });
};

const handleReactionToggled = (data) => {
    // If user is switching reactions, remove the old one first
    if (data.previousReaction && !data.isRemoving) {
        router.post(route('tickets.comments.reactions', { ticket: props.ticket.id, comment: data.commentId }), {
            reaction: data.previousReaction,
            action: 'remove'
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // After removing old reaction, add the new one
                router.post(route('tickets.comments.reactions', { ticket: props.ticket.id, comment: data.commentId }), {
                    reaction: data.reaction,
                    action: 'add'
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        router.reload({ only: ['ticket'] });
                    }
                });
            }
        });
    } else {
        // Normal add/remove behavior
        const action = data.isRemoving ? 'remove' : 'add';
        router.post(route('tickets.comments.reactions', { ticket: props.ticket.id, comment: data.commentId }), {
            reaction: data.reaction,
            action: action
        }, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['ticket'] });
            }
        });
    }
};

const handleReplyPosted = (data) => {
    const formData = new FormData();
    formData.append('body', data.body);
    formData.append('parent_id', data.parentId);
    
    if (data.files && data.files.length > 0) {
        data.files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
    }
    
    router.post(route('tickets.comments.store', { ticket: props.ticket.id }), formData, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['ticket'] });
        }
    });
};

// Process description content to make URLs clickable
const descriptionContent = ref(null);

// Process description after component is mounted
onMounted(async () => {
    if (descriptionContent.value) {
        descriptionContent.value.innerHTML = renderWithLinks(descriptionContent.value.innerHTML);
    }
    
    // Load proof images and annotations
    await loadProofImages();
    await loadProofAnnotations();
    
    // Highlight comment if accessed via direct link
    const urlParams = new URLSearchParams(window.location.search);
    const commentId = urlParams.get('comment');
    if (commentId) {
        // Try multiple times to find the comment (in case it takes time to render)
        let attempts = 0;
        const maxAttempts = 10;
        
        const highlightComment = () => {
            console.log(`Attempting to highlight comment ${commentId}, attempt ${attempts + 1}`);
            const commentElement = document.getElementById(`comment-${commentId}`);
            
            if (commentElement) {
                console.log('Comment element found, highlighting...');
                
                // Scroll to comment
                commentElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center',
                    inline: 'nearest'
                });
                
                // Add highlight class
                commentElement.classList.add('highlight-comment');
                
                // Add a subtle border and background
                commentElement.style.transition = 'all 0.3s ease';
                
                console.log('Comment highlighted successfully');
                
                // Remove highlight after 5 seconds
                setTimeout(() => {
                    commentElement.classList.remove('highlight-comment');
                    console.log('Highlight removed');
                }, 5000);
                
                // Clear URL parameter after highlighting
                const newUrl = window.location.pathname + window.location.search.replace(/[?&]comment=\d+/, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
                
                return true;
            } else {
                console.log(`Comment element not found, attempt ${attempts + 1}/${maxAttempts}`);
                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(highlightComment, 200);
                }
                return false;
            }
        };
        
        // Start highlighting attempt after a short delay
        setTimeout(highlightComment, 300);
    }
});

// Current user data for comments
const currentUser = computed(() => ({
    id: props.authUserId,
    name: props.authUser?.name || 'Current User',
    email: props.authUser?.email || ''
}));

// Proof modal state
const showProofModal = ref(false);
const proofUploadType = ref('file'); // 'file' or 'url'
const proofUrl = ref('');
const proofFile = ref(null);
const proofImages = ref([]);
const proofAnnotations = ref([]);

// Load proof images on mount
const loadProofImages = async () => {
    try {
        const response = await fetch(`/api/tickets/${props.ticket.id}/images`, {
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            proofImages.value = data.data || [];
        }
    } catch (error) {
        console.error('Failed to load proof images:', error);
    }
};

// Load annotations for proof images
const loadProofAnnotations = async () => {
    try {
        for (const image of proofImages.value) {
            const response = await fetch(`/api/tickets/${props.ticket.id}/images/${image.id}/annotations`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                proofAnnotations.value.push(...(data.data || []));
            }
        }
    } catch (error) {
        console.error('Failed to load annotations:', error);
    }
};

// Get annotation count for an image
const getAnnotationCount = (imageId) => {
    return proofAnnotations.value.filter(annotation => annotation.ticket_image_id === imageId).length;
};

// Handle proof upload
const submitProof = async () => {
    if (proofUploadType.value === 'url' && !proofUrl.value.trim()) {
        alert('Please enter a valid URL');
        return;
    }
    
    if (proofUploadType.value === 'file' && !proofFile.value) {
        alert('Please select a file');
        return;
    }
    
    try {
        let response;
        
        if (proofUploadType.value === 'url') {
            response = await fetch(`/api/tickets/${props.ticket.id}/images/from-url`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ url: proofUrl.value })
            });
        } else {
            const formData = new FormData();
            formData.append('file', proofFile.value);
            
            response = await fetch(`/api/tickets/${props.ticket.id}/images/from-file`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: formData
            });
        }
        
        if (response.ok) {
            const data = await response.json();
            // Redirect to annotation page
            window.open(`/annotations/${data.data.id}`, '_blank');
            
            // Close modal and refresh
            showProofModal.value = false;
            proofUrl.value = '';
            proofFile.value = null;
            await loadProofImages();
        } else {
            const error = await response.json();
            alert('Failed to upload proof: ' + (error.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error uploading proof:', error);
        alert('Failed to upload proof. Please try again.');
    }
};

// Open annotation page
const openAnnotationPage = (image) => {
    window.open(`/annotations/${image.id}`, '_blank');
};

// Handle file selection
const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        proofFile.value = file;
    }
};

// Close proof modal
const closeProofModal = () => {
    showProofModal.value = false;
    proofUrl.value = '';
    proofFile.value = null;
    proofUploadType.value = 'file';
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
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Change Status</h3>
                                        <div class="flex items-center gap-2">
                                            <div v-if="can.changeStatus" class="flex items-center gap-2">
                                                <label for="status-select" class="sr-only">Select Status</label>
                                                <select
                                                    id="status-select"
                                                    v-model="selectedStatus"
                                                    class="font-bold pl-3 rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-2 py- focus:outline-none focus:ring-2 focus:ring-uh-teal focus:border-uh-teal"
                                                >
                                                    <option value="" disabled>Select Status</option>
                                                    <option v-for="(label, status) in filteredStatusOptions" :key="status" :value="status">{{ label }}</option>
                                                </select>
                                                <button
                                                    type="button"
                                                    @click="applyStatus"
                                                    :disabled="!selectedStatus"
                                                    class="px-3 py-2 font-medium rounded-md text-white bg-uh-red hover:bg-uh-green disabled:opacity-50 disabled:cursor-not-allowed"
                                                    title="Apply selected status"
                                                    aria-label="Apply status"
                                                >
                                                    Apply
                                                </button>
                                            </div>
                                            <div
                                                v-else-if="ticket.status !== 'Received' && authUserId && ticket.user && authUserId === ticket.user.id"
                                                class="flex items-center gap-2 p-2 px-3 text-gray-700 dark:text-gray-200 rounded-md bg-gray-100 dark:bg-gray-600/40"
                                            >
                                                <font-awesome-icon icon="ban" class="w-5 h-5" />
                                                <span>Ticket cannot be edited once status changed.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="relative p-8 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg min-h-[500px] prose dark:prose-invert max-w-none break-words">
                                        <div v-if="can.update" class="absolute mr-1 top-2 right-2 z-10">
                                            <Link
                                                :href="route('tickets.edit', ticket.id)"
                                                class=""
                                                :aria-label="'Edit description'"
                                                title="Edit description"
                                            >
                                                <div class="flex items-center gap-2 p-2 px-3 text-gray-100 dark:text-gray-100 rounded-md bg-gray-500/80 dark:bg-gray-600/80 backdrop-blur hover:bg-gray-600/90 dark:hover:bg-gray-500/90 transition-colors">
                                                    <font-awesome-icon icon="edit" class="w-4 h-4" />
                                                    <span class="text-sm">Edit</span>
                                                </div>
                                            </Link>
                                        </div>
                                        <div v-html="renderWithLinks(ticket.description)" ref="descriptionContent"></div>
                                    </div>
                                </div>
                                <!-- Status Change Confirmation Modal -->
                                <Modal :show="showStatusModal" @close="cancelStatusChange">
                                    <div class="bg-uh-white dark:bg-gray-700 p-6">
                                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                            Confirm Status Change
                                        </h2>

                                        <p class="mt-4 text-gray-600 dark:text-gray-200">
                                            Are you sure you want to
                                            <span class="font-semibold">{{ (statusOptions[pendingStatus] || pendingStatus).toLowerCase() }}</span>
                                            this ticket?
                                        </p>

                                        <div class="mt-6 flex justify-end space-x-3">
                                            <button
                                                type="button"
                                                @click="cancelStatusChange"
                                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-teal transition ease-in-out duration-150"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="button"
                                                @click="confirmStatusChange"
                                                class="px-4 py-2 text-sm font-medium text-white bg-uh-teal border border-transparent rounded-md shadow-sm hover:bg-uh-green focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-teal transition ease-in-out duration-150"
                                            >
                                                Confirm
                                            </button>
                                        </div>
                                    </div>
                                </Modal>
                                
                                <!-- Attachments Section -->
                                <div v-if="ticket.files && ticket.files.length > 0" class="mt-8">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Attachments</h3>
                                        
                                    </div>
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
                                                        <p class="text-sm font-medium text-gray-900  dark:text-gray-100 truncate" :title="file.original_name || file.file_path.split('/').pop()">
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
                                
                                

                                <!-- Proofs Section -->
                                <div class="mt-8">
                                    <div v-if="proofImages.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                        <h3 class="col-span-full text-lg font-medium text-gray-900 dark:text-white mb-3 flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            Proofs
                                        </h3>
                                        <div 
                                            v-for="image in proofImages" 
                                            :key="image.id" 
                                            class="relative group cursor-pointer bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-500 transition-colors"
                                            @click="openAnnotationPage(image)"
                                        >
                                            <div class="aspect-square p-2">
                                                <img 
                                                    :src="image.image_url" 
                                                    :alt="image.original_name || 'Proof image'" 
                                                    class="w-full h-full object-cover rounded"
                                                />
                                            </div>
                                            <div class="p-2">
                                                <p class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" :title="image.name || image.original_name">
                                                    {{ image.name || image.original_name || 'Untitled' }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ getAnnotationCount(image.id) }} annotations
                                                </p>
                                            </div>
                                            <!--trassh can icon-->
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
                                                    <font-awesome-icon v-if="!copied" icon="copy" class="w-5 h-5" />
                                                    <font-awesome-icon v-else icon="check" class="w-5 h-5 text-uh-teal" />
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
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-teal/10 dark:bg-uh-teal/20 text-uh-slate dark:text-uh-cream">
                                                <Avatar :user="ticket.user" size="xs" class="mr-2" />
                                                {{ ticket.user?.name || 'Unknown User' }}
                                            </span>
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
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-teal/10 dark:bg-uh-teal/20 text-uh-slate dark:text-uh-cream">
                                                <Avatar :user="ticket.updated_by_user" size="xs" class="mr-2" />
                                                {{ ticket.updated_by_user?.name || 'Unknown User' }}
                                            </span>
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
                                                <Avatar :user="user" size="xs" class="mr-2" />
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
        
        <!-- Proof Upload Modal -->
        <Modal :show="showProofModal" @close="closeProofModal">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        Add Proof
                    </h2>
                    <button
                        @click="closeProofModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Upload an image or capture a screenshot from a URL to create annotations and provide visual proof.
                </p>
                
                <!-- Upload Type Selector -->
                <div class="mb-6">
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input
                                type="radio"
                                v-model="proofUploadType"
                                value="file"
                                class="mr-2 text-blue-500 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Upload File</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="radio"
                                v-model="proofUploadType"
                                value="url"
                                class="mr-2 text-blue-500 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Capture URL</span>
                        </label>
                    </div>
                </div>
                
                <!-- File Upload -->
                <div v-if="proofUploadType === 'file'" class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Image File
                    </label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleFileSelect"
                        class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        PNG, JPG, GIF up to 10MB
                    </p>
                </div>
                
                <!-- URL Input -->
                <div v-if="proofUploadType === 'url'" class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Website URL
                    </label>
                    <input
                        type="url"
                        v-model="proofUrl"
                        placeholder="https://example.com"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        We'll capture a screenshot of this webpage
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button
                        @click="closeProofModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitProof"
                        :disabled="(proofUploadType === 'file' && !proofFile) || (proofUploadType === 'url' && !proofUrl.trim())"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-500 border border-transparent rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i class="fas fa-upload mr-2"></i>
                        {{ proofUploadType === 'url' ? 'Capture & Annotate' : 'Upload & Annotate' }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Enhanced Comments Section -->
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <CommentList
                    :comments="ticket.comments || []"
                    :current-user="currentUser"
                    :ticket-id="ticket.id"
                    :can-delete-any="canDeleteAnyComment"
                    :can-pin-any="canManageTickets"
                    @comment-posted="handleCommentPosted"
                    @comment-deleted="handleCommentDeleted"
                    @comment-edited="handleCommentEdited"
                    @reaction-toggled="handleReactionToggled"
                    @reply-posted="handleReplyPosted"
                    @pin-toggled="handlePinToggled"
                />
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

.prose :deep(table) {
    border-collapse: collapse;
    width: 100%;
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
    color: #f1f5f9;
}

/* Comment highlighting animation */
.highlight-comment {
    animation: highlightPulse 5s ease-in-out;
    border-left: 4px solid #eab308 !important;
    background-color: rgba(254, 240, 138, 0.5) !important;
}

@keyframes highlightPulse {
    0% {
        background-color: rgba(254, 240, 138, 0.7) !important;
        transform: scale(1);
    }
    25% {
        background-color: rgba(254, 240, 138, 0.6) !important;
        transform: scale(1.005);
    }
    50% {
        background-color: rgba(254, 240, 138, 0.5) !important;
        transform: scale(1.01);
    }
    75% {
        background-color: rgba(254, 240, 138, 0.4) !important;
        transform: scale(1.005);
    }
    100% {
        background-color: rgba(254, 240, 138, 0.3) !important;
        transform: scale(1);
    }
}

/* Dark mode highlighting */
.dark .highlight-comment {
    border-left: 4px solid #facc15 !important;
    background-color: rgba(254, 240, 138, 0.3) !important;
}

.dark .highlight-comment {
    animation: highlightPulseDark 5s ease-in-out;
}

@keyframes highlightPulseDark {
    0% {
        background-color: rgba(254, 240, 138, 0.4) !important;
        transform: scale(1);
    }
    25% {
        background-color: rgba(254, 240, 138, 0.35) !important;
        transform: scale(1.005);
    }
    50% {
        background-color: rgba(254, 240, 138, 0.3) !important;
        transform: scale(1.01);
    }
    75% {
        background-color: rgba(254, 240, 138, 0.25) !important;
        transform: scale(1.005);
    }
    100% {
        background-color: rgba(254, 240, 138, 0.2) !important;
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .highlight-comment {
        animation: none;
        background-color: rgba(254, 240, 138, 0.5) !important;
        border-left: 4px solid #eab308 !important;
    }
    
    .dark .highlight-comment {
        background-color: rgba(254, 240, 138, 0.3) !important;
        border-left: 4px solid #facc15 !important;
    }
}
</style>
