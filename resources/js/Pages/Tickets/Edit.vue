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
import AnnotationInterface from '@/Components/Annotation/AnnotationInterface.vue';
import Modal from '@/Components/Modal.vue';
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useHasAny } from '@/Extensions/useAuthz';
import axios from 'axios';

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
    can: {
        type: Object,
        default: () => ({
            update: false,
            delete: false,
            changeStatus: false,
            changeStatusAll: false,
        }),
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

const loadNewsletterDrafts = async (page = 1) => {
    newsletterLoading.value = true;
    newsletterError.value = null;
    try {
        const response = await axios.get('/api/newsletter/campaigns/drafts', {
            params: {
                page,
                per_page: NEWSLETTER_PER_PAGE,
            },
        });
        newsletterDrafts.value = response?.data?.data || [];
        newsletterMeta.value = response?.data?.meta || {};
        newsletterLinks.value = response?.data?.links || {};
        newsletterPage.value = page;
        newsletterHasLoaded.value = true;
    } catch (error) {
        console.error('Failed to load newsletter drafts', error);
        newsletterError.value = error?.response?.data?.message || 'Failed to load newsletter drafts.';
    } finally {
        newsletterLoading.value = false;
    }
};

const handleNewsletterPageChange = (page) => {
    if (!page || page === newsletterPage.value) return;
    loadNewsletterDrafts(page);
};

const captureNewsletterDraft = async () => {
    if (!selectedNewsletterId.value) return;
    isCapturing.value = true;
    uploadError.value = null;
    captureProgress.value = 0;
    try {
        const response = await axios.post(`/api/tickets/${props.ticket.id}/images/from-newsletter`, {
            newsletter_campaign_id: selectedNewsletterId.value,
        });
        const ticketImageId = response?.data?.data?.id || response?.data?.id;
        if (ticketImageId) {
            const result = await pollUrlCapture(ticketImageId);
            if (result?.failed) {
                uploadError.value = result.error || 'Failed to capture newsletter preview.';
                return;
            }
            if (result?.timeout) {
                uploadError.value = 'Capture is taking longer than expected. Please check the proof list shortly or try again.';
                return;
            }
        }
        await loadProofImages();
        closeProofModal();
    } catch (error) {
        console.error('Failed to capture newsletter draft', error);
        uploadError.value = error?.response?.data?.message || 'Failed to capture newsletter draft.';
    } finally {
        isCapturing.value = false;
        captureProgress.value = 0;
    }
};

// Proof images data
const proofImages = ref([]);
const showProofModal = ref(false);
const selectedFile = ref(null);
const uploadProgress = ref(0);
const uploadError = ref(null);
// Proof upload options
const proofUploadType = ref('file'); // 'file' | 'url'
const proofUrl = ref('');
const isCapturing = ref(false);
const captureProgress = ref(0);
const newsletterDrafts = ref([]);
const newsletterMeta = ref({});
const newsletterLinks = ref({});
const newsletterLoading = ref(false);
const newsletterError = ref(null);
const newsletterPage = ref(1);
const selectedNewsletterId = ref(null);
const newsletterHasLoaded = ref(false);
const NEWSLETTER_PER_PAGE = 6;

// Load proof images for this ticket
const loadProofImages = async () => {
    try {
        const response = await axios.get(`/api/tickets/${props.ticket.id}/images`);
        proofImages.value = response.data.data || [];
    } catch (error) {
        console.error('Error loading proof images:', error);
    }
};

// Get annotation count for an image
const getAnnotationCount = (imageId) => {
    const image = proofImages.value.find(img => img.id === imageId);
    return image?.annotations_count || 0;
};

// Open annotation page (uses existing route /annotations/{image})
const openAnnotationPage = (image) => {
    window.open(`/annotations/${image.id}`, '_blank');
};

// Track per-image deleting state
const deletingProof = ref({});

// Delete a proof image
const deleteProofImage = async (image) => {
    if (!image || !image.id) return;
    const confirmed = window.confirm(`Delete proof "${image.original_name || 'Untitled'}"? This action cannot be undone.`);
    if (!confirmed) return;

    deletingProof.value = { ...deletingProof.value, [image.id]: true };
    try {
        await axios.delete(`/api/tickets/${props.ticket.id}/images/${image.id}`);
        await loadProofImages();
    } catch (e) {
        console.error('Failed to delete proof image:', e);
        window.alert(e?.response?.data?.message || 'Failed to delete proof image.');
    } finally {
        deletingProof.value = { ...deletingProof.value, [image.id]: false };
    }
};

// Handle file selection
const handleFileSelect = (event) => {
    if (event.target.files.length > 0) {
        selectedFile.value = event.target.files[0];
        uploadProgress.value = 0;
    }
};

// Close proof modal
const closeProofModal = () => {
    showProofModal.value = false;
    selectedFile.value = null;
    proofUrl.value = '';
    proofUploadType.value = 'file';
    uploadProgress.value = 0;
    uploadError.value = null;
    newsletterError.value = null;
    selectedNewsletterId.value = null;
    captureProgress.value = 0;
};

// Poll status for URL capture and update determinate progress
const pollUrlCapture = async (imageId) => {
    const maxAttempts = 60; // up to 60 seconds
    let attempts = 0;
    captureProgress.value = 15; // start a bit in
    return new Promise((resolve) => {
        const tick = async () => {
            try {
                const resp = await axios.get(`/api/tickets/${props.ticket.id}/images/${imageId}/status`);
                const status = resp?.data?.data?.status || resp?.data?.status;
                if (status === 'completed') {
                    captureProgress.value = 100;
                    resolve({ done: true });
                    return;
                }
                if (status === 'failed' || status === 'error') {
                    resolve({ done: true, failed: true, error: resp?.data?.data?.error_message || 'Capture failed' });
                    return;
                }
                // Increase progress gradually up to 90% while processing
                if (captureProgress.value < 90) {
                    captureProgress.value = Math.min(90, captureProgress.value + 2);
                }
            } catch (e) {
                // Keep trying, but nudge progress slightly
                if (captureProgress.value < 85) captureProgress.value += 1;
            }
            attempts++;
            if (attempts < maxAttempts && isCapturing.value) {
                setTimeout(tick, 1000);
            } else {
                resolve({ done: true, timeout: true });
            }
        };
        setTimeout(tick, 1000);
    });
};

// Handle proof upload (file, URL, or newsletter)
const submitProof = async () => {
    try {
        uploadError.value = null;

        if (proofUploadType.value === 'url') {
            if (!proofUrl.value || !proofUrl.value.trim()) {
                uploadError.value = 'Please enter a valid URL to capture.';
                return;
            }
            isCapturing.value = true;
            captureProgress.value = 0;
            try {
                const resp = await axios.post(`/api/tickets/${props.ticket.id}/images/from-url`, { url: proofUrl.value.trim() });
                const imageId = resp?.data?.data?.id || resp?.data?.id;
                if (imageId) {
                    const result = await pollUrlCapture(imageId);
                    if (result?.failed) {
                        uploadError.value = result.error || 'Failed to capture screenshot.';
                        return;
                    }
                }
                await loadProofImages();
                closeProofModal();
            } finally {
                isCapturing.value = false;
            }
            return;
        }

        if (proofUploadType.value === 'newsletter') {
            if (!selectedNewsletterId.value) {
                uploadError.value = 'Please select a draft newsletter to capture.';
                return;
            }
            await captureNewsletterDraft();
            return;
        }

        if (proofUploadType.value === 'file') {
            if (!selectedFile.value) {
                uploadError.value = 'Please select a file to upload.';
                return;
            }
            const formData = new FormData();
            formData.append('file', selectedFile.value);
            uploadProgress.value = 0;
            await axios.post(`/api/tickets/${props.ticket.id}/images/from-file`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        uploadProgress.value = progress;
                    }
                },
            });

            await loadProofImages();
            closeProofModal();
            return;
        }

        uploadError.value = 'Unsupported proof upload type.';
    } catch (error) {
        console.error('Error uploading proof:', error);
        uploadError.value = error.response?.data?.message || 'Failed to upload proof. Please try again.';
        uploadProgress.value = 0;
        isCapturing.value = false;
    }
};

