<template>
    <div class="space-y-3">
        <!-- Hidden file input -->
        <input
            type="file"
            multiple
            :accept="accept"
            @change="onFilesSelected"
            ref="fileInput"
            class="hidden"
        />

        <!-- Upload button and progress -->
        <div class="flex items-center gap-3">
            <PrimaryButton type="button" @click="openPicker" :disabled="uploading">
                <span v-if="!uploading">Add files</span>
                <span v-else>Uploading...</span>
            </PrimaryButton>
            <div v-if="uploading" class="flex-1">
                <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded">
                    <div class="h-2 bg-uh-teal rounded" :style="{ width: progress + '%' }"></div>
                </div>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ progress }}%</p>
            </div>
        </div>

        <p v-if="errorMsg" class="text-sm text-red-600">{{ errorMsg }}</p>

        <!-- Existing files list -->
        <div v-if="existingFiles && existingFiles.length" class="space-y-2">
            <div
                v-for="file in existingFiles"
                :key="file.id"
                class="flex items-center justify-between p-2 rounded border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/40"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div v-if="isImage(file.mime_type)" class="h-10 w-10 rounded overflow-hidden ring-1 ring-gray-200 dark:ring-gray-600">
                        <img :src="file.url || storageUrl(file.file_path)" :alt="file.original_name || 'file'" class="h-full w-full object-cover" />
                    </div>
                    <div v-else class="h-10 w-10 flex items-center justify-center rounded bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-600 text-gray-500">📄</div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" :title="file.original_name || file.file_path?.split('/').pop()">
                            {{ file.original_name || file.file_path?.split('/').pop() || 'File' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" v-if="file.size">{{ formatSize(file.size) }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-sm text-uh-red hover:text-uh-brick disabled:opacity-50"
                    :disabled="uploading"
                    @click="removeFile(file)"
                >
                    Remove
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';

const props = defineProps({
    // When true, use temp files API (used on ticket create page)
    tempMode: { type: Boolean, default: false },
    // When editing an existing ticket, provide the ticket id
    ticketId: { type: [Number, String], default: null },
    // Files already attached (rendered in list)
    existingFiles: { type: Array, default: () => [] },
    // Optional accept attribute
    accept: { type: String, default: '.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*' },
});

const emit = defineEmits(['uploaded', 'removed']);

const fileInput = ref(null);
const uploading = ref(false);
const progress = ref(0);
const errorMsg = ref('');

const openPicker = () => fileInput.value?.click();

const onFilesSelected = async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    errorMsg.value = '';
    progress.value = 0;
    uploading.value = true;

    try {
        const formData = new FormData();
        files.forEach(f => formData.append('files[]', f));

        const url = props.tempMode
            ? '/api/temp-files'
            : (props.ticketId ? `/api/tickets/${props.ticketId}/files` : null);

        if (!url) {
            throw new Error('Missing upload context. Provide temp-mode or ticket-id.');
        }

        const resp = await axios.post(url, formData, {
            onUploadProgress: (e) => {
                if (e.total) progress.value = Math.round((e.loaded * 100) / e.total);
            },
        });

        const uploaded = resp?.data?.files || [];
        if (uploaded.length) emit('uploaded', uploaded);
    } catch (err) {
        console.error('File upload failed:', err);
        errorMsg.value = err?.response?.data?.message || 'Upload failed. Please try again.';
    } finally {
        uploading.value = false;
        progress.value = 0;
        if (fileInput.value) fileInput.value.value = '';
    }
};

const removeFile = async (file) => {
    if (!file?.id) return;
    try {
        errorMsg.value = '';
        const url = props.tempMode
            ? `/api/temp-files/${file.id}`
            : (props.ticketId ? `/api/tickets/${props.ticketId}/files/${file.id}` : null);

        if (!url) throw new Error('Missing removal context. Provide temp-mode or ticket-id.');

        await axios.delete(url);
        emit('removed', file);
    } catch (err) {
        console.error('Failed to remove file:', err);
        errorMsg.value = err?.response?.data?.message || 'Failed to remove file.';
    }
};

const formatSize = (bytes) => {
    if (!bytes) return '';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
};

const isImage = (mime) => !!mime && mime.startsWith('image/');
const storageUrl = (path) => (path ? `/storage/${path}` : '#');
</script>