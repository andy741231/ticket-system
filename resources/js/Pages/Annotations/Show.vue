<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import AnnotationCanvas from '@/Components/Annotation/AnnotationCanvas.vue';
import ExternalUserLoginModal from '@/Components/Annotation/ExternalUserLoginModal.vue';

const props = defineProps({
    image: {
        type: Object,
        required: true,
    },
    ticket: {
        type: Object,
        required: true,
    },
    isPublic: {
        type: Boolean,
        default: false,
    },
    publicToken: {
        type: String,
        default: null,
    },
    canReviewAnnotations: {
        type: Boolean,
        default: false,
    }
});

// Get highlighted comment ID from URL parameter
const urlParams = new URLSearchParams(window.location.search);
const highlightedCommentId = ref(urlParams.get('comment') || null);

// Get current user from Inertia page props
const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);

// External user state
const externalUser = ref(null);
const showLoginModal = ref(false);
const checkingSession = ref(false);

// State
const annotations = ref([]);
const comments = ref([]);
const loading = ref(true);
const error = ref(null);

// Check external user session
const checkExternalUserSession = async () => {
    if (!props.isPublic) return;
    
    checkingSession.value = true;
    try {
        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.get(`/external-auth/annotations/${props.image.id}/check-session`);
        
        if (response.data.authenticated) {
            externalUser.value = response.data.user;
        }
    } catch (err) {
        console.error('Error checking external user session:', err);
    } finally {
        checkingSession.value = false;
    }
};

// Permission check functions
const canEditAnnotation = (annotation) => {
    if (props.isPublic) {
        // External users can edit their own annotations
        if (externalUser.value && annotation.external_user_id === externalUser.value.id) {
            return true;
        }
        return false;
    }
    
    const user = currentUser.value;
    if (!user) return false;
    
    // Super Admin or Tickets Admin can edit any annotation
    if (user.isSuperAdmin || props.canReviewAnnotations) return true;
    
    // Regular users can only edit their own annotations
    return annotation.user_id === user.id;
};

const canDeleteAnnotation = (annotation) => {
    if (props.isPublic) {
        // External users can delete their own annotations
        if (externalUser.value && annotation.external_user_id === externalUser.value.id) {
            return true;
        }
        return false;
    }
    
    const user = currentUser.value;
    if (!user) return false;
    
    // Super Admin or Tickets Admin can delete any annotation
    if (user.isSuperAdmin || props.canReviewAnnotations) return true;
    
    // Regular users can only delete their own annotations
    return annotation.user_id === user.id;
};

const canEditComment = (comment) => {
    if (props.isPublic) {
        // External users can edit their own comments
        if (externalUser.value && comment.external_user_id === externalUser.value.id) {
            return true;
        }
        return false;
    }
    
    const user = currentUser.value;
    if (!user) return false;
    
    // Super Admin or Tickets Admin can edit any comment
    if (user.isSuperAdmin || props.canReviewAnnotations) return true;
    
    // Regular users can only edit their own comments
    return comment.user_id === user.id;
};

const canDeleteComment = (comment) => {
    if (props.isPublic) {
        // External users can delete their own comments
        if (externalUser.value && comment.external_user_id === externalUser.value.id) {
            return true;
        }
        return false;
    }
    
    const user = currentUser.value;
    if (!user) return false;
    
    // Super Admin or Tickets Admin can delete any comment
    if (user.isSuperAdmin || props.canReviewAnnotations) return true;
    
    // Regular users can only delete their own comments
    return comment.user_id === user.id;
};

// Load annotations
const loadAnnotations = async () => {
    try {
        loading.value = true;
        
        const url = props.isPublic 
            ? `/api/public/annotations/${props.image.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations`;
            
        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.get(url);
        
        annotations.value = response.data.data || [];
        // Load comments for each annotation
        await loadComments();
    } catch (err) {
        console.error('Error loading annotations:', err);
        error.value = 'Failed to load annotations';
    } finally {
        loading.value = false;
    }
};

// Create annotation (for public users)
const createAnnotation = async (annotationData) => {
    // Check if external user is authenticated for public view
    if (props.isPublic && !externalUser.value) {
        showLoginModal.value = true;
        return;
    }

    try {
        const url = props.isPublic 
            ? `/api/public/annotations/${props.image.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations`;
            
        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.post(url, annotationData);
        
        annotations.value.push(response.data.data);
    } catch (error) {
        console.error('Error creating annotation:', error);
        if (error.response?.data?.message === 'Authentication required. Please verify your email.') {
            showLoginModal.value = true;
        } else {
            alert('Failed to create annotation: ' + (error.response?.data?.message || error.message || 'Unknown error'));
        }
    }
};

