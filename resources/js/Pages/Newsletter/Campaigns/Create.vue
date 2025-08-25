<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { CalendarIcon, UserGroupIcon, ClockIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  templates: Array,
  groups: Array,
});

const form = useForm({
  name: '',
  subject: '',
  preview_text: '',
  content: '',
  html_content: '',
  template_id: '',
  send_type: 'immediate',
  scheduled_at: '',
  recurring_config: {},
  target_groups: [],
  send_to_all: true,
});

const showHtmlView = ref(false);
const htmlContent = ref('');

const recipientCount = computed(() => {
  if (form.send_to_all) {
    return 'All active subscribers';
  }
  
  if (form.target_groups.length === 0) {
    return '0 subscribers';
  }
  
  const selectedGroups = props.groups.filter(g => form.target_groups.includes(g.id));
  const totalSubscribers = selectedGroups.reduce((sum, group) => sum + group.active_subscriber_count, 0);
  return `${totalSubscribers} subscribers`;
});

function updateContent(content) {
  form.content = content;
}

function updateHtmlContent(html) {
  htmlContent.value = html;
  form.html_content = html;
}

function toggleHtmlView() {
  showHtmlView.value = !showHtmlView.value;
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      content: typeof data.content === 'string' ? safeParseJson(data.content) ?? {} : data.content,
      template_id: data.template_id === '' ? null : data.template_id,
      scheduled_at: data.scheduled_at === '' ? null : data.scheduled_at,
    }))
    .post(route('newsletter.campaigns.store'));
}

function saveDraft() {
  form
    .transform(data => ({
      ...data,
      // Keep as immediate; backend treats immediate as draft until explicitly sent
      send_type: 'immediate',
      content: typeof data.content === 'string' ? safeParseJson(data.content) ?? {} : data.content,
      template_id: data.template_id === '' ? null : data.template_id,
      scheduled_at: data.scheduled_at === '' ? null : data.scheduled_at,
    }))
    .post(route('newsletter.campaigns.store'));
}

function safeParseJson(str) {
  try {
    return JSON.parse(str);
  } catch (e) {
    return null;
  }
}
</script>

<template>
  <Head title="Create Campaign" />
  <AuthenticatedLayout>
    <template #header>
      <div>
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Create New Campaign
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Design and configure your email campaign
        </p>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit" class="space-y-6">
          
          <!-- Campaign Settings -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
              Campaign Settings
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Campaign Name
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  placeholder="e.g., Weekly Newsletter #42"
                />
                <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">
                  {{ form.errors.name }}
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Email Subject
                </label>
                <input
                  v-model="form.subject"
                  type="text"
                  required
                  class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  placeholder="e.g., Your Weekly Update from UHPH"
                />
                <div v-if="form.errors.subject" class="text-red-600 text-sm mt-1">
                  {{ form.errors.subject }}
                </div>
              </div>

              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Preview Text (Optional)
                </label>
                <input
                  v-model="form.preview_text"
                  type="text"
                  class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                  placeholder="Short preview text that appears in email clients"
                />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  This text appears in the email preview in most email clients
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Template (Optional)
                </label>
                <select
                  v-model="form.template_id"
                  class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                >
                  <option value="">Start from scratch</option>
                  <option v-for="template in templates" :key="template.id" :value="template.id">
                    {{ template.name }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Recipients -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6 flex items-center">
              <UserGroupIcon class="w-5 h-5 mr-2" />
              Recipients
            </h3>
            
            <div class="space-y-4">
              <div class="flex items-center">
                <input
                  v-model="form.send_to_all"
                  type="checkbox"
                  id="send_to_all"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                />
                <label for="send_to_all" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                  Send to all active subscribers
                </label>
              </div>

              <div v-if="!form.send_to_all" class="space-y-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Select Groups
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <label
                    v-for="group in groups"
                    :key="group.id"
                    class="flex items-center p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                  >
                    <input
                      v-model="form.target_groups"
                      :value="group.id"
                      type="checkbox"
                      class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                    />
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ group.name }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ group.active_subscriber_count }} subscribers
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                <div class="text-sm font-medium text-blue-900 dark:text-blue-100">
                  Campaign will be sent to: {{ recipientCount }}
                </div>
              </div>
            </div>
          </div>

          <!-- Scheduling -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6 flex items-center">
              <ClockIcon class="w-5 h-5 mr-2" />
              Scheduling
            </h3>
            
            <div class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                  <input
                    v-model="form.send_type"
                    value="immediate"
                    type="radio"
                    class="text-blue-600 focus:ring-blue-500"
                  />
                  <div class="ml-3">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      Send Immediately
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      Send as soon as you click send
                    </div>
                  </div>
                </label>

                <label class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                  <input
                    v-model="form.send_type"
                    value="scheduled"
                    type="radio"
                    class="text-blue-600 focus:ring-blue-500"
                  />
                  <div class="ml-3">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      Schedule for Later
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      Choose a specific date and time
                    </div>
                  </div>
                </label>

                <label class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                  <input
                    v-model="form.send_type"
                    value="recurring"
                    type="radio"
                    class="text-blue-600 focus:ring-blue-500"
                  />
                  <div class="ml-3">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                      Recurring
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      Send on a regular schedule
                    </div>
                  </div>
                </label>
              </div>

              <div v-if="form.send_type === 'scheduled'" class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Schedule Date & Time
                </label>
                <input
                  v-model="form.scheduled_at"
                  type="datetime-local"
                  :min="new Date().toISOString().slice(0, 16)"
                  class="w-full md:w-auto border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                />
              </div>
            </div>
          </div>

          <!-- Email Content -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Email Content
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Design your email using the visual editor or HTML
              </p>
            </div>
            
            <div v-if="!showHtmlView">
              <EmailBuilder
                v-model="form.content"
                :templates="templates"
                @update:html-content="updateHtmlContent"
                @toggle-html-view="toggleHtmlView"
              />
            </div>

            <div v-else class="p-6">
              <div class="flex justify-between items-center mb-4">
                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  HTML Source
                </h4>
                <button
                  type="button"
                  @click="toggleHtmlView"
                  class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500"
                >
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
          </div>

          <!-- Actions -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-col sm:flex-row gap-4 justify-between">
              <div class="text-sm text-gray-600 dark:text-gray-400">
                <p>Campaign will be sent to {{ recipientCount }}</p>
                <p v-if="form.send_type === 'scheduled' && form.scheduled_at">
                  Scheduled for {{ new Date(form.scheduled_at).toLocaleString() }}
                </p>
              </div>
              
              <div class="flex gap-3">
                <button
                  type="button"
                  @click="saveDraft"
                  :disabled="form.processing"
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
                >
                  Save as Draft
                </button>
                
                <button
                  type="submit"
                  :disabled="form.processing || !form.name || !form.subject || !form.html_content"
                  class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                >
                  <span v-if="form.send_type === 'immediate'">Create Campaign</span>
                  <span v-else-if="form.send_type === 'scheduled'">Schedule Campaign</span>
                  <span v-else>Create Campaign</span>
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
