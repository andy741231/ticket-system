<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { 
  Dialog, 
  DialogPanel, 
  TransitionChild, 
  TransitionRoot,
  DialogTitle
} from '@headlessui/vue';
import { 
  CalendarIcon, 
  UserGroupIcon, 
  ClockIcon, 
  PaperAirplaneIcon, 
  DocumentTextIcon 
} from '@heroicons/vue/24/outline';
import { 
  XMarkIcon, 
  CheckCircleIcon, 
  ExclamationTriangleIcon 
} from '@heroicons/vue/24/solid';

const props = defineProps({
  templates: Array,
  groups: Array,
});

// Initialize with default content structure
const defaultContent = {
  blocks: [
    {
      id: 'header',
      type: 'header',
      data: {
        logo: '',
        title: 'Your Brand',
        subtitle: 'A short description',
        backgroundColor: '#f3f4f6'
      },
      editable: true,
      locked: false
    },
    {
      id: 'content',
      type: 'text',
      data: {
        text: '<p>Start writing your email content here...</p>',
        textAlign: 'left',
        backgroundColor: '#ffffff',
        padding: '20px'
      },
      editable: true,
      locked: false
    },
    {
      id: 'footer',
      type: 'footer',
      data: {
        text: '© ' + new Date().getFullYear() + ' Your Company. All rights reserved.',
        backgroundColor: '#f3f4f6',
        textColor: '#6b7280',
        padding: '20px',
        textAlign: 'center'
      },
      editable: true,
      locked: true
    }
  ],
  settings: {
    width: '600px',
    backgroundColor: '#ffffff',
    contentBackgroundColor: '#ffffff',
    textColor: '#1f2937',
    fontFamily: 'Arial, sans-serif',
    linkColor: '#3b82f6'
  }
};

const form = useForm({
  name: '',
  subject: '',
  from_name: 'UHPH',
  from_email: 'noreply@uhphub.com',
  reply_to: 'noreply@uhphub.com',
  preview_text: '',
  content: JSON.stringify(defaultContent),
  html_content: '',
  template_id: '',
  send_type: 'immediate',
  scheduled_date: '',
  scheduled_time: '12:00',
  scheduled_at: null,
  recurring_config: null,
  target_groups: [],
  send_to_all: false,
});

// Initialize recurring config only when needed
const initRecurringConfig = () => {
  if (form.send_type === 'recurring' && !form.recurring_config) {
    form.recurring_config = {
      frequency: 'weekly',
      days_of_week: [],
      day_of_month: 1,
      has_end_date: false,
      end_date: '',
      occurrences: null
    };
  } else if (form.send_type !== 'recurring') {
    form.recurring_config = null;
  }
};

// Initialize on component mount
onMounted(() => {
  initRecurringConfig();
  
  // Set default scheduled time to next hour
  if (!form.scheduled_at) {
    const now = new Date();
    now.setHours(now.getHours() + 1, 0, 0, 0);
    form.scheduled_at = now.toISOString().slice(0, 16);
  }
  
  // Log groups data for debugging
  console.log('Groups data:', props.groups);
  if (Array.isArray(props.groups)) {
    props.groups.forEach(group => {
      console.log(`Group: ${group.name} (${group.id}) - Subscribers: ${group.active_subscriber_count}`);
    });
  }
  
  // Watch for send_type changes
  watch(() => form.send_type, (newVal) => {
    if (newVal === 'recurring' && !form.recurring_config) {
      initRecurringConfig();
    } else if (newVal !== 'recurring') {
      form.recurring_config = null;
    }
  });
});

const showHtmlView = ref(false);
const showSendConfirmation = ref(false);
const isSending = ref(false);
const sendStatus = ref({ type: null, message: '' });
const sendError = ref(null);
const recipientCount = ref(0);
const isLoadingRecipients = ref(false);

// Get unique subscribers across all selected groups
const getUniqueSubscribersCount = async (groupIds) => {
  try {
    if (!groupIds || groupIds.length === 0) {
      return 0;
    }
    
    const url = route('newsletter.groups.unique-subscribers');
    console.log('Making request to:', url);
    
    const token = document.head.querySelector('meta[name="csrf-token"]')?.content;
    
    const response = await axios.post(url, {
      group_ids: groupIds
    }, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token
      },
      withCredentials: true
    });
    
    console.log('[getUniqueSubscribersCount] Response:', response.data);
    
    // Check for the expected response format
    if (response.data && typeof response.data.unique_subscribers_count !== 'undefined') {
      return response.data.unique_subscribers_count;
    }
    
    // Fallback: check if the response is a direct number
    if (typeof response.data === 'number') {
      return response.data;
    }
    
    console.error('[getUniqueSubscribersCount] Unexpected response format:', response.data);
    return 0;
  } catch (error) {
    console.error('Error fetching unique subscribers count:', error);
    return 0;
  }
};

