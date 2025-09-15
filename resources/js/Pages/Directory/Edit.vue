<script setup>
import { ref, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import TicketEditor from '@/Components/WYSIWYG/TicketEditor.vue';

import AvatarUploader from '@/Components/AvatarUploader.vue';

const props = defineProps({
  team: Object,
});

const form = useForm({
    // Columns 2–13 from uhph_team
    first_name: props.team.first_name,
    last_name: props.team.last_name,
    email: props.team.email,
    degree: props.team.degree,
    title: props.team.title,
    description: props.team.description,
    message: props.team.message,
    bio: props.team.bio,
    img: props.team.img, // For preview
    tmp_folder: null,
    tmp_filename: null,
    group_1: props.team.group_1,
    program: props.team.program,
    team: props.team.team,
    department: props.team.department,
});

const submit = () => {
    form.put(route('directory.update', props.team.id));
};

// Image actions modal state and AvatarUploader ref
const showImageActions = ref(false);
const avatarUploader = ref(null);

const triggerUploadNew = () => {
  showImageActions.value = false;
  avatarUploader.value?.openFileInput();
};

const triggerCropCurrent = () => {
  showImageActions.value = false;
  avatarUploader.value?.openCropperWithCurrent();
};

const onImageUploaded = async ({ folder, filename, dataUrl }) => {
  // If a temporary file was already uploaded, delete it before assigning the new one.
  if (form.tmp_folder) {
    await axios.delete('/api/tmp_delete', { data: { folder: form.tmp_folder } });
  }
  form.img = dataUrl; // for preview
  form.tmp_folder = folder;
  form.tmp_filename = filename;
  showImageActions.value = false;
};

const triggerDeleteImage = async () => {
  if (form.tmp_folder) {
    await axios.delete('/api/tmp_delete', { data: { folder: form.tmp_folder } });
  }
  form.img = null;
  form.tmp_folder = null;
  form.tmp_filename = null;
  showImageActions.value = false;
};

// Cleanup temporary file on page exit
onUnmounted(() => {
  if (form.tmp_folder) {
    axios.delete('/api/tmp_delete', { data: { folder: form.tmp_folder } });
  }
});

// Collapsible editors
const showBioEditor = ref(true);
const showDescriptionEditor = ref(false);
const showMessageEditor = ref(false);
</script>

<template>
  <Head :title="'Edit ' + team.name" />

  <AuthenticatedLayout>
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit {{ team.name }}</h2>
    </template>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1 flex flex-col items-center">
                            <div class="relative group">
                                <img v-if="form.img" :src="form.img" :alt="team.name" class="rounded-full h-48 w-48 object-cover">
                                <svg v-else class="w-48 h-48 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <!-- Hover overlay -->
                                <button type="button" @click.prevent="showImageActions = true" class="absolute -inset-5 rounded-full flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-white font-medium">Edit</span>
                                </button>
                            </div>
                            <div class="mt-4 w-full flex flex-col items-center">
                                <!-- Reuse uploader without its own button; control via ref -->
                                <AvatarUploader ref="avatarUploader" v-model="form.img" :team-name="`${form.first_name} ${form.last_name}`.trim()" :team-id="team.id" @on-upload="onImageUploaded" :render-button="false" />
                                <InputError class="mt-2" :message="form.errors.img" />
                            </div>
                        </div>
                        <div class="md:col-span-2 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="first_name" value="First Name" />
                                <TextInput id="first_name" type="text" class="dark:bg-gray-700d dark:text-gray-100 mt-1 block w-full" v-model="form.first_name" autofocus />
                                <InputError class="mt-2" :message="form.errors.first_name" />
                            </div>

                            <div>
                                <InputLabel for="last_name" value="Last Name" />
                                <TextInput id="last_name" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.last_name" />
                                <InputError class="mt-2" :message="form.errors.last_name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Email" />
                                <TextInput id="email" type="email" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.email" />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <InputLabel for="degree" value="Degree" />
                                <TextInput id="degree" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.degree" />
                                <InputError class="mt-2" :message="form.errors.degree" />
                            </div>
                        
                        
                            <div>
                                <InputLabel for="title" value="Title" />
                                <TextInput id="title" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.title" />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                             <div>
                                <InputLabel for="group_1" value="Group" />
                                <TextInput id="group_1" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.group_1" />
                                <InputError class="mt-2" :message="form.errors.group_1" />
                            </div>
                        </div>
                            <div class="border rounded-md dark:border-gray-700">
                                 <button
                                  type="button"
                                  class="w-full text-left px-4 py-2 flex items-center justify-between bg-gray-50 dark:bg-gray-800 dark:text-gray-100"
                                  @click="showBioEditor = !showBioEditor"
                                  aria-controls="bio-editor"
                                  :aria-expanded="showBioEditor.toString()"
                                >
                                  <span class="font-medium">Biography</span>
                                  <span class="ml-2 select-none">{{ showBioEditor ? '−' : '+' }}</span>
                                </button>
                                <div id="bio-editor" v-show="showBioEditor" class="p-2">
                                <TicketEditor class="dark:text-gray-100" id="bio" label="" v-model="form.bio" :error="form.errors.bio" />
                                </div>
                            </div>

                             <!-- Description (collapsible) -->
                            <div class="border rounded-md dark:border-gray-700">
                                <button
                                  type="button"
                                  class="w-full text-left px-4 py-2 flex items-center justify-between bg-gray-50 dark:bg-gray-800 dark:text-gray-100"
                                  @click="showDescriptionEditor = !showDescriptionEditor"
                                  aria-controls="description-editor"
                                  :aria-expanded="showDescriptionEditor.toString()"
                                >
                                  <span class="font-medium">Description</span>
                                  <span class="ml-2 select-none">{{ showDescriptionEditor ? '−' : '+' }}</span>
                                </button>
                                <div id="description-editor" v-show="showDescriptionEditor" class="p-2">
                                  <TicketEditor class="dark:text-gray-100" id="description" label="" v-model="form.description" :error="form.errors.description" />
                                </div>
                            </div>

                            <!-- Message (collapsible) -->
                            <div class="border rounded-md dark:border-gray-700">
                                <button
                                  type="button"
                                  class="w-full text-left px-4 py-2 flex items-center justify-between bg-gray-50 dark:bg-gray-800 dark:text-gray-100"
                                  @click="showMessageEditor = !showMessageEditor"
                                  aria-controls="message-editor"
                                  :aria-expanded="showMessageEditor.toString()"
                                >
                                  <span class="font-medium">Message</span>
                                  <span class="ml-2 select-none">{{ showMessageEditor ? '−' : '+' }}</span>
                                </button>
                                <div id="message-editor" v-show="showMessageEditor" class="p-2">
                                  <TicketEditor class="dark:text-gray-100" id="message" label="" v-model="form.message" :error="form.errors.message" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <InputLabel for="program" value="Program" />
                                    <TextInput id="program" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.program" />
                                    <InputError class="mt-2" :message="form.errors.program" />
                                </div>

                                <div>
                                    <InputLabel for="team" value="Team" />
                                    <TextInput id="team" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.team" />
                                    <InputError class="mt-2" :message="form.errors.team" />
                                </div>

                                <div>
                                    <InputLabel for="department" value="Department" />
                                    <TextInput id="department" type="text" class="dark:bg-gray-700 dark:text-gray-100 mt-1 block w-full" v-model="form.department" />
                                    <InputError class="mt-2" :message="form.errors.department" />
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton type="submit" :disabled="form.processing">Save</PrimaryButton>

                                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">Saved.</p>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Image Actions Modal -->
    <Modal :show="showImageActions" @close="showImageActions = false">
      <div class="p-6 dark:bg-gray-800">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Edit Photo</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Choose an action for this profile photo.</p>
        <div class="mt-6 flex flex-wrap gap-3 justify-end">
          <SecondaryButton type="button" @click.prevent="triggerUploadNew">Upload new</SecondaryButton>
          <PrimaryButton type="button" @click.prevent="triggerCropCurrent" :disabled="!form.img">Crop current</PrimaryButton>
          <DangerButton type="button" @click.prevent="triggerDeleteImage">Delete</DangerButton>
          <button type="button" @click="showImageActions = false" class="ml-2 inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">Cancel</button>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>