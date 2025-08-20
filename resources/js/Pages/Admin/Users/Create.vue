<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TiptapLink from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import { onBeforeUnmount } from 'vue';
import { useHasAny } from '@/Extensions/useAuthz';
import { Color } from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
    description: '',
});

const editor = useEditor({
    extensions: [
        StarterKit,
        Underline,
        TiptapLink,
        Image,
        Table,
        TableCell,
        TableHeader,
        TableRow,
        Highlight,
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Color,
        TextStyle,
        TaskList,
        TaskItem,
    ],
    content: form.description,
    onUpdate: ({ editor }) => {
        form.description = editor.getHTML();
    },
});

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const setLink = () => {
    const url = window.prompt('URL');
    if (url) {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
};

const addImage = () => {
    const url = window.prompt('URL');
    if (url) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
};

const canManageRbacRoles = useHasAny(['admin.rbac.roles.manage']);
</script>

<template>
    <Head title="Create New User" />

    <AuthenticatedLayout>
        <template #header>
           
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 text-uh-slate dark:text-uh-cream overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Form fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="name" value="Name" />
                                    <TextInput id="name" type="text" class="dark:bg-gray-700 mt-1 block w-full" v-model="form.name" required autofocus />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="username" value="Username" />
                                    <TextInput id="username" type="text" class="dark:bg-gray-700 mt-1 block w-full" v-model="form.username" required />
                                    <InputError class="mt-2" :message="form.errors.username" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="Email" />
                                    <TextInput id="email" type="email" class="dark:bg-gray-700 mt-1 block w-full" v-model="form.email" required />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                                <div>
                                    <InputLabel for="password" value="Password" />
                                    <TextInput id="password" type="password" class="dark:bg-gray-700 mt-1 block w-full" v-model="form.password" required />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>
                                <div>
                                    <InputLabel for="password_confirmation" value="Confirm Password" />
                                    <TextInput id="password_confirmation" type="password" class="dark:bg-gray-700 mt-1 block w-full" v-model="form.password_confirmation" required />
                                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                                </div>
                            </div>

                            <!-- Roles -->
                            <div>
                                <InputLabel value="Roles" />
                                <div v-if="!canManageRbacRoles" class="mt-2 rounded-md border border-yellow-300 bg-yellow-50 p-2 text-xs text-yellow-900">
                                    Read-only: you do not have permission to manage roles.
                                </div>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div v-for="role in roles" :key="role.id" class="flex items-center">
                                        <Checkbox :id="`role_${role.id}`" :value="role.id" v-model:checked="form.roles" :disabled="!canManageRbacRoles" class="dark:bg-gray-700"/>
                                        <label :for="`role_${role.id}`" class="ml-2 ">{{ role.name }}</label>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>

                            

                            <div class="flex items-center justify-end space-x-4">
                                <Link :href="route('admin.users.index')" class="inline-flex items-center px-4 py-2 bg-uh-slate border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-gray focus:bg-uh-gray active:bg-uh-black focus:outline-none focus:ring-2 focus:ring-uh-slate focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Cancel
                                </Link>
                                <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Create User
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
    background-color: #1f2937; /* dark:bg-gray-800 */
    color: #f9fafb; /* dark:text-gray-100 */
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

/* Links */
:deep(.ProseMirror a) {
    color: #007BFF;
    text-decoration: underline;
    cursor: pointer;
}
.dark :deep(.ProseMirror a) {
    color: #38bdf8;
}

/* Blockquotes */
:deep(.ProseMirror blockquote) {
    margin: 1rem 0;
    padding-left: 1rem;
    border-left: 4px solid #e5e7eb; /* border-gray-200 */
    font-style: italic;
    color: #6b7280; /* text-gray-500 */
}
.dark :deep(.ProseMirror blockquote) {
    border-left-color: #4b5563; /* dark:border-gray-600 */
    color: #9ca3af; /* dark:text-gray-400 */
}

/* Code */
:deep(.ProseMirror code) {
    background-color: #f3f4f6; /* bg-gray-100 */
    color: #1f2937;
    padding: 0.2em 0.4em;
    border-radius: 0.25rem;
    font-size: 0.9em;
}
.dark :deep(.ProseMirror code) {
    background-color: #374151; /* dark:bg-gray-700 */
    color: #e5e7eb;
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