// Get total active subscribers count from the server
const getTotalActiveSubscribers = async () => {
  try {
    const url = route('newsletter.subscribers.count');
    console.log('[getTotalActiveSubscribers] Fetching from:', url);

    const token = document.head.querySelector('meta[name="csrf-token"]')?.content;

    const response = await axios.get(url, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token
      },
      withCredentials: true
    });

    console.log('[getTotalActiveSubscribers] Response:', response.data);

    // Expected response: { total_active_subscribers: number }
    if (response.data && typeof response.data.total_active_subscribers !== 'undefined') {
      return response.data.total_active_subscribers;
    }

    // Fallback: if backend returns a direct number
    if (typeof response.data === 'number') {
      return response.data;
    }

    console.error('[getTotalActiveSubscribers] Unexpected response format:', response.data);
    return 0;
  } catch (error) {
    console.error('Error fetching total active subscribers count:', error);
    return 0;
  }
};

// Update recipient count based on current form state
const updateRecipientCount = async () => {
  console.log('[updateRecipientCount] Starting update, form:', JSON.stringify({
    send_to_all: form.send_to_all,
    target_groups: form.target_groups
  }));
  
  try {
    if (!Array.isArray(props.groups)) {
      console.error('[updateRecipientCount] props.groups is not an array:', props.groups);
      recipientCount.value = 0;
      return;
    }

    isLoadingRecipients.value = true;

    if (form.send_to_all) {
      console.log('[updateRecipientCount] Getting total active subscribers');
      const count = await getTotalActiveSubscribers();
      console.log('[updateRecipientCount] Total active subscribers count:', count);
      recipientCount.value = count;
    } else {
      const selected = Array.isArray(form.target_groups) ? form.target_groups : [];
      console.log('[updateRecipientCount] Selected groups:', selected);
      
      if (selected.length > 0) {
        console.log('[updateRecipientCount] Getting unique subscribers count for groups:', selected);
        const count = await getUniqueSubscribersCount(selected);
        console.log('[updateRecipientCount] Unique subscribers count:', count);
        recipientCount.value = count;
      } else {
        console.log('[updateRecipientCount] No groups selected');
        recipientCount.value = 0;
      }
    }
  } catch (error) {
    console.error('Error updating recipient count:', error);
    recipientCount.value = 0;
  } finally {
    isLoadingRecipients.value = false;
  }
};

// Watch for changes that should update the recipient count
watch(
  [() => form.send_to_all, () => form.target_groups, () => props.groups],
  () => {
    updateRecipientCount();
  },
  { immediate: true, deep: true }
);

function updateContent(content) {
  try {
    // Ensure content is a string (JSON)
    const contentStr = typeof content === 'string' ? content : JSON.stringify(content);
    form.content = contentStr;
    
    // If we have a valid content object, also update html_content
    if (contentStr && contentStr !== '{}') {
      const contentObj = typeof content === 'object' ? content : JSON.parse(contentStr);
      if (contentObj && contentObj.blocks) {
        // This will be updated by the EmailBuilder's html-content update
        // We just need to ensure the content is valid
      }
    }
  } catch (error) {
    console.error('Error updating content:', error);
    // Ensure we have at least an empty content structure
    form.content = JSON.stringify({
      blocks: [],
      settings: {}
    });
  }
}

function updateHtmlContent(html) {
  form.html_content = html;
}

function toggleHtmlView() {
  showHtmlView.value = !showHtmlView.value;
}

function confirmSend() {
  // Directly submit without confirmation
  submit();
}

