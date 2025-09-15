<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';
import TrackingToggle from '@/Components/Newsletter/TrackingToggle.vue';
import RecipientsSelector from '@/Components/Newsletter/RecipientsSelector.vue';
import SendOptions from '@/Components/Newsletter/SendOptions.vue';
import EmailContentSection from '@/Components/Newsletter/EmailContentSection.vue';
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

// Temporary upload key used before campaign ID exists
const tempKey = ref(`nl-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`);

// Reference to EmailBuilder component to access default structure
const emailBuilderRef = ref(null);

// Function to get default content - will be initialized from EmailBuilder or default template
const getDefaultContent = () => {
  // Check if there's a default template
  const defaultTemplate = props.templates?.find(t => t.is_default);
  if (defaultTemplate && defaultTemplate.content) {
    try {
      return typeof defaultTemplate.content === 'string' 
        ? defaultTemplate.content 
        : JSON.stringify(defaultTemplate.content);
    } catch (e) {
      console.warn('Failed to parse default template content:', e);
    }
  }
  
  // Fallback to EmailBuilder's default structure
  if (emailBuilderRef.value?.getDefaultEmailStructure) {
    return JSON.stringify(emailBuilderRef.value.getDefaultEmailStructure());
  }
  
  // Ultimate fallback - basic structure
  return JSON.stringify({
    blocks: [
      {
        id: 'header',
        type: 'header',
        data: {
          title: 'Newsletter Title',
          subtitle: 'Your weekly dose of updates',
          background: '#c8102e',
          textColor: '#ffffff'
        },
        editable: true,
        locked: false
      },
      {
        id: 'content',
        type: 'text',
        data: {
          content: '<p>Start writing your email content here...</p>',
          fontSize: '16px',
          color: '#666666',
          background: 'transparent',
          padding: '15px 50px'
        },
        editable: true,
        locked: false
      },
      {
        id: 'footer',
        type: 'footer',
        data: {
          content: 'Thanks for reading! Forward this to someone who might find it useful.',
          links: [
            { text: 'Unsubscribe', url: '{{unsubscribe_url}}' },
            { text: 'Update preferences', url: '{{preferences_url}}' },
            { text: 'View in browser', url: '{{browser_url}}' }
          ],
          copyright: '2025 UH Population Health. All rights reserved.',
          background: '#c8102e',
          textColor: '#ffffff'
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
  });
};

// Apply from name/email overrides from a selected template
function applyTemplateOverrides(tpl) {
  if (!tpl) return;
  if (tpl.from_name && String(tpl.from_name).trim() !== '') {
    form.from_name = tpl.from_name;
  }
  if (tpl.from_email && String(tpl.from_email).trim() !== '') {
    form.from_email = tpl.from_email;
  }
}

const form = useForm({
  name: '',
  subject: '',
  from_name: 'UHPH',
  from_email: 'noreply@uhphub.com',
  reply_to: 'noreply@uhphub.com',
  preview_text: '',
  content: '', // Will be initialized in onMounted
  html_content: '<p>Draft content</p>',
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
  
  // Initialize content with default structure
  form.content = getDefaultContent();

  // If a default template exists, apply its overrides
  const defaultTemplate = props.templates?.find(t => t.is_default);
  if (defaultTemplate) {
    applyTemplateOverrides(defaultTemplate);
  }
  
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
  // Include temp_key during creation so backend can move files
  payload.temp_key = tempKey.value;
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
            <!-- Recipients Section (shared) -->
            <RecipientsSelector
              :groups="groups"
              :send-to-all="form.send_to_all"
              :selected-group-ids="form.target_groups"
              :estimated-count="recipientCount"
              :loading-estimated="isLoadingRecipients"
              :show-estimate="true"
              @update:sendToAll="(v)=>form.send_to_all=v"
              @update:selectedGroupIds="(v)=>form.target_groups=v"
            />

            <!-- Send Options Section (shared) -->
            <SendOptions
              v-model="form.send_type"
              :scheduled-date="form.scheduled_date"
              :scheduled-time="form.scheduled_time"
              @update:scheduledDate="(v)=>form.scheduled_date=v"
              @update:scheduledTime="(v)=>form.scheduled_time=v"
            />
            
            <!-- Tracking Toggle -->
            <TrackingToggle
              v-model="form.enable_tracking"
              label="Enable URL tracking"
              help="When disabled, tracked URLs will be removed from links in the email HTML before sending."
              input-id="enable-tracking"
            />
          </div>
          

          <!-- Email Content Section - Full Width -->
          <EmailContentSection
            ref="emailBuilderRef"
            :model-value="form.content"
            :templates="templates"
            :initial-html="form.html_content"
            :campaign-id="null"
            :temp-key="tempKey"
            @update:modelValue="form.content = $event"
            @update:html-content="form.html_content = $event"
            @template-selected="handleTemplateSelection"
          />
          <div class="px-6">
            <InputError class="mt-2" :message="form.errors.content || clientErrors.content" />
            <InputError class="mt-2" :message="form.errors.html_content" />
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
