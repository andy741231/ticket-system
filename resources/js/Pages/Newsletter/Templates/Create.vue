<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';

const props = defineProps({
  templates: {
    type: Array,
    default: () => [],
  },
});

const builderContent = ref('');
const builderHtml = ref('');
const showHtmlView = ref(false);

const form = useForm({
  name: '',
  description: '',
  content: '',
  html_content: '',
  is_default: false,
});

watch(builderContent, (val) => {
  form.content = val;
});
watch(builderHtml, (val) => {
  form.html_content = val;
});

function submit() {
  form.post(route('newsletter.templates.store'));
}

function toggleHtmlView() {
  showHtmlView.value = !showHtmlView.value;
}
</script>

<template>
  <Head title="Create Template" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Create Email Template
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Define the name and content for your reusable template
          </p>
        </div>
        <Link :href="route('newsletter.templates.index')" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
          Back to Templates
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 space-y-6">
            <!-- Form Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  placeholder="e.g., Default Newsletter"
                />
                <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Default Template</label>
                <div class="mt-2 flex items-center">
                  <input id="is_default" type="checkbox" v-model="form.is_default" class="h-4 w-4 text-blue-600 border-gray-300 rounded" />
                  <label for="is_default" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Set as default</label>
                </div>
              </div>

              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                <textarea
                  v-model="form.description"
                  rows="2"
                  class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  placeholder="Optional description"
                />
                <div v-if="form.errors.description" class="text-sm text-red-600 mt-1">{{ form.errors.description }}</div>
              </div>
            </div>

            <!-- Email Builder -->
            <div>
              <div v-if="!showHtmlView">
                <EmailBuilder
                  v-model="builderContent"
                  @update:html-content="(val) => (builderHtml = val)"
                  @toggle-html-view="toggleHtmlView"
                  :templates="templates"
                />
              </div>
              <div v-else class="p-2">
                <div class="flex justify-between items-center mb-2">
                  <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">HTML Source</h4>
                  <button type="button" @click="toggleHtmlView" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">
                    ← Back to Visual Editor
                  </button>
                </div>
                <textarea
                  v-model="form.html_content"
                  rows="20"
                  class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm font-mono text-sm"
                  placeholder="Enter your HTML content here..."
                ></textarea>
              </div>
              <div v-if="form.errors.content" class="text-sm text-red-600 mt-2">{{ form.errors.content }}</div>
              <div v-if="form.errors.html_content" class="text-sm text-red-600 mt-1">{{ form.errors.html_content }}</div>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
            <Link :href="route('newsletter.templates.index')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</Link>
            <button
              @click="submit"
              :disabled="form.processing || !form.name || !form.html_content"
              class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
            >
              {{ form.processing ? 'Saving...' : 'Save Template' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