async function submit() {
  isSending.value = true;
  sendStatus.value = { type: 'info', message: 'Preparing to send campaign...' };
  sendError.value = null;
  
  // Format date for backend
  const formatDate = (dateStr, timeStr) => {
    if (!dateStr) return null;
    
    // Parse date and time in local timezone
    const [year, month, day] = dateStr.split('-').map(Number);
    const [hours, minutes] = (timeStr || '12:00').split(':').map(Number);
    
    // Create date in local timezone
    const d = new Date(year, month - 1, day, hours, minutes, 0, 0);
    
    // Get current time in local timezone
    const now = new Date();
    now.setSeconds(0);
    now.setMilliseconds(0);
    
    console.log('Selected date:', dateStr);
    console.log('Selected time:', timeStr);
    console.log('Scheduled time (local):', d.toString());
    console.log('Current time (local):', now.toString());
    console.log('Time difference (minutes):', (d - now) / (1000 * 60));
    
    // Add 1 minute buffer
    now.setMinutes(now.getMinutes() - 1);
    
    if (d <= now) {
      console.error('Scheduled time is in the past');
      throw new Error('Scheduled time must be at least 1 minute in the future');
    }
    
    // Convert to ISO string for backend
    return d.toISOString();
  };

  // Process content - ensure we have valid content
  if (!form.content) {
    throw new Error('Email content is required');
  }

  let content;
  try {
    content = typeof form.content === 'string' 
      ? JSON.parse(form.content)
      : form.content;
    
    if (!content || typeof content !== 'object' || !Array.isArray(content.blocks)) {
      throw new Error('Invalid content format');
    }
  } catch (e) {
    console.error('Error parsing content:', e);
    throw new Error('Invalid email content format. Please check your content and try again.');
  }

  // Validate all required fields
  if (!form.name) {
    throw new Error('Campaign name is required');
  }
  
  if (!form.subject) {
    throw new Error('Email subject is required');
  }
  
  if (!form.from_name || !form.from_email) {
    throw new Error('From Name and From Email are required');
  }
  
  // Validate reply_to email if provided
  if (form.reply_to && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.reply_to)) {
    throw new Error('Please enter a valid reply-to email address');
  }

  // Validate recipients selection when not sending to all
  if (!form.send_to_all && (!Array.isArray(form.target_groups) || form.target_groups.length === 0)) {
    throw new Error('Please select at least one recipient group or enable Send to all.');
  }

  // Handle scheduled date/time
  let scheduledAt = null;
  if (form.send_type === 'scheduled') {
    if (!form.scheduled_date) {
      throw new Error('Scheduled date is required');
    }
    scheduledAt = formatDate(form.scheduled_date, form.scheduled_time);
  }

  // Build JSON payload per backend validation rules (expects arrays/objects)
  const payload = {
    name: form.name,
    subject: form.subject,
    from_name: form.from_name,
    from_email: form.from_email,
    reply_to: form.reply_to || form.from_email,
    content: content,
    html_content: form.html_content || undefined,
    template_id: form.template_id || undefined,
    send_type: form.send_type,
    send_to_all: !!form.send_to_all,
    target_groups: form.send_to_all ? [] : (Array.isArray(form.target_groups) ? form.target_groups : []),
    scheduled_at: scheduledAt || undefined,
  };

  if (form.send_type === 'recurring') {
      if (!form.recurring_config || typeof form.recurring_config !== 'object') {
        throw new Error('Recurring configuration is required');
      }
      payload.recurring_config = form.recurring_config;
    }

    // Submit the form
    try {
      sendStatus.value = { type: 'info', message: 'Saving campaign...' };
      
      const response = await axios.post(route('newsletter.campaigns.store'), payload, {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      });
      
      // If backend issues a redirect, axios follows it; use final URL if present
      const redirectedUrl = response?.request?.responseURL;
      if (redirectedUrl && redirectedUrl.includes('/newsletter/campaigns/')) {
        window.location.href = redirectedUrl;
        return;
      }
      // Fallback: if API returned a redirect field
      if (response.data?.redirect) {
        window.location.href = response.data.redirect;
        return;
      }
      // Final fallback: go to campaigns index
      window.location.href = route('newsletter.campaigns.index');
    } catch (error) {
      console.error('Error creating campaign:', error);
      
      if (error.response?.data?.errors) {
        // Handle Laravel validation errors
        const errors = error.response.data.errors;
        const errorMessages = [];
        
        for (const messages of Object.values(errors)) {
          errorMessages.push(...messages);
        }
        
        sendError.value = errorMessages.join('\n');
      } else {
        sendError.value = error.message || 'An error occurred while creating the campaign. Please try again.';
      }
      
      sendStatus.value = { 
        type: 'error', 
        message: 'Failed to create campaign' 
      };
    } finally {
      isSending.value = false;
    }
}