// Load proof images when component mounts
onMounted(() => {
    loadProofImages();
});

// Format file size helper (mirrors implementation in Show.vue)
const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

watch(proofUploadType, async (newType) => {
    if (newType === 'newsletter' && showProofModal.value && !newsletterHasLoaded.value) {
        await loadNewsletterDrafts(newsletterPage.value || 1);
    }
    if (newType !== 'newsletter') {
        selectedNewsletterId.value = null;
    }
});

watch(showProofModal, async (isOpen) => {
    if (isOpen && proofUploadType.value === 'newsletter' && !newsletterHasLoaded.value) {
        await loadNewsletterDrafts(newsletterPage.value || 1);
    }
    if (!isOpen) {
        selectedNewsletterId.value = null;
        newsletterError.value = null;
    }
});

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

                            <!-- File Uploader and Add Proof Button -->
                            <div class="mt-6">
                                <div class="flex items-center justify-start mb-2">
                                    <FileUploader
                                    :ticket-id="props.ticket.id"
                                    :existing-files="existingFiles"
                                    @uploaded="handleFilesUploaded"
                                    @removed="handleFileRemoved"
                                    class="mb-4 mr-2"
                                />
                                <InputError :message="form.errors.files" class="mt-2" />
                                
                                </div>
                                <PrimaryButton
                                    v-if="can.update"
                                    type="button"
                                    @click="showProofModal = true"
                                >
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Proof
                                </PrimaryButton>
                                <!-- Empty state attachments block (ported from Show.vue) -->
                                <div v-if="existingFiles.length === 0" class="mt-4">
                                    <div v-if="can.update" class="mt-4">
                                       
                                    </div>
                                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">

                                    </p>
                                </div>
                                <div v-else>
                                   
                                </div>
                            </div>


                            <!-- Proofs Section -->
                            <div class="mt-8">
                                
                                <div v-if="proofImages.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
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
                                        <p class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate" :title="image.original_name">
                                            {{ image.original_name || 'Untitled' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ getAnnotationCount(image.id) }} annotations
                                        </p>
                                    </div>
                                    
                                    <!-- Delete button -->
                                    <div v-if="can.update" class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-600/90 hover:bg-red-600 text-white shadow focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 focus:ring-offset-white dark:focus:ring-offset-gray-800"
                                            :title="deletingProof[image.id] ? 'Deleting…' : 'Delete proof'"
                                            :disabled="deletingProof[image.id]"
                                            @click.stop="deleteProofImage(image)"
                                        >
                                            <span v-if="!deletingProof[image.id]" aria-hidden="true">
                                                <font-awesome-icon icon="trash" class="text-xs" />
                                            </span>
                                            <span v-else class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                                            <span class="sr-only">Delete</span>
                                        </button>
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
                                        <button @click="closeProofModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
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
                                                <input type="radio" v-model="proofUploadType" value="file" class="mr-2 text-blue-500 focus:ring-blue-500" />
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Upload File</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="radio" v-model="proofUploadType" value="url" class="mr-2 text-blue-500 focus:ring-blue-500" />
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Capture URL</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="radio" v-model="proofUploadType" value="newsletter" class="mr-2 text-blue-500 focus:ring-blue-500" />
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Capture Newsletter</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- File Upload -->
                                    <div v-if="proofUploadType === 'file'" class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Image File</label>
                                        <input
                                            type="file"
                                            accept="image/*,.pdf"
                                            @change="handleFileSelect"
                                            class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300"
                                        />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 10MB. PDF uploads are converted to PNG (first page only).</p>
                                    <div v-if="uploadProgress > 0" class="mt-3">
                                        <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded">
                                            <div
                                                class="h-2 bg-blue-500 rounded transition-all"
                                                :style="{ width: Math.min(uploadProgress, 100) + '%' }"
                                            ></div>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Uploading file... {{ uploadProgress }}%</p>
                                    </div>
                                    </div>

                                    <!-- URL Input -->
                                    <div v-if="proofUploadType === 'url'" class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Website URL</label>
                                        <input
                                            type="url"
                                            v-model="proofUrl"
                                            placeholder="https://example.com"
                                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            @keydown.enter.prevent="submitProof"
                                        />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">We'll capture a screenshot of this webpage</p>
                                        <div v-if="isCapturing" class="mt-3">
                                            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded">
                                                <div class="h-2 bg-blue-500 rounded transition-all" :style="{ width: captureProgress + '%' }"></div>
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Capturing screenshot... {{ captureProgress }}%</p>
                                        </div>
                                    </div>

                                    <!-- Newsletter Drafts -->
                                    <div v-if="proofUploadType === 'newsletter'" class="mb-6">
                                        <div class="flex items-center justify-between mb-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Draft Newsletter</label>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Pick a draft campaign to capture its latest preview.</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="text-sm text-blue-600 hover:text-blue-500 flex items-center"
                                                @click="loadNewsletterDrafts(newsletterPage || 1)"
                                                :disabled="newsletterLoading"
                                            >
                                                <font-awesome-icon icon="sync" :class="['mr-1', newsletterLoading ? 'animate-spin' : '']" />
                                                Refresh
                                            </button>
                                        </div>

                                        <div v-if="newsletterError" class="mb-4 rounded-md bg-red-50 dark:bg-red-900/40 p-3 text-sm text-red-700 dark:text-red-200">
                                            {{ newsletterError }}
                                        </div>

                                        <div v-if="newsletterLoading" class="flex items-center justify-center py-8 text-gray-500 dark:text-gray-300">
                                            <span class="inline-flex items-center">
                                                <span class="mr-3 inline-block h-4 w-4 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></span>
                                                Loading drafts...
                                            </span>
                                        </div>

                                        <div v-else>
                                            <div v-if="newsletterDrafts.length === 0" class="py-6 text-center text-sm text-gray-500 dark:text-gray-300">
                                                No draft campaigns available. Create a draft in Newsletter Campaigns to use this feature.
                                            </div>

                                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <button
                                                    v-for="draft in newsletterDrafts"
                                                    :key="draft.id"
                                                    type="button"
                                                    class="w-full text-left border rounded-lg p-4 transition-colors"
                                                    :class="selectedNewsletterId === draft.id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/40' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-500'"
                                                    @click="selectedNewsletterId = draft.id"
                                                >
                                                    <div class="flex items-start justify-between">
                                                        <div>
                                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ draft.name }}</h3>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400" v-if="draft.subject">Subject: {{ draft.subject }}</p>
                                                        </div>
                                                        <font-awesome-icon
                                                            v-if="selectedNewsletterId === draft.id"
                                                            icon="check-circle"
                                                            class="text-blue-500"
                                                        />
                                                    </div>
                                                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                                                        Updated {{ draft.updated_at ? new Date(draft.updated_at).toLocaleString() : '-' }}
                                                    </p>
                                                </button>
                                            </div>

                                            <div v-if="newsletterMeta && newsletterMeta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
                                                <button
                                                    type="button"
                                                    class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
                                                    :disabled="!newsletterLinks.prev"
                                                    @click="handleNewsletterPageChange(newsletterMeta.current_page - 1)"
                                                >
                                                    Previous
                                                </button>
                                                <span class="text-gray-500 dark:text-gray-300">
                                                    Page {{ newsletterMeta.current_page }} of {{ newsletterMeta.last_page }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="px-3 py-1 border rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
                                                    :disabled="!newsletterLinks.next"
                                                    @click="handleNewsletterPageChange(newsletterMeta.current_page + 1)"
                                                >
                                                    Next
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex justify-end space-x-3">
                                        <button
                                            type="button"
                                            @click="closeProofModal"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            @click="submitProof"
                                            :disabled="
                                                (proofUploadType === 'file' && (!selectedFile || uploadProgress > 0)) ||
                                                (proofUploadType === 'url' && (!proofUrl.trim() || isCapturing)) ||
                                                (proofUploadType === 'newsletter' && (!selectedNewsletterId || isCapturing))
                                            "
                                            class="px-4 py-2 text-sm font-medium text-white bg-blue-500 border border-transparent rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <i class="fas fa-upload mr-2"></i>
                                            {{
                                                proofUploadType === 'url'
                                                    ? (isCapturing ? 'Capturing...' : 'Capture & Annotate')
                                                    : proofUploadType === 'newsletter'
                                                        ? (isCapturing ? 'Capturing...' : 'Capture Newsletter')
                                                    : (uploadProgress > 0 ? `Uploading... ${uploadProgress}%` : 'Upload & Annotate')
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </Modal>

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
