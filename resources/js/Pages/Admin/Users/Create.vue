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
</script>

<template>
    <Head title="Create New User" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Create New User
                </h2>
            </div>
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
                                    <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="username" value="Username" />
                                    <TextInput id="username" type="text" class="mt-1 block w-full" v-model="form.username" required />
                                    <InputError class="mt-2" :message="form.errors.username" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="Email" />
                                    <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                                <div>
                                    <InputLabel for="password" value="Password" />
                                    <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>
                                <div>
                                    <InputLabel for="password_confirmation" value="Confirm Password" />
                                    <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" required />
                                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                                </div>
                            </div>

                            <!-- Roles -->
                            <div>
                                <InputLabel value="Roles" />
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div v-for="role in roles" :key="role.id" class="flex items-center">
                                        <Checkbox :id="`role_${role.id}`" :value="role.id" v-model:checked="form.roles" />
                                        <label :for="`role_${role.id}`" class="ml-2">{{ role.name }}</label>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>

                            <!-- Description (Tiptap Editor) -->
                            <div>
                                <InputLabel for="description" value="Description" />
                                <div class="mt-1">
                                    <div class="border border-gray-300 dark:border-gray-700 rounded-md shadow-sm overflow-hidden">
                                        <div v-if="editor" class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-2 flex flex-wrap items-center gap-2 text-gray-700 dark:text-gray-300">
                                            <!-- Editor buttons -->
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().redo().run()" :disabled="!editor.can().redo()" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-50"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.96 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg></button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('bold')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('italic')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('underline')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('strike')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z"/></svg></button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('heading', { level: 1 })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 font-bold">H1</button>
                                                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('heading', { level: 2 })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 font-bold">H2</button>
                                                <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('heading', { level: 3 })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 font-bold">H3</button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'left' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'center' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M7 15v2h10v-2H7zm-4 6h18v-2H3v2zm0-8h18v-2H3v2zm4-6v2h10V7H7zM3 3v2h18V3H3z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive({ textAlign: 'right' })}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M3 21h18v-2H3v2zm6-4h12v-2H9v2zm-6-4h18v-2H3v2zm6-4h12V7H9v2zM3 3v2h18V3H3z"/></svg></button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('bulletList')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6s-.67-1.5-1.5-1.5zm0 12c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8h14V3H7v2z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('orderedList')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().toggleTaskList().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('taskList')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M22 6.98V19c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V5c0-1.1.9-2 2-2h10.1C14.04 3.34 14 3.66 14 4c0 .55.22 1.05.59 1.41L15 6l-1.41 1.41c-.38.37-.59.87-.59 1.42 0 .28.06.54.16.78H4v2h10.1c.05.23.13.45.23.66.11.21.24.41.39.59l2.28 2.28.71-.71-2.28-2.28c-.08-.08-.14-.17-.2-.26H4v-2h12.35c.08-.24.15-.5.15-.78s-.07-.54-.15-.78H4V5h9.35c.43.9.98 1.73 1.65 2.41.21.21.44.4.69.57L22 6.98zM15 5c0-.55.45-1 1-1s1 .45 1 1-.45 1-1 1-1-.45-1-1z"/></svg></button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('blockquote')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M6 17h3l2-4V7H5v6h3l-2 4zm8 0h3l2-4V7h-6v6h3l-2 4z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('codeBlock')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg></button>
                                                <button type="button" @click="editor.chain().focus().setHorizontalRule().run()" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M19 13H5v-2h14v2z"/></svg></button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="setLink" :class="{'bg-gray-200 dark:bg-gray-600': editor.isActive('link')}" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg></button>
                                                <button type="button" @click="addImage" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600"><svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 0 24 24" width="20" class="fill-current"><path d="M0 0h24v24H0z" fill="none"/><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg></button>
                                            </div>
                                            <div class="w-px h-5 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                                            <div class="flex items-center">
                                                <input type="color" @input="editor.chain().focus().setColor($event.target.value).run()" :value="editor.getAttributes('textStyle').color" class="w-8 h-8 p-1 bg-transparent border-none cursor-pointer rounded">
                                            </div>
                                        </div>
                                        <div class="p-4 bg-white dark:bg-gray-900 min-h-[300px] max-h-[600px] overflow-y-auto">
                                            <EditorContent :editor="editor" />
                                        </div>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div class="flex items-center justify-end space-x-4">
                                <Link :href="route('admin.users.index')" class="inline-flex items-center px-4 py-2 bg-uh-slate border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-uh-gray focus:bg-uh-gray active:bg-uh-black focus:outline-none focus:ring-2 focus:ring-uh-slate focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Cancel
                                </Link>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
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