async function saveDraft() {
  isSending.value = true;
  sendStatus.value = { type: 'info', message: 'Saving draft...' };
  
  try {
    // Process content for draft
    let content = { blocks: [], settings: {} };
    if (form.content) {
      try {
        const parsedContent = typeof form.content === 'string' 
          ? safeParseJson(form.content) || {}
          : form.content;
        
        content = {
          blocks: Array.isArray(parsedContent.blocks) ? parsedContent.blocks : [],
          settings: parsedContent.settings || {}
        };
      } catch (e) {
        console.error('Error parsing content:', e);
      }
    }
    
    // Prepare form data
    form.content = content;
    form.status = 'draft';
    form.template_id = form.template_id || null;
    form.target_groups = form.send_to_all ? [] : (Array.isArray(form.target_groups) ? form.target_groups : []);
    
    await form.post(route('newsletter.campaigns.store'), {
      onSuccess: () => {
        sendStatus.value = { 
          type: 'success', 
          message: 'Draft saved successfully!'
        };
      },
      onError: (errors) => {
        console.error('Error saving draft:', errors);
        sendStatus.value = { 
          type: 'error', 
          message: Object.values(errors).join(' ')
        };
      },
      onFinish: () => {
        isSending.value = false;
      }
    });
  } catch (error) {
    console.error('Error saving draft:', error);
    sendStatus.value = { 
      type: 'error', 
      message: 'An unexpected error occurred while saving the draft.'
    };
    isSending.value = false;
  }
}

function safeParseJson(str) {
  if (!str) return null;
  try {
    return JSON.parse(str);
  } catch (e) {
    console.error('Error parsing JSON:', e);
    return null;
  }
}
</script>

