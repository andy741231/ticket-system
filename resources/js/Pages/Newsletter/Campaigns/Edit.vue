<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/solid';
import { ClockIcon, UserGroupIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  campaign: Object,
  templates: Array,
  groups: Array,
});

// Initialize form with campaign data
const form = useForm({
  name: props.campaign?.name || '',
  subject: props.campaign?.subject || '',
  from_name: props.campaign?.from_name || 'UHPH',
  from_email: props.campaign?.from_email || 'noreply@uhphub.com',
  reply_to: props.campaign?.reply_to || 'noreply@uhphub.com',
  preview_text: props.campaign?.preview_text || '',
  content: props.campaign?.content || '',
  html_content: props.campaign?.html_content || '',
  template_id: props.campaign?.template_id || '',
  send_type: props.campaign?.send_type || 'immediate',
  scheduled_date: props.campaign?.scheduled_at ? new Date(props.campaign.scheduled_at).toISOString().split('T')[0] : '',
  scheduled_time: props.campaign?.scheduled_at ? new Date(props.campaign.scheduled_at).toTimeString().slice(0, 5) : '12:00',
  scheduled_at: props.campaign?.scheduled_at || null,
  recurring_config: props.campaign?.recurring_config || null,
  target_groups: props.campaign?.target_groups?.map(g => g.id) || [],
  send_to_all: props.campaign?.send_to_all || false,
});

const isSending = ref(false);
const sendStatus = ref({ type: '', message: '' });
const sendError = ref(null);

// Initialize recurring config if needed
const initRecurringConfig = () => {
  if (form.send_type === 'recurring' && !form.recurring_config) {
    form.recurring_config = {
      frequency: 'weekly',
      days_of_week: [],
      day_of_month: 1,
      time: '09:00',
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      start_date: new Date().toISOString().split('T')[0],
      end_date: null
    };
  }
};

onMounted(() => {
  initRecurringConfig();
});

