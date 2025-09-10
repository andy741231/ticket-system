<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';

const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
  templates: {
    type: Array,
    default: () => [],
  },
});

// Initialize builder content from existing template (avoid double-stringify)
const initialContent = typeof props.template?.content === 'string'
  ? props.template.content
  : (props.template?.content ? JSON.stringify(props.template.content) : '');
const builderContent = ref(initialContent);
const builderHtml = ref(props.template?.html_content || '');
const showHtmlView = ref(false);

const form = useForm({
  name: props.template?.name || '',
  description: props.template?.description || '',
  content: initialContent,
  html_content: props.template?.html_content || '',
  is_default: !!props.template?.is_default,
});

watch(builderContent, (val) => {
  form.content = val;
});
watch(builderHtml, (val) => {
  form.html_content = val;
});

function submit() {
  const transformedData = {
    ...form.data(),
    // Send as integers to satisfy Laravel boolean validation unequivocally
    is_default: form.is_default ? 1 : 0,
    make_default: form.is_default ? 1 : 0 // Also send as make_default for backward compatibility
  };
  
  form
    .transform(() => transformedData)
    .put(route('newsletter.templates.update', props.template.id), {
      onSuccess: () => {
        // Success handled by global toast via Inertia flash, if set on backend
      },
      onError: (errors) => {
        console.error('Error updating template:', errors);
        // Errors can be displayed via form errors; global toast will handle flash
      },
      onFinish: () => {
        form.transform((d) => d);
      },
    });
}

function deleteTemplate() {
  if (confirm(`Delete template "${props.template.name}"? This cannot be undone.`)) {
    router.delete(route('newsletter.templates.destroy', props.template.id));
  }
}

function toggleHtmlView() {
  showHtmlView.value = !showHtmlView.value;
}
</script>

<template>
  <Head :title="`Edit Template - ${props.template.name}`" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit Template
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Update name, description, and content
          </p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('newsletter.templates.index')" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">
            Back to Templates
          </Link>
          <button @click="deleteTemplate" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">Delete</button>
        </div>
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
                />
                <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Default Template</label>
                <div class="mt-2 flex items-center">
                  <input 
                    id="is_default" 
                    type="checkbox" 
                    v-model="form.is_default" 
                    class="h-4 w-4 text-uh-red border-gray-300 rounded focus:ring-uh-red" 
                  />
                  <label for="is_default" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                    Set as default template for new campaigns
                  </label>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  When checked, this template will be used as the starting point for new campaigns instead of the basic layout.
                </p>
                <div v-if="form.errors.is_default" class="text-sm text-red-600 mt-1">{{ form.errors.is_default }}</div>
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
              {{ form.processing ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