<template>
  <Head title="Create Campaign" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Create New Campaign
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Status Messages -->
        <div v-if="sendStatus.message" 
             class="mb-4 p-4 rounded-md"
             :class="{
               'bg-blue-50 text-blue-800 dark:bg-blue-900/20 dark:text-blue-100': sendStatus.type === 'info',
               'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-100': sendStatus.type === 'success',
               'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-100': sendStatus.type === 'error'
             }">
          <div class="flex items-center">
            <CheckCircleIcon v-if="sendStatus.type === 'success'" class="h-5 w-5 mr-2" />
            <ExclamationTriangleIcon v-else class="h-5 w-5 mr-2" />
            <span>{{ sendStatus.message }}</span>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <!-- Campaign Form Content -->
            <div class="mb-8">
              

              
              
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 mt-8">
                <UserGroupIcon class="w-5 h-5 inline-block mr-2" />
                Recipients
              </h3>
              
              <div class="space-y-4">
                <div class="flex items-center">
                  <input
                    id="send-to-all"
                    v-model="form.send_to_all"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <label for="send-to-all" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                    Send to all active subscribers
                  </label>
                </div>
                
                <div v-if="!form.send_to_all" class="mt-4">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
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
                    Campaign will be sent to: 
                    <span v-if="isLoadingRecipients">Calculating...</span>
                    <span v-else>{{ recipientCount.toLocaleString() }} (duplicates not included)</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Send Options Section -->
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
                    
                    <label class="inline-flex items-center">
                      <input
                        v-model="form.send_type"
                        type="radio"
                        value="recurring"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                      />
                      <span class="ml-2 text-gray-700 dark:text-gray-300">Recurring</span>
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
                    <input
                      v-model="form.scheduled_at"
                      type="datetime-local"
                      :min="new Date().toISOString().slice(0, 16)"
                      class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                      :class="{ 'border-red-500': form.errors.scheduled_at }"
                    />
                    <p v-if="form.errors.scheduled_at" class="mt-1 text-sm text-red-600 dark:text-red-400">
                      {{ form.errors.scheduled_at[0] }}
                    </p>
                  </div>
                  
                  <!-- Recurring Options -->
                  <div v-if="form.send_type === 'recurring'" class="mt-4 space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Frequency <span class="text-red-500">*</span>
                      </label>
                      <select
                        v-model="form.recurring_config.frequency"
                        class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                      >
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                      </select>
                    </div>
                    
                    <div v-if="form.recurring_config.frequency === 'weekly'">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Days of Week <span class="text-red-500">*</span>
                      </label>
                      <div class="flex space-x-2">
                        <label v-for="(day, index) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" 
                               :key="index" 
                               class="inline-flex items-center">
                          <input
                            v-model="form.recurring_config.days_of_week"
                            type="checkbox"
                            :value="index"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span class="ml-2 text-gray-700 dark:text-gray-300">{{ day }}</span>
                        </label>
                      </div>
                      <p v-if="form.errors['recurring_config.days_of_week']" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors['recurring_config.days_of_week'] }}
                      </p>
                    </div>
                    
                    <div v-if="form.recurring_config.frequency === 'monthly'">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Day of Month <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model.number="form.recurring_config.day_of_month"
                        type="number"
                        min="1"
                        max="31"
                        class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                      />
                      <p v-if="form.errors['recurring_config.day_of_month']" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors['recurring_config.day_of_month'] }}
                      </p>
                    </div>
                    
                    <div class="pt-2">
                      <label class="flex items-center">
                        <input
                          v-model="form.recurring_config.has_end_date"
                          type="checkbox"
                          class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Set end date</span>
                      </label>
                      
                      <div v-if="form.recurring_config.has_end_date" class="mt-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                          End Date <span class="text-red-500">*</span>
                        </label>
                        <input
                          v-model="form.recurring_config.end_date"
                          type="date"
                          :min="new Date().toISOString().split('T')[0]"
                          class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        <p v-if="form.errors['recurring_config.end_date']" class="mt-1 text-sm text-red-600 dark:text-red-400">
                          {{ form.errors['recurring_config.end_date'] }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Email Content Section -->
              <div class="mb-8">
                <div class="space-y-4 mb-2">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Campaign Name <span class="text-red-500">*</span>
                </label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  required
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  :class="{ 'border-red-500': form.errors.name }"
                  placeholder="Enter a name for this campaign"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ form.errors.name }}
                </p>
                </div>
                <div class="space-y-4">
                  <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    EmailSubject
                  </label>
                  <input
                    id="subject"
                    v-model="form.subject"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="Email subject"
                  />
                </div>
              </div>
             
              
              
                
                
                <div>
                <div class="mt-1">
                  <EmailBuilder 
                    v-model:content="form.content"
                    v-model:html-content="form.html_content"
                    :templates="templates"
                    @update:content="updateContent"
                    @update:html-content="updateHtmlContent"
                  />
                </div>
              </div>
         
            
            <!-- Form actions -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
              <button
                type="button"
                @click="saveDraft"
                :disabled="isSending"
                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <DocumentTextIcon class="-ml-1 mr-2 h-5 w-5" />
                Save Draft
              </button>
              
              <button
                type="button"
                @click="confirmSend"
                :disabled="isSending"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <PaperAirplaneIcon class="-ml-1 mr-2 h-5 w-5" />
                {{ form.send_type === 'scheduled' ? 'Schedule Send' : 'Send Now' }}
              </button>
            </div>
          </div>
        </div>
        
        <!-- Send Confirmation Modal -->
        <TransitionRoot as="template" :show="showSendConfirmation">
          <Dialog as="div" class="relative z-10" @close="showSendConfirmation = false">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
              <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 overflow-y-auto">
              <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                  <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div class="absolute top-0 right-0 hidden pt-4 pr-4 sm:block">
                      <button type="button" class="rounded-md bg-white dark:bg-gray-800 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="showSendConfirmation = false">
                        <span class="sr-only">Close</span>
                        <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                      </button>
                    </div>
                    <div class="sm:flex sm:items-start">
                      <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                        <ExclamationTriangleIcon class="h-6 w-6 text-red-600 dark:text-red-200" aria-hidden="true" />
                      </div>
                      <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                          Confirm Send
                        </DialogTitle>
                        <div class="mt-2">
                          <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to send this campaign to {{ recipientCount }}?
                          </p>
                        </div>
                      </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                      <button type="button" class="inline-flex w-full justify-center rounded-md border border-transparent bg-red-600 dark:bg-red-500 px-4 py-2 text-base font-medium text-white hover:bg-red-700 dark:hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm" @click="submit">
                        Send
                      </button>
                      <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:mt-0 sm:w-auto sm:text-sm" @click="showSendConfirmation = false" ref="cancelButtonRef">
                        Cancel
                      </button>
                    </div>
                  </DialogPanel>
                </TransitionChild>
              </div>
            </div>
          </Dialog>
        </TransitionRoot>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
