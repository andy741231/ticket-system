<template>
    <Modal :show="show" @close="handleClose">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg max-w-2xl">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                    Add Proof
                </h2>
                <button @click="handleClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
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
                        <input type="radio" v-model="uploadType" value="file" class="mr-2 text-blue-500 focus:ring-blue-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Upload File</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" v-model="uploadType" value="url" class="mr-2 text-blue-500 focus:ring-blue-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Capture URL</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" v-model="uploadType" value="newsletter" class="mr-2 text-blue-500 focus:ring-blue-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Capture Newsletter</span>
                    </label>
                </div>
            </div>

            <!-- Proof Name Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Proof Name (Optional)</label>
                <input
                    type="text"
                    v-model="proofName"
                    placeholder="e.g., Homepage Design V2, Landing Page Mockup"
                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    maxlength="100"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Give this proof a descriptive name to help identify it later</p>
            </div>

            <!-- File Upload -->
            <div v-if="uploadType === 'file'" class="mb-6">
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
            <div v-if="uploadType === 'url'" class="mb-6">
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
            <div v-if="uploadType === 'newsletter'" class="mb-6">
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
                        <div
                            v-for="draft in newsletterDrafts"
                            :key="draft.id"
                            @click="selectedNewsletterId = draft.id"
                            :class="[
                                'cursor-pointer rounded-lg border-2 p-4 transition-all',
                                selectedNewsletterId === draft.id
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600'
                            ]"
                        >
                            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-1">{{ draft.name }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ draft.subject }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Updated {{ formatDate(draft.updated_at) }}</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="newsletterMeta.last_page > 1" class="mt-4 flex items-center justify-between">
                        <button
                            type="button"
                            @click="loadNewsletterDrafts(newsletterPage - 1)"
                            :disabled="newsletterPage <= 1 || newsletterLoading"
                            class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Page {{ newsletterPage }} of {{ newsletterMeta.last_page }}
                        </span>
                        <button
                            type="button"
                            @click="loadNewsletterDrafts(newsletterPage + 1)"
                            :disabled="newsletterPage >= newsletterMeta.last_page || newsletterLoading"
                            class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="uploadError" class="mb-4 rounded-md bg-red-50 dark:bg-red-900/40 p-3 text-sm text-red-700 dark:text-red-200">
                {{ uploadError }}
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-3 mt-6">
                <button
                    type="button"
                    @click="handleClose"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="submitProof"
                    :disabled="
                        (uploadType === 'file' && (!selectedFile || uploadProgress > 0)) ||
                        (uploadType === 'url' && (!proofUrl.trim() || isCapturing)) ||
                        (uploadType === 'newsletter' && (!selectedNewsletterId || isCapturing))
                    "
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-500 border border-transparent rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i class="fas fa-upload mr-2"></i>
                    {{
                        uploadType === 'url'
                            ? (isCapturing ? 'Capturing...' : 'Capture & Add')
                            : uploadType === 'newsletter'
                                ? (isCapturing ? 'Capturing...' : 'Capture Newsletter')
                            : (uploadProgress > 0 ? `Uploading... ${uploadProgress}%` : 'Upload & Add')
                    }}
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import Modal from '@/Components/Modal.vue'
import axios from 'axios'

const props = defineProps({
    show: {
        type: Boolean,
        required: true
    },
    ticketId: {
        type: Number,
        required: false
    },
    tempMode: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'uploaded'])

const uploadType = ref('file')
const proofName = ref('')
const proofUrl = ref('')
const selectedFile = ref(null)
const uploadProgress = ref(0)
const uploadError = ref(null)
const isCapturing = ref(false)
const captureProgress = ref(0)

// Newsletter state
const newsletterDrafts = ref([])
const newsletterMeta = ref({})
const newsletterLinks = ref({})
const newsletterLoading = ref(false)
const newsletterError = ref(null)
const newsletterPage = ref(1)
const selectedNewsletterId = ref(null)
const newsletterHasLoaded = ref(false)
const NEWSLETTER_PER_PAGE = 6

const handleClose = () => {
    // Reset all fields
    uploadType.value = 'file'
    proofName.value = ''
    proofUrl.value = ''
    selectedFile.value = null
    uploadProgress.value = 0
    uploadError.value = null
    newsletterError.value = null
    selectedNewsletterId.value = null
    captureProgress.value = 0
    isCapturing.value = false
    
    emit('close')
}

const handleFileSelect = (event) => {
    if (event.target.files.length > 0) {
        selectedFile.value = event.target.files[0]
        uploadProgress.value = 0
    }
}