// Handle form submission
const submit = async () => {
  isSending.value = true;
  sendStatus.value = { type: 'info', message: 'Updating campaign...' };
  sendError.value = null;
  
  // Format date for backend with proper timezone handling
  const formatDate = (dateStr, timeStr) => {
    if (!dateStr) return null;
    
    // Parse date and time in local timezone
    const [year, month, day] = dateStr.split('-').map(Number);
    const [hours, minutes] = (timeStr || '12:00').split(':').map(Number);
    
    // Create date in local timezone
    const scheduledDate = new Date(year, month - 1, day, hours, minutes, 0, 0);
    
    // Get current time in local timezone
    const now = new Date();
    
    // Add 2 minutes buffer to account for any time differences
    const bufferMs = 2 * 60 * 1000; // 2 minutes in milliseconds
    const nowWithBuffer = new Date(now.getTime() + bufferMs);
    
    console.log('Selected date:', dateStr);
    console.log('Selected time:', timeStr);
    console.log('Scheduled time (local):', scheduledDate.toString());
    console.log('Current time (local):', now.toString());
    console.log('Current time with buffer:', nowWithBuffer.toString());
    console.log('Time difference (minutes):', (scheduledDate - now) / (1000 * 60));
    
    if (scheduledDate <= nowWithBuffer) {
      console.error('Scheduled time is too close to current time or in the past');
      throw new Error('Scheduled time must be at least 2 minutes in the future');
    }
    
    // Convert to UTC and format as 'YYYY-MM-DD HH:mm:ss' (server-friendly)
    const isoUtc = scheduledDate.toISOString(); // e.g. 2025-09-03T18:50:00.000Z
    const utcFormatted = isoUtc.substring(0, 19).replace('T', ' '); // 2025-09-03 18:50:00
    console.log('Formatted scheduled_at (UTC):', utcFormatted);
    return utcFormatted;
  };

  // Handle scheduled date/time
  let scheduledAt = null;
  if (form.send_type === 'scheduled' && form.scheduled_date) {
    scheduledAt = formatDate(form.scheduled_date, form.scheduled_time);
  }

  // Build payload
  const payload = {
    name: form.name,
    subject: form.subject,
    from_name: form.from_name,
    from_email: form.from_email,
    reply_to: form.reply_to || form.from_email,
    content: form.content,
    html_content: form.html_content || undefined,
    template_id: form.template_id || undefined,
    send_type: form.send_type,
    send_to_all: !!form.send_to_all,
    target_groups: form.send_to_all ? [] : (Array.isArray(form.target_groups) ? form.target_groups : []),
    scheduled_at: scheduledAt || undefined,
  };

  if (form.send_type === 'recurring' && form.recurring_config) {
    payload.recurring_config = form.recurring_config;
  }

  // Handle target groups - ensure it's always an array of valid group IDs
  let validTargetGroups = [];
  if (form.send_to_all) {
    // If sending to all, set target_groups to empty array
    validTargetGroups = [];
  } else if (form.target_groups && form.target_groups.length > 0) {
    // Only include groups that exist in the available groups list
    validTargetGroups = form.target_groups
      .filter(id => props.groups.some(g => g.id == id))
      .map(id => Number(id)); // Ensure IDs are numbers (not strings)
  }

  // Validate we have target groups if not sending to all
  if (!form.send_to_all && validTargetGroups.length === 0) {
    sendError.value = 'Please select at least one valid target group or select "Send to all subscribers"';
    isSending.value = false;
    return;
  }

  // Prepare the final payload
  const finalPayload = {
    ...payload,
    target_groups: validTargetGroups,
    send_to_all: !!form.send_to_all,
    // Only include scheduled_at if it's a future date and we're in scheduled mode
    scheduled_at: (form.send_type === 'scheduled' || form.send_type === 'recurring') ? scheduledAt : null
  };
  
  // Remove scheduled_at if it's not needed for the current send type
  if (form.send_type === 'immediate') {
    delete finalPayload.scheduled_at;
  }

  // Submit with axios so we send exactly the payload we built
  try {
    console.log('Submitting payload:', finalPayload);
    await axios.put(
      route('newsletter.campaigns.update', { campaign: props.campaign.id }),
      finalPayload,
      { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
    );
    sendStatus.value = { type: 'success', message: 'Campaign updated successfully!' };
    isSending.value = false;
    // Redirect to show page after a short delay
    setTimeout(() => {
      window.location.href = route('newsletter.campaigns.show', { campaign: props.campaign.id });
    }, 800);
  } catch (e) {
    const errors = e?.response?.data?.errors || {};
    console.error('Validation errors:', errors);

    const errorMessages = [];
    if (errors.scheduled_at) {
      errorMessages.push(Array.isArray(errors.scheduled_at) ? errors.scheduled_at[0] : String(errors.scheduled_at));
    }
    if (errors['target_groups.0']) {
      errorMessages.push(Array.isArray(errors['target_groups.0']) ? errors['target_groups.0'][0] : String(errors['target_groups.0']));
    }
    // Any other errors
    Object.entries(errors).forEach(([key, value]) => {
      if (key !== 'scheduled_at' && key !== 'target_groups.0') {
        errorMessages.push(Array.isArray(value) ? value[0] : String(value));
      }
    });
    sendError.value = errorMessages.length ? errorMessages.join(' ') : (e?.response?.data?.message || 'Failed to update campaign.');
    isSending.value = false;
  }
};
</script>

<template>
  <Head :title="`Edit Campaign: ${form.name}`" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Edit Campaign: {{ form.name }}
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Status Messages -->
        <div v-if="sendStatus.message" 
             class="mb-4 p-4 rounded-md"
             :class="{
               'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-200': sendStatus.type === 'info',
               'bg-green-50 dark:bg-green-900 text-green-700 dark:text-green-200': sendStatus.type === 'success'
             }">
          <div class="flex items-center">
            <CheckCircleIcon v-if="sendStatus.type === 'success'" class="h-5 w-5 mr-2" />
            <span>{{ sendStatus.message }}</span>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="sendError" class="mb-4 p-4 bg-red-50 dark:bg-red-900 text-red-700 dark:text-red-200 rounded-md">
          <div class="flex items-center">
            <ExclamationTriangleIcon class="h-5 w-5 mr-2" />
            <span>{{ sendError }}</span>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <!-- Campaign Form Content -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                <DocumentTextIcon class="w-5 h-5 inline-block mr-2" />
                Campaign Details
              </h3>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Campaign Name <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Email Subject <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.subject"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
              </div>
            </div>
            
            <!-- Send Options -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                <ClockIcon class="w-5 h-5 inline-block mr-2" />
                Send Options
              </h3>
              
              <div class="space-y-4">
                <div class="space-y-2">
                  <div class="flex items-center space-x-4">
                    <label class="inline-flex items-center">
                      <input
                        v-model="form.send_type"
                        type="radio"
                        value="immediate"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                      />
                      <span class="ml-2 text-gray-700 dark:text-gray-300">Send immediately</span>
                    </label>
                    
                    <label class="inline-flex items-center">
                      <input
                        v-model="form.send_type"
                        type="radio"
                        value="scheduled"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                      />
                      <span class="ml-2 text-gray-700 dark:text-gray-300">Schedule for later</span>
                    </label>
                  </div>
                  
                  <!-- Scheduled Date Picker -->
                  <div v-if="form.send_type === 'scheduled'" class="mt-4 space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Scheduled Date <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="date"
                        v-model="form.scheduled_date"
                        :min="new Date().toISOString().split('T')[0]"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        required
                      >
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Scheduled Time <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="time"
                        v-model="form.scheduled_time"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        required
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Target Groups Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                <UserGroupIcon class="w-5 h-5 inline-block mr-2" />
                Recipients
              </h3>
              
              <div class="space-y-4">
                <div class="flex items-start">
                  <div class="flex items-center h-5">
                    <input
                      id="send_to_all"
                      v-model="form.send_to_all"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="send_to_all" class="font-medium text-gray-700 dark:text-gray-300">
                      Send to all subscribers
                    </label>
                    <p class="text-gray-500 dark:text-gray-400">
                      Send this campaign to all active subscribers in your list.
                    </p>
                  </div>
                </div>
                
                <div v-if="!form.send_to_all" class="mt-4">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Select Target Groups <span class="text-red-500">*</span>
                  </label>
                  <div v-if="groups.length > 0" class="space-y-2 max-h-60 overflow-y-auto p-2 border rounded-md">
                    <div v-for="group in groups" :key="group.id" class="flex items-center">
                      <input
                        :id="`group-${group.id}`"
                        v-model="form.target_groups"
                        :value="group.id"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                      />
                      <label :for="`group-${group.id}`" class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                        {{ group.name }} 
                        <span class="text-xs text-gray-500">({{ group.subscribers_count || 0 }} subscribers)</span>
                      </label>
                    </div>
                  </div>
                  <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                    No subscriber groups found. Please create groups first.
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Email Content Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Email Content
              </h3>
              <EmailBuilder 
                :initial-content="form.content"
                @update:content="form.content = $event"
                @update:html-content="form.html_content = $event"
              />
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-4">
              <Link 
                :href="route('newsletter.campaigns.index')" 
                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600"
              >
                Cancel
              </Link>
              <button
                type="button"
                @click="submit"
                :disabled="isSending"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
              >
                <span v-if="isSending">Saving...</span>
                <span v-else>Save Changes</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
