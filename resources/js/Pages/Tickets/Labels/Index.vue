<script setup>
import { ref, nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

const props = defineProps({
    tags: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);
const editingTag = ref(null);
const nameInput = ref(null);

const form = useForm({
    name: '',
});

const openCreateModal = () => {
    editingTag.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
    nextTick(() => nameInput.value?.focus());
};

const openEditModal = (tag) => {
    editingTag.value = tag;
    form.name = tag.name;
    form.clearErrors();
    showModal.value = true;
    nextTick(() => nameInput.value?.focus());
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    editingTag.value = null;
};

const submit = () => {
    if (editingTag.value) {
        form.put(route('tickets.labels.update', editingTag.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('tickets.labels.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteForm = useForm({});
const deleteTag = ref(null);
const showDeleteModal = ref(false);

const openDeleteModal = (tag) => {
    deleteTag.value = tag;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteTag.value = null;
};

const confirmDelete = () => {
    if (!deleteTag.value) return;
    deleteForm.delete(route('tickets.labels.destroy', deleteTag.value.id), {
        onFinish: () => {
            closeDeleteModal();
        },
    });
};
</script>

<template>
    <Head title="Label Manager" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Label Manager
                </h2>
                <PrimaryButton @click="openCreateModal">
                    <FontAwesomeIcon icon="plus" class="mr-2" />
                    Create Label
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 overflow-hidden">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <div v-if="tags.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                            No labels found. Create one to get started.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                            Name
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                            Slug
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <tr v-for="tag in tags" :key="tag.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-uh-teal/20 text-uh-slate dark:text-uh-cream border border-uh-teal/30 dark:border-uh-teal/50">
                                                {{ tag.name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ tag.slug }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button 
                                                @click="openEditModal(tag)" 
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-4"
                                            >
                                                Edit
                                            </button>
                                            <button 
                                                @click="openDeleteModal(tag)" 
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6 bg-white dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingTag ? 'Edit Label' : 'Create Label' }}
                </h2>

                <div class="mt-6">
                    <InputLabel for="name" value="Name" />
                    <TextInput
                        id="name"
                        ref="nameInput"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Label Name"
                        @keyup.enter="submit"
                    />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> Cancel </SecondaryButton>
                    <PrimaryButton
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ editingTag ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal">
            <div class="p-6 bg-white dark:bg-gray-800">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Delete Label
                </h2>

                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    Are you sure you want to delete the label
                    <span class="font-semibold">"{{ deleteTag?.name }}"</span>?
                    This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModal"> Cancel </SecondaryButton>
                    <PrimaryButton
                        class="ml-3 bg-red-600 hover:bg-red-700 focus:ring-red-500"
                        :class="{ 'opacity-25': deleteForm.processing }"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                    >
                        Delete
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
