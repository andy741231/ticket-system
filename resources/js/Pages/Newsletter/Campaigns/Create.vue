<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
  // Tracking options
  enable_tracking: true,
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

  // If tracking is toggled off, sanitize current html_content
  watch(() => form.enable_tracking, (enabled) => {
    if (!enabled && form.html_content) {
      form.html_content = stripTrackingFromHtml(form.html_content);
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
const clientErrors = ref({});
const isValidating = ref(false);

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
watch([() => form.send_to_all, () => form.target_groups], updateRecipientCount, { deep: true });

// Real-time validation watchers
watch(() => form.name, () => validateField('name'));
watch(() => form.subject, () => validateField('subject'));
watch(() => form.from_email, () => validateField('from_email'));
watch(() => form.reply_to, () => validateField('reply_to'));
watch(() => form.scheduled_date, () => validateField('scheduled_date'));
watch(() => form.scheduled_time, () => validateField('scheduled_time'));
watch(() => form.target_groups, () => validateField('target_groups'), { deep: true });
watch(() => form.send_to_all, () => validateField('target_groups'));

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
  // If tracking is disabled, sanitize tracking URLs before saving
  form.html_content = form.enable_tracking ? html : stripTrackingFromHtml(html);
}

function toggleHtmlView() {
  showHtmlView.value = !showHtmlView.value;
}

function confirmSend() {
  // Directly submit without confirmation
  submit();
}

// Helper: Strip newsletter tracking URLs from anchors and attempt to restore original URLs
function stripTrackingFromHtml(html) {
  if (!html) return html;

  const decodeBase64Safe = (b64) => {
    try {
      // Normalize URL-safe base64 and fix padding
      let s = (b64 || '').replace(/-/g, '+').replace(/_/g, '/');
      const pad = s.length % 4;
      if (pad) s += '='.repeat(4 - pad);
      return atob(s);
    } catch (e) {
      return null;
    }
  };

  return html.replace(/href=("|')(.*?)(\1)/gi, (match, quote, url) => {
    if (!url) return match;
    if (!/\/newsletter\/(public\/)?track-click\//i.test(url)) return match;

    try {
      const u = new URL(url, window.location.origin);
      const parts = u.pathname.split('/').filter(Boolean);
      const idx = parts.findIndex((p) => p.toLowerCase() === 'track-click');
      // Expecting: .../track-click/{campaign}/{subscriber}/{base64}[/{token}]
      const base64Part = (idx !== -1 && parts.length > idx + 3) ? parts[idx + 3] : null;
      const original = base64Part ? decodeBase64Safe(base64Part) : null;
      const href = original && /^https?:\/\//i.test(original) ? original : '#';
      return `href=${quote}${href}${quote}`;
    } catch (e) {
      // If URL constructor fails (relative or malformed), fallback to removing tracking entirely
      return `href=${quote}#${quote}`;
    }
  });
}

const validateField = (fieldName) => {
  const errors = { ...clientErrors.value };
  
  switch (fieldName) {
    case 'name':
      if (!form.name?.trim()) {
        errors.name = 'Campaign name is required';
      } else if (form.name.length < 3) {
        errors.name = 'Campaign name must be at least 3 characters';
      } else {
        delete errors.name;
      }
      break;
      
    case 'subject':
      if (!form.subject?.trim()) {
        errors.subject = 'Email subject is required';
      } else if (form.subject.length < 5) {
        errors.subject = 'Subject should be at least 5 characters for better engagement';
      } else {
        delete errors.subject;
      }
      break;
      
    case 'from_email':
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!form.from_email?.trim()) {
        errors.from_email = 'From email is required';
      } else if (!emailRegex.test(form.from_email)) {
        errors.from_email = 'Please enter a valid email address';
      } else {
        delete errors.from_email;
      }
      break;
      
    case 'reply_to':
      if (form.reply_to?.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.reply_to)) {
        errors.reply_to = 'Please enter a valid reply-to email address';
      } else {
        delete errors.reply_to;
      }
      break;
      
    case 'scheduled_date':
      if (form.send_type === 'scheduled') {
        if (!form.scheduled_date) {
          errors.scheduled_date = 'Please select a date for your scheduled campaign';
        } else {
          const selectedDate = new Date(form.scheduled_date);
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          if (selectedDate < today) {
            errors.scheduled_date = 'Scheduled date cannot be in the past';
          } else {
            delete errors.scheduled_date;
          }
        }
      } else {
        delete errors.scheduled_date;
      }
      break;
      
    case 'scheduled_time':
      if (form.send_type === 'scheduled' && form.scheduled_date) {
        if (!form.scheduled_time) {
          errors.scheduled_time = 'Please select a time for your scheduled campaign';
        } else {
          const scheduledDateTime = new Date(`${form.scheduled_date}T${form.scheduled_time}`);
          const now = new Date();
          if (scheduledDateTime <= now) {
            errors.scheduled_time = 'Scheduled time must be in the future';
          } else {
            delete errors.scheduled_time;
          }
        }
      } else {
        delete errors.scheduled_time;
      }
      break;
      
    case 'target_groups':
      if (!form.send_to_all && (!form.target_groups || form.target_groups.length === 0)) {
        errors.target_groups = 'Please select at least one group or enable "Send to all subscribers"';
      } else {
        delete errors.target_groups;
      }
      break;
  }
  
  clientErrors.value = errors;
};

const validateAllFields = () => {
  isValidating.value = true;
  
  ['name', 'subject', 'from_email', 'reply_to', 'scheduled_date', 'scheduled_time', 'target_groups'].forEach(field => {
    validateField(field);
  });
  
  // Validate content
  const errors = { ...clientErrors.value };
  if (!form.content || form.content === '{}' || form.content === '') {
    errors.content = 'Please add some content to your email';
  } else {
    try {
      const content = JSON.parse(form.content);
      if (!content.blocks || content.blocks.length === 0) {
        errors.content = 'Please add some content blocks to your email';
      } else {
        delete errors.content;
      }
    } catch (e) {
      errors.content = 'Email content format is invalid';
    }
  }
  
  clientErrors.value = errors;
  isValidating.value = false;
  
  return Object.keys(errors).length === 0;
};

const submit = (status = 'pending') => {
  // Clear any existing form errors
  form.clearErrors();
  
  // Validate all fields first
  if (!validateAllFields()) {
    // Scroll to first error
    const firstErrorField = Object.keys(clientErrors.value)[0];
    const errorElement = document.querySelector(`[name="${firstErrorField}"], #${firstErrorField}`);
    if (errorElement) {
      errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
      errorElement.focus();
    }
    return;
  }
  
  // Combine date and time for scheduled sends
  if (form.send_type === 'scheduled' && form.scheduled_date && form.scheduled_time) {
    const scheduledDateTime = new Date(`${form.scheduled_date}T${form.scheduled_time}`);
    form.scheduled_at = scheduledDateTime.toISOString();
  } else {
    form.scheduled_at = null;
  }

  // Ensure html content respects tracking setting at submit time
  if (!form.enable_tracking && form.html_content) {
    form.html_content = stripTrackingFromHtml(form.html_content);
  }

  const payload = { ...form.data(), status };
  // Ensure content is an object (backend expects array/object, not JSON string)
  if (typeof payload.content === 'string') {
    const parsed = safeParseJson(payload.content);
    if (parsed) {
      payload.content = parsed;
    }
  }
  // Ensure recurring_config is an object when recurring
  if (payload.send_type === 'recurring') {
    if (!payload.recurring_config || typeof payload.recurring_config !== 'object') {
      payload.recurring_config = {
        frequency: 'weekly',
        days_of_week: [],
        day_of_month: 1,
        has_end_date: false,
        end_date: '',
        occurrences: null,
      };
    }
  }
  // Only include recurring_config when send_type is recurring
  if (payload.send_type !== 'recurring') {
    delete payload.recurring_config;
  }
  // Only include scheduled_at when scheduled
  if (payload.send_type !== 'scheduled') {
    delete payload.scheduled_at;
  }
  
  // Use transform to ensure JSON payload shape is sent
  form.transform(() => payload).post(route('newsletter.campaigns.store'), {
    onSuccess: () => {
      // Clear client errors on success
      clientErrors.value = {};
    },
    onError: (errors) => {
      console.error('Error creating campaign:', errors);
      // Scroll to first server error
      setTimeout(() => {
        const firstErrorField = Object.keys(errors)[0];
        const errorElement = document.querySelector(`[name="${firstErrorField}"], #${firstErrorField}`);
        if (errorElement) {
          errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 100);
    },
    onFinish: () => {
      // reset transform for other requests
      form.transform((data) => data);
    }
  });
};

const saveDraft = () => {
  submit('draft');
};

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

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <form @submit.prevent="submit('pending')">
          <!-- Campaign Details Section -->
          <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Campaign Details</h3>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <InputLabel for="name" value="Campaign Name" />
                  <TextInput 
                    id="name" 
                    v-model="form.name" 
                    type="text" 
                    name="name"
                    class="mt-1 block w-full" 
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.name || clientErrors.name }"
                    required 
                    placeholder="Enter campaign name"
                  />
                  <InputError class="mt-2" :message="form.errors.name || clientErrors.name" />
                </div>
                <div>
                  <InputLabel for="subject" value="Email Subject" />
                  <TextInput 
                    id="subject" 
                    v-model="form.subject" 
                    type="text" 
                    name="subject"
                    class="mt-1 block w-full" 
                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.subject || clientErrors.subject }"
                    required 
                    placeholder="Enter email subject"
                  />
                  <InputError class="mt-2" :message="form.errors.subject || clientErrors.subject" />
                </div>
              </div>
            </div>
          </div>

          <!-- Settings Row -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Recipients Section -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                  <UserGroupIcon class="w-5 h-5 mr-2 text-uh-red" />
                  Recipients
                </h3>
              </div>
              <div class="p-6 space-y-4">
                <div class="flex items-center">
                  <input 
                    id="send-to-all" 
                    v-model="form.send_to_all" 
                    type="checkbox" 
                    class="h-4 w-4 rounded border-gray-300 text-uh-red focus:ring-uh-red focus:ring-offset-0" 
                  />
                  <label for="send-to-all" class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Send to all active subscribers
                  </label>
                </div>
                
                <div v-if="!form.send_to_all">
                  <InputLabel value="Select Groups" class="mb-3" />
                  <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                    <label 
                      v-for="group in groups" 
                      :key="group.id" 
                      class="flex items-center p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors"
                    >
                      <input 
                        v-model="form.target_groups" 
                        :value="group.id" 
                        type="checkbox" 
                        class="rounded border-gray-300 text-uh-red shadow-sm focus:ring-uh-red focus:ring-offset-0" 
                      />
                      <div class="ml-3 flex-1">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ group.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ group.active_subscriber_count }} subscribers</div>
                      </div>
                    </label>
                  </div>
                  <InputError class="mt-2" :message="form.errors.target_groups || clientErrors.target_groups" />
                </div>

                <div class="bg-uh-cream dark:bg-uh-forest/20 border border-uh-gold/30 rounded-lg p-4">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <UserGroupIcon class="h-5 w-5 text-uh-gold" />
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-uh-chocolate dark:text-uh-cream">
                        Estimated recipients: 
                        <span v-if="isLoadingRecipients" class="animate-pulse">Calculating...</span>
                        <span v-else class="font-bold">{{ recipientCount.toLocaleString() }}</span>
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Send Options Section -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                  <ClockIcon class="w-5 h-5 mr-2 text-uh-red" />
                  Send Options
                </h3>
              </div>
              <div class="p-6 space-y-6">
                <div class="space-y-3">
                  <label class="flex items-center p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                    <input 
                      v-model="form.send_type" 
                      type="radio" 
                      value="immediate" 
                      name="send_type" 
                      class="h-4 w-4 text-uh-red focus:ring-uh-red border-gray-300" 
                    />
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Send Immediately</div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">Campaign will be sent right away</div>
                    </div>
                  </label>
                  
                  <label class="flex items-center p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                    <input 
                      v-model="form.send_type" 
                      type="radio" 
                      value="scheduled" 
                      name="send_type" 
                      class="h-4 w-4 text-uh-red focus:ring-uh-red border-gray-300" 
                    />
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Schedule for Later</div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">Choose a specific date and time</div>
                    </div>
                  </label>
                </div>

                <div v-if="form.send_type === 'scheduled'" class="space-y-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <InputLabel for="scheduled_date" value="Date" class="text-sm font-medium" />
                      <div class="mt-1 relative">
                        <TextInput 
                          id="scheduled_date" 
                          v-model="form.scheduled_date" 
                          type="date" 
                          name="scheduled_date"
                          :min="new Date().toISOString().split('T')[0]" 
                          class="block w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-uh-red focus:border-uh-red" 
                          :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.scheduled_date || clientErrors.scheduled_date, 'border-gray-300 dark:border-gray-600': !(form.errors.scheduled_date || clientErrors.scheduled_date) }"
                        />
                        <CalendarIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                      </div>
                      <InputError class="mt-1" :message="form.errors.scheduled_date || clientErrors.scheduled_date" />
                    </div>
                    
                    <div>
                      <InputLabel for="scheduled_time" value="Time" class="text-sm font-medium" />
                      <div class="mt-1 relative">
                        <TextInput 
                          id="scheduled_time" 
                          v-model="form.scheduled_time" 
                          type="time" 
                          name="scheduled_time"
                          class="block w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-uh-red focus:border-uh-red" 
                          :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.scheduled_time || clientErrors.scheduled_time, 'border-gray-300 dark:border-gray-600': !(form.errors.scheduled_time || clientErrors.scheduled_time) }"
                        />
                        <ClockIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                      </div>
                      <InputError class="mt-1" :message="form.errors.scheduled_time || clientErrors.scheduled_time" />
                    </div>
                  </div>
                  
                  <div v-if="form.scheduled_date && form.scheduled_time" class="text-sm text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-800 p-3 rounded border">
                    <strong>Scheduled for:</strong> 
                    {{ new Date(`${form.scheduled_date}T${form.scheduled_time}`).toLocaleString() }}
                  </div>
                  
                  <InputError class="mt-2" :message="form.errors.scheduled_at" />
                </div>

                <!-- Tracking Toggle -->
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                  <label class="flex items-start p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                    <input
                      id="enable-tracking"
                      v-model="form.enable_tracking"
                      type="checkbox"
                      class="mt-1 h-4 w-4 text-uh-red focus:ring-uh-red border-gray-300 rounded"
                    />
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable open and click tracking</div>
                      <p class="text-xs text-gray-500 dark:text-gray-400">When disabled, tracked URLs like <code>/newsletter/public/track-click/...</code> will be removed from links in the email HTML before sending.</p>
                    </div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Email Content Section - Full Width -->
          <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Email Content</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Design your email using the visual editor below</p>
            </div>
            <div class="p-6">
              <EmailBuilder 
                v-model:content="form.content"
                v-model:html-content="form.html_content"
                :templates="templates"
                @update:content="updateContent"
                @update:html-content="updateHtmlContent"
              />
              <InputError class="mt-2" :message="form.errors.content || clientErrors.content" />
              <InputError class="mt-2" :message="form.errors.html_content" />
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3">
              <SecondaryButton 
                @click="saveDraft" 
                :class="{ 'opacity-25': form.processing }" 
                :disabled="form.processing" 
                type="button"
                class="inline-flex items-center justify-center"
              >
                <DocumentTextIcon class="-ml-1 mr-2 h-5 w-5" />
                Save Draft
              </SecondaryButton>
              
              <PrimaryButton 
              type="submit"
                :class="{ 'opacity-25': form.processing || isValidating }" 
                :disabled="form.processing || isValidating"
                class="inline-flex items-center justify-center bg-uh-red hover:bg-uh-brick focus:ring-uh-red"
              >
                <PaperAirplaneIcon class="-ml-1 mr-2 h-5 w-5" />
                <span v-if="form.processing">Sending...</span>
                <span v-else-if="isValidating">Validating...</span>
                <span v-else>{{ form.send_type === 'immediate' ? 'Send Campaign' : 'Schedule Campaign' }}</span>
              </PrimaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
