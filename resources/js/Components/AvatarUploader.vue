<template>
    <div>
        <input type="file" accept="image/*" @change="onFileChange" ref="fileInput" class="hidden" />
        <PrimaryButton v-if="renderButton" type="button" @click="openFileInput" :disabled="uploading">
            <span v-if="!uploading">Upload Image</span>
            <span v-else>Uploading...</span>
        </PrimaryButton>

        <p v-if="errorMsg" class="mt-2 text-sm text-red-600">{{ errorMsg }}</p>

        <Modal :show="showCropper" @close="closeCropper">
            <div class="p-6 dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Crop Image</h2>
                <div class="mt-4">
                    <VueCropper
                        ref="cropper"
                        :src="imageSrc"
                        :aspect-ratio="1"
                        :view-mode="2"
                        :auto-crop-area="0.8"
                        :background="false"
                        style="max-height: 400px;"
                    />
                </div>
                <div class="mt-4" v-if="uploading">
                    <div class="w-full h-2 bg-gray-200 rounded">
                        <div class="h-2 bg-indigo-600 rounded" :style="{ width: progress + '%' }"></div>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">{{ progress }}%</p>
                </div>
                <div class="mt-6 flex justify-end">
                    <PrimaryButton type="button" @click="cropAndUpload" :disabled="uploading">Crop & Upload</PrimaryButton>
                    <button type="button" @click="closeCropper" class="ml-4 inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">Cancel</button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Modal from '@/Components/Modal.vue';
import VueCropper from 'vue-cropperjs';
import 'cropperjs/dist/cropper.css';
import axios from 'axios';

const props = defineProps({
    modelValue: String,
    teamName: String,
    teamId: Number,
    renderButton: { type: Boolean, default: true },
    handleUpload: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'on-upload']);

const fileInput = ref(null);
const showCropper = ref(false);
const imageSrc = ref(null);
const cropper = ref(null);
const uploading = ref(false);
const progress = ref(0);
const errorMsg = ref('');

const openFileInput = () => {
    fileInput.value.click();
};

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        errorMsg.value = '';
        if (!file.type?.startsWith('image/')) {
            errorMsg.value = 'Please select a valid image file.';
            fileInput.value.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = (event) => {
            imageSrc.value = event.target.result;
            showCropper.value = true;
        };
        reader.readAsDataURL(file);
    }
};

// Allow parent to open cropper using current image URL (without selecting a new file)
const openCropperWithCurrent = () => {
    errorMsg.value = '';
    if (!props.modelValue) {
        errorMsg.value = 'No image available to crop.';
        return;
    }
    imageSrc.value = props.modelValue;
    showCropper.value = true;
};

const closeCropper = () => {
    showCropper.value = false;
    imageSrc.value = null;
    fileInput.value.value = '';
    progress.value = 0;
    uploading.value = false;
    // Do not clear errorMsg here so user can still see message outside modal
};

const cropAndUpload = async () => {
    try {
        uploading.value = true;
        errorMsg.value = '';
        progress.value = 0;

        const canvas = cropper.value?.getCroppedCanvas({
            width: 600,
            height: 600,
        });
        if (!canvas) throw new Error('Cropper is not ready.');

        const blob = await new Promise((resolve, reject) => {
            try {
                canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Failed to generate image blob.'))), 'image/jpeg');
            } catch (e) {
                reject(e);
            }
        });

        const formData = new FormData();
                        const uploadFilename = props.teamName ? `${props.teamName.trim().replace(/\s+/g, '_')}.jpg` : 'image.jpg';
        formData.append('image', blob, uploadFilename);
        formData.append('name', props.teamName || 'profile');

        // CSRF headers are set globally in resources/js/bootstrap.js
        const uploadResp = await axios.post('/api/tmp_upload', formData, {
            headers: {
                'Accept': 'application/json'
            },
            onUploadProgress: (e) => {
                if (e.total) {
                    progress.value = Math.round((e.loaded * 100) / e.total);
                }
            },
        });

                const { folder, filename } = uploadResp.data;
        emit('on-upload', { folder, filename, dataUrl: canvas.toDataURL('image/jpeg') });

        closeCropper();
    } catch (error) {
        console.error('Image upload failed:', error);
        errorMsg.value = error?.response?.data?.message || 'Upload failed. Please try again.';
    } finally {
        uploading.value = false;
        fileInput.value.value = '';
    }
};

// Expose imperative methods to parent components
defineExpose({ openFileInput, openCropperWithCurrent });
</script>
