<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AnnotationCanvas from '@/Components/Annotation/AnnotationCanvas.vue';

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
    }
});

// State
const annotations = ref([]);
const comments = ref([]);
const loading = ref(true);
const error = ref(null);

// Load annotations
const loadAnnotations = async () => {
    try {
        loading.value = true;
        
        const url = props.isPublic 
            ? `/api/public/annotations/${props.image.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations`;
            
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                ...(props.isPublic ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                })
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            annotations.value = data.data || [];
            // Load comments for each annotation
            await loadComments();
        } else {
            throw new Error('Failed to load annotations');
        }
    } catch (err) {
        console.error('Error loading annotations:', err);
        error.value = 'Failed to load annotations';
    } finally {
        loading.value = false;
    }
};

// Create annotation (for public users)
const createAnnotation = async (annotationData) => {
    try {
        const url = props.isPublic 
            ? `/api/public/annotations/${props.image.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations`;
            
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(props.isPublic ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                })
            },
            body: JSON.stringify(annotationData)
        });
        
        if (response.ok) {
            const data = await response.json();
            annotations.value.push(data.data);
        } else {
            const error = await response.json();
            alert('Failed to create annotation: ' + (error.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error creating annotation:', error);
        alert('Failed to create annotation');
    }
};

// Update annotation
const updateAnnotation = async (annotation) => {
    try {
        if (props.isPublic) {
            alert('Editing annotations is not available in public view.');
            return;
        }

        const url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${annotation.id}`;
        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({
                type: annotation.type,
                coordinates: annotation.coordinates,
                style: annotation.style,
                content: annotation.content
            })
        });

        if (response.ok) {
            const data = await response.json();
            const idx = annotations.value.findIndex(a => a.id === annotation.id);
            if (idx !== -1) {
                annotations.value[idx] = data.data;
            }
        } else {
            const error = await response.json();
            alert('Failed to update annotation: ' + (error.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error updating annotation:', error);
        alert('Failed to update annotation');
    }
};

// Delete annotation
const deleteAnnotation = async (annotation) => {
    if (!confirm('Are you sure you want to delete this annotation?')) {
        return;
    }
    
    try {
        const url = props.isPublic 
            ? `/api/public/annotations/${props.image.id}/${annotation.id}?token=${props.publicToken}`
            : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${annotation.id}`;
            
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                ...(props.isPublic ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                })
            }
        });
        
        if (response.ok) {
            const index = annotations.value.findIndex(a => a.id === annotation.id);
            if (index !== -1) {
                annotations.value.splice(index, 1);
            }
        } else {
            throw new Error('Failed to delete annotation');
        }
    } catch (error) {
        console.error('Error deleting annotation:', error);
        alert('Failed to delete annotation. Please try again.');
    }
};

// Load comments (image-level + per-annotation)
const loadComments = async () => {
    try {
        const allComments = [];

        // 1) Image-level comments (auth only)
        if (!props.isPublic) {
            const imageUrl = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments`;
            const respImage = await fetch(imageUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            if (respImage.ok) {
                const data = await respImage.json();
                allComments.push(...(data.data || []));
            }
        }

        // 2) Comments for each visible annotation
        for (const annotation of annotations.value) {
            const url = props.isPublic 
                ? `/api/public/annotations/${props.image.id}/annotations/${annotation.id}/comments?token=${props.publicToken}`
                : `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${annotation.id}/comments`;
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    ...(props.isPublic ? {} : {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    })
                }
            });
            if (response.ok) {
                const data = await response.json();
                allComments.push(...(data.data || []));
            }
        }

        comments.value = allComments;
    } catch (err) {
        console.error('Error loading comments:', err);
    }
};

// Add comment (image-level if no annotation_id)
const addComment = async (commentData) => {
    try {
        let url = '';
        if (props.isPublic) {
            if (!commentData.annotation_id) {
                alert('Please select an annotation to add a comment (public view)');
                return;
            }
            url = `/api/public/annotations/${props.image.id}/annotations/${commentData.annotation_id}/comments?token=${props.publicToken}`;
        } else {
            if (commentData.annotation_id) {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${commentData.annotation_id}/comments`;
            } else {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments`;
            }
        }

        // Prepare request body
        let requestBody = { content: commentData.content, parent_id: commentData.parent_id || null };
        if (props.isPublic && commentData.public_name && commentData.public_email) {
            requestBody.public_name = commentData.public_name;
            requestBody.public_email = commentData.public_email;
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(props.isPublic ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                })
            },
            body: JSON.stringify(requestBody)
        });
        
        if (response.ok) {
            const data = await response.json();
            comments.value.push(data.data);
        } else {
            const error = await response.json();
            alert('Failed to add comment: ' + (error.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error adding comment:', error);
        alert('Failed to add comment');
    }
};

// Update comment (route by scope)
const updateComment = async (comment) => {
    try {
        let url = '';
        if (props.isPublic) {
            url = `/api/public/annotations/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}?token=${props.publicToken}`;
        } else {
            const isAnnotationScoped = annotations.value.some(a => a.id === comment.annotation_id);
            if (isAnnotationScoped) {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}`;
            } else {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments/${comment.id}`;
            }
        }

        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(props.isPublic ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                })
            },
            body: JSON.stringify({ content: comment.content })
        });
        
        if (response.ok) {
            const data = await response.json();
            const index = comments.value.findIndex(c => c.id === comment.id);
            if (index !== -1) {
                comments.value[index] = data.data;
            }
        } else {
            throw new Error('Failed to update comment');
        }
    } catch (error) {
        console.error('Error updating comment:', error);
        alert('Failed to update comment');
    }
};

// Delete comment (route by scope)
const deleteComment = async (comment) => {
    try {
        let url = '';
        if (props.isPublic) {
            url = `/api/public/annotations/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}?token=${props.publicToken}`;
        } else {
            const isAnnotationScoped = annotations.value.some(a => a.id === comment.annotation_id);
            if (isAnnotationScoped) {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/${comment.annotation_id}/comments/${comment.id}`;
            } else {
                url = `/api/tickets/${props.ticket.id}/images/${props.image.id}/annotations/image-comments/${comment.id}`;
            }
        }

        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                ...(props.isPublic ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                })
            }
        });
        
        if (response.ok) {
            const index = comments.value.findIndex(c => c.id === comment.id);
            if (index !== -1) {
                comments.value.splice(index, 1);
            }
        } else {
            throw new Error('Failed to delete comment');
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
        alert('Failed to delete comment');
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

// Generate share link on mount
onMounted(async () => {
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
                        <div v-if="isPublic" class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-globe mr-2"></i>
                            Public View
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
                    :readonly="false"
                    :is-public="isPublic"
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
                            <p>You can add your own annotations and comments to provide feedback. Use the toolbar above to select annotation tools.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom styles for the annotation page */
.annotation-page {
    min-height: 100vh;
}
</style>