// Update annotation
const updateAnnotation = async (annotation) => {
    // Check if external user is authenticated for public view
    if (props.isPublic && !externalUser.value) {
        showLoginModal.value = true;
        return;
    }

    try {
        const url = props.isPublic
            ? `/api/public/annotations/${props.image.id}/${annotation.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${annotation.id}`;
            
        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.put(url, {
            type: annotation.type,
            coordinates: annotation.coordinates,
            style: annotation.style,
            content: annotation.content
        });

        const idx = annotations.value.findIndex(a => a.id === annotation.id);
        if (idx !== -1) {
            annotations.value[idx] = response.data.data;
        }
    } catch (error) {
        console.error('Error updating annotation:', error);
        alert('Failed to update annotation: ' + (error.response?.data?.message || error.message || 'Unknown error'));
    }
};

// Delete annotation
const deleteAnnotation = async (annotation) => {
    try {
        const url = props.isPublic 
            ? `/api/public/annotations/${props.image.id}/${annotation.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${annotation.id}`;
            
        // Use axios instead of fetch for better CSRF token handling across all browsers
        await window.axios.delete(url);
        
        // Remove the annotation from the list
        const index = annotations.value.findIndex(a => a.id === annotation.id);
        if (index !== -1) {
            annotations.value.splice(index, 1);
        }
        
        // Remove all comments associated with this annotation
        comments.value = comments.value.filter(c => c.annotation_id !== annotation.id);
    } catch (error) {
        console.error('Error deleting annotation:', error);
        alert('Failed to delete annotation: ' + (error.response?.data?.message || error.message || 'Unknown error'));
    }
};