const submitProof = async () => {
    try {
        uploadError.value = null
        
        const baseUrl = props.tempMode ? '/api/temp-images' : `/api/tickets/${props.ticketId}/images`

        if (uploadType.value === 'url') {
            if (!proofUrl.value || !proofUrl.value.trim()) {
                uploadError.value = 'Please enter a valid URL to capture.'
                return
            }
            isCapturing.value = true
            captureProgress.value = 0
            try {
                const payload = { url: proofUrl.value.trim() }
                if (proofName.value.trim()) {
                    payload.name = proofName.value.trim()
                }
                const resp = await axios.post(`${baseUrl}/from-url`, payload)
                const imageId = resp?.data?.data?.id || resp?.data?.id
                if (imageId) {
                    const result = await pollCapture(imageId)
                    if (result?.failed) {
                        uploadError.value = result.error || 'Failed to capture screenshot.'
                        return
                    }
                }
                emit('uploaded')
                handleClose()
            } finally {
                isCapturing.value = false
            }
            return
        }

        if (uploadType.value === 'newsletter') {
            if (!selectedNewsletterId.value) {
                uploadError.value = 'Please select a draft newsletter to capture.'
                return
            }
            isCapturing.value = true
            captureProgress.value = 0
            try {
                const payload = {
                    newsletter_campaign_id: selectedNewsletterId.value,
                }
                if (proofName.value.trim()) {
                    payload.name = proofName.value.trim()
                }
                const resp = await axios.post(`${baseUrl}/from-newsletter`, payload)
                const imageId = resp?.data?.data?.id || resp?.data?.id
                if (imageId) {
                    const result = await pollCapture(imageId)
                    if (result?.failed) {
                        uploadError.value = result.error || 'Failed to capture newsletter preview.'
                        return
                    }
                }
                emit('uploaded')
                handleClose()
            } finally {
                isCapturing.value = false
            }
            return
        }

        if (uploadType.value === 'file') {
            if (!selectedFile.value) {
                uploadError.value = 'Please select a file to upload.'
                return
            }
            const formData = new FormData()
            formData.append('file', selectedFile.value)
            if (proofName.value.trim()) {
                formData.append('name', proofName.value.trim())
            }
            uploadProgress.value = 0
            await axios.post(`${baseUrl}/from-file`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
                        uploadProgress.value = progress
                    }
                },
            })

            emit('uploaded')
            handleClose()
            return
        }

        uploadError.value = 'Unsupported proof upload type.'
    } catch (error) {
        console.error('Error uploading proof:', error)
        uploadError.value = error.response?.data?.message || 'Failed to upload proof. Please try again.'
        uploadProgress.value = 0
        isCapturing.value = false
    }
}

const pollCapture = async (imageId) => {
    const maxAttempts = 60
    let attempts = 0
    const statusUrl = props.tempMode 
        ? `/api/temp-images/${imageId}/status`
        : `/api/tickets/${props.ticketId}/images/${imageId}/status`

    while (attempts < maxAttempts) {
        try {
            const response = await axios.get(statusUrl)
            const status = response.data.data.status

            if (status === 'completed') {
                captureProgress.value = 100
                return { success: true }
            }
            if (status === 'failed') {
                return { failed: true, error: response.data.data.error_message || 'Capture failed' }
            }

            captureProgress.value = Math.min(95, (attempts / maxAttempts) * 100)
            await new Promise(resolve => setTimeout(resolve, 1000))
            attempts++
        } catch (error) {
            console.error('Poll error:', error)
            return { failed: true, error: 'Failed to check capture status' }
        }
    }
    return { timeout: true }
}

const loadNewsletterDrafts = async (page = 1) => {
    newsletterLoading.value = true
    newsletterError.value = null
    try {
        const response = await axios.get('/api/newsletter/campaigns/drafts', {
            params: {
                page,
                per_page: NEWSLETTER_PER_PAGE,
            },
        })
        newsletterDrafts.value = response.data.data || []
        newsletterMeta.value = response.data.meta || {}
        newsletterLinks.value = response.data.links || {}
        newsletterPage.value = page
        newsletterHasLoaded.value = true
    } catch (error) {
        console.error('Failed to load newsletter drafts:', error)
        newsletterError.value = 'Failed to load newsletter drafts. Please try again.'
    } finally {
        newsletterLoading.value = false
    }
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

watch(() => props.show, async (isOpen) => {
    if (isOpen && uploadType.value === 'newsletter' && !newsletterHasLoaded.value) {
        await loadNewsletterDrafts(newsletterPage.value || 1)
    }
})

watch(uploadType, async (newType) => {
    if (newType === 'newsletter' && props.show && !newsletterHasLoaded.value) {
        await loadNewsletterDrafts(newsletterPage.value || 1)
    }
})
</script>