// Load comments (image-level + per-annotation)
const loadComments = async () => {
    console.log('[loadComments] Called');
    try {
        const allComments = [];

        // 1) Image-level comments (both public and auth)
        const imageUrl = props.isPublic
            ? `/api/public/annotations/${props.image.id}/image-comments?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments`;
        console.log('[loadComments] Fetching image-level comments from:', imageUrl);
        
        try {
            // Use axios instead of fetch for better CSRF token handling across all browsers
            const respImage = await window.axios.get(imageUrl);
            console.log('[loadComments] Image-level comments fetched:', respImage.data.data?.length || 0, respImage.data.data);
            allComments.push(...(respImage.data.data || []));
        } catch (err) {
            console.error('[loadComments] Failed to fetch image-level comments:', err);
        }

        // 2) Comments for each visible annotation (exclude root_comment to avoid duplicates)
        const visibleAnnotations = annotations.value.filter(a => a.type !== 'root_comment');
        console.log('[loadComments] Fetching comments for', visibleAnnotations.length, 'annotations');
        for (const annotation of visibleAnnotations) {
            const url = props.isPublic 
                ? `/api/public/annotations/${props.image.id}/annotations/${annotation.id}/comments?token=${props.publicToken}`
                : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${annotation.id}/comments`;
            
            try {
                // Use axios instead of fetch for better CSRF token handling across all browsers
                const response = await window.axios.get(url);
                allComments.push(...(response.data.data || []));
            } catch (err) {
                console.error(`[loadComments] Failed to fetch comments for annotation ${annotation.id}:`, err);
            }
        }

        console.log('[loadComments] Total comments loaded:', allComments.length);
        comments.value = allComments;
    } catch (err) {
        console.error('Error loading comments:', err);
    }
};

// Add comment (image-level if no annotation_id)
const addComment = async (commentData) => {
    console.log('[addComment] Called with:', commentData);
    
    // Check if external user is authenticated for public view
    if (props.isPublic && !externalUser.value) {
        showLoginModal.value = true;
        return;
    }

    try {
        let url = '';
        if (props.isPublic) {
            // Public view supports both annotation-level and image-level comments
            if (commentData.annotation_id) {
                url = `/api/public/annotations/${props.image.id}/annotations/${commentData.annotation_id}/comments?token=${props.publicToken}`;
            } else {
                url = `/api/public/annotations/${props.image.id}/image-comments?token=${props.publicToken}`;
            }
        } else {
            if (commentData.annotation_id) {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${commentData.annotation_id}/comments`;
            } else {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments`;
            }
        }

        console.log('[addComment] Posting to:', url);

        // Prepare request body (no longer need public_name/public_email with new auth system)
        let requestBody = { 
            content: commentData.content, 
            parent_id: commentData.parent_id || null 
        };

        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.post(url, requestBody);
        
        console.log('[addComment] Comment created successfully:', response.data);
        // Reload all comments from server to ensure sync
        await loadComments();
        console.log('[addComment] Comments reloaded');
    } catch (error) {
        console.error('[addComment] API error:', error);
        if (error.response?.data?.message === 'Authentication required. Please verify your email.') {
            showLoginModal.value = true;
        } else {
            alert('Failed to add comment: ' + (error.response?.data?.message || error.message || 'Unknown error'));
        }
    }
};

// Update comment (route by scope)
const updateComment = async (comment) => {
    try {
        let url = '';
        if (props.isPublic) {
            // Check if it's an annotation-level or image-level comment
            const isAnnotationScoped = annotations.value.some(a => a.id === comment.annotation_id);
            if (isAnnotationScoped) {
                url = `/api/public/annotations/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}?token=${props.publicToken}`;
            } else {
                url = `/api/public/annotations/${props.image.id}/image-comments/${comment.id}?token=${props.publicToken}`;
            }
        } else {
            const isAnnotationScoped = annotations.value.some(a => a.id === comment.annotation_id);
            if (isAnnotationScoped) {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}`;
            } else {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments/${comment.id}`;
            }
        }

        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.put(url, { content: comment.content });
        
        const index = comments.value.findIndex(c => c.id === comment.id);
        if (index !== -1) {
            comments.value[index] = response.data.data;
        }
    } catch (error) {
        console.error('Error updating comment:', error);
        alert('Failed to update comment: ' + (error.response?.data?.message || error.message || 'Unknown error'));
    }
};

// Delete comment (route by scope)
const deleteComment = async (comment) => {
    try {
        let url = '';
        if (props.isPublic) {
            // Check if it's an annotation-level or image-level comment
            const isAnnotationScoped = annotations.value.some(a => a.id === comment.annotation_id);
            if (isAnnotationScoped) {
                url = `/api/public/annotations/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}?token=${props.publicToken}`;
            } else {
                url = `/api/public/annotations/${props.image.id}/image-comments/${comment.id}?token=${props.publicToken}`;
            }
        } else {
            const isAnnotationScoped = annotations.value.some(a => a.id === comment.annotation_id);
            if (isAnnotationScoped) {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}`;
            } else {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments/${comment.id}`;
            }
        }

        // Use axios instead of fetch for better CSRF token handling across all browsers
        await window.axios.delete(url);
        
        const index = comments.value.findIndex(c => c.id === comment.id);
        if (index !== -1) {
            comments.value.splice(index, 1);
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
        alert('Failed to delete comment: ' + (error.response?.data?.message || error.message || 'Unknown error'));
    }
};

// Handle annotation selection
const handleAnnotationSelected = (annotation) => {
    console.log('Selected annotation:', annotation);
};

// Handle undo/redo restore
const handleAnnotationsRestored = (restoredAnnotations) => {
    annotations.value = restoredAnnotations;
};

// Format file size
const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Format date
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Copy share link
const shareUrl = ref('');
const copied = ref(false);

const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (err) {
        console.error('Failed to copy share link:', err);
    }
};

// Safe back navigation: use history when available, otherwise fall back to a known route
const goBack = () => {
    try {
        const hasHistory = window.history.length > 1;
        const sameOriginReferrer = document.referrer && new URL(document.referrer).origin === window.location.origin;

        if (hasHistory && sameOriginReferrer) {
            window.history.back();
            return;
        }

        // Fallback destinations
        if (!props.isPublic) {
            router.visit(`/tickets/${props.ticket.id}`);
        } else {
            router.visit('/');
        }
    } catch (e) {
        // Ultimate fallback to avoid unhandled errors
        if (!props.isPublic) {
            router.visit(`/tickets/${props.ticket.id}`);
        } else {
            router.visit('/');
        }
    }
};

// Public/Private Access Management
const togglePublicAccess = async () => {
    try {
        const newPublicState = !props.image.is_public;
        
        // Use axios instead of fetch for better CSRF token handling across all browsers
        const response = await window.axios.put(`/api/tickets/${props.ticket.id}/images/${props.image.id}/public-access`, {
            is_public: newPublicState,
            public_access_level: 'annotate' // Always full access when public
        });
        
        // Update local state
        props.image.is_public = response.data.data.is_public;
        props.image.public_access_level = response.data.data.public_access_level;
    } catch (error) {
        console.error('Error toggling public access:', error);
        alert('Failed to update public access: ' + (error.response?.data?.message || error.message || 'Unknown error'));
    }
};

// Generate share link on mount
onMounted(async () => {
    // Check external user session if public view
    if (props.isPublic) {
        await checkExternalUserSession();
    }
    
    await loadAnnotations();
    
    // Generate share URL
    if (!props.isPublic) {
        shareUrl.value = `${window.location.origin}/annotations/${props.image.id}/public`;
    } else {
        shareUrl.value = window.location.href;
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <Head :title="`Annotation: ${image.original_name || 'Image'} - Ticket #${ticket.id}`" />
        
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 shadow">
            <div class="mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Left: Back and Title -->
                    <div class="flex items-center space-x-4">
                        <button
                            @click="goBack"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                        >
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </button>
                        <div>
                            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ image.original_name || 'Annotation Image' }}
                            </h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Ticket #{{ ticket.id }} - {{ ticket.title }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Right: Share and Info -->
                    <div class="flex items-center space-x-4">
                        <!-- Public/Private Toggle (only for authenticated users) -->
                        <div v-if="!isPublic">
                            <label class="flex items-center cursor-pointer group">
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        :checked="image.is_public"
                                        @change="togglePublicAccess"
                                        class="sr-only peer"
                                    />
                                    <div class="w-11 h-6 bg-gray-300 dark:bg-gray-600 rounded-full peer peer-checked:bg-green-500 transition-colors"></div>
                                    <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ image.is_public ? 'Public' : 'Private' }}
                                </span>
                            </label>
                        </div>
                        
                        <!-- Share Button (only for authenticated users) -->
                        <div v-if="!isPublic" class="flex items-center">
                            <input
                                type="text"
                                :value="shareUrl"
                                readonly
                                class="hidden sm:block w-64 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-l-md bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-400"
                            />
                            <button
                                @click="copyShareLink"
                                class="px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md sm:rounded-l-none border-l-0"
                                :title="copied ? 'Copied!' : 'Copy share link'"
                            >
                                <i :class="copied ? 'fas fa-check' : 'fas fa-share'" class="mr-2"></i>
                                {{ copied ? 'Copied!' : 'Share' }}
                            </button>
                        </div>
                        
                        <!-- Public indicator -->
                        <div v-if="isPublic" class="flex items-center space-x-3">
                            <!-- External user info or login button -->
                            <div v-if="externalUser" class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                                <i class="fas fa-user-check mr-2 text-green-600 dark:text-green-400"></i>
                                <span>{{ externalUser.name }}</span>
                                <span class="ml-2 px-2 py-0.5 bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-300 text-xs rounded">Guest</span>
                            </div>
                            <button
                                v-else
                                @click="showLoginModal = true"
                                class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md transition-colors"
                            >
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Sign In to Collaborate
                            </button>
                            <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-globe mr-2"></i>
                                Public View
                            </div>
                        </div>
                        
                        <!-- Image info -->
                        <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                            <p>{{ formatFileSize(image.file_size || 0) }}</p>
                            <p>{{ formatDate(image.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="mx-auto">
            <div v-if="loading" class="flex items-center justify-center h-64">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400">Loading annotations...</p>
                </div>
            </div>
            
            <div v-else-if="error" class="flex items-center justify-center h-64">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-3xl text-red-500 mb-4"></i>
                    <p class="text-red-600 dark:text-red-400">{{ error }}</p>
                    <button
                        @click="loadAnnotations"
                        class="mt-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md"
                    >
                        Try Again
                    </button>
                </div>
            </div>
            
            <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <!-- Annotation Canvas -->
                <AnnotationCanvas
                    :image-url="image.image_url"
                    :image-name="image.original_name || 'Annotation Image'"
                    :annotations="annotations"
                    :comments="comments"
                    :can-edit="canEditAnnotation"
                    :can-delete="canDeleteAnnotation"
                    :can-edit-comment="canEditComment"
                    :can-delete-comment="canDeleteComment"
                    :readonly="false"
                    :is-public="isPublic"
                    :ticket-id="ticket.id"
                    :image-id="image.id"
                    :public-token="publicToken"
                    :highlighted-comment-id="highlightedCommentId"
                    @annotation-created="createAnnotation"
                    @annotation-updated="updateAnnotation"
                    @annotation-deleted="deleteAnnotation"
                    @annotation-selected="handleAnnotationSelected"
                    @annotations-restored="handleAnnotationsRestored"
                    @comment-added="addComment"
                    @comment-updated="updateComment"
                    @comment-deleted="deleteComment"
                />
                
                <!-- Instructions for public users -->
                <div v-if="isPublic" class="p-4 bg-blue-50 dark:bg-blue-900/20 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <p class="font-medium mb-1">You're viewing a shared annotation</p>
                            <p v-if="externalUser">
                                You can add your own annotations and comments to provide feedback. Use the toolbar above to select annotation tools. 
                                Mention others using <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">@email@domain.com</code>
                            </p>
                            <p v-else>
                                <button @click="showLoginModal = true" class="underline font-medium">Sign in</button> to add annotations and comments.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- External User Login Modal -->
        <ExternalUserLoginModal
            :show="showLoginModal"
            :image-id="image.id"
            @close="showLoginModal = false"
            @success="checkExternalUserSession"
        />
    </div>
</template>

<style scoped>
/* Custom styles for the annotation page */
.annotation-page {
    min-height: 100vh;
}
</style>
