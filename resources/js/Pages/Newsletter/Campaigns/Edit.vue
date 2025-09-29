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
  campaign: Object,
  templates: Array,
  groups: Array,
});

// Initialize form with campaign data - ensure content is properly formatted
const initializeContent = () => {
  let content = props.campaign?.content;
  
  // If content is a string, try to parse it
  if (typeof content === 'string') {
    try {
      content = JSON.parse(content);
    } catch (e) {
      console.warn('Failed to parse campaign content:', e);
      content = null;
    }
  }
  
  // If no valid content, return default structure
  if (!content || !content.blocks) {
    // Return empty string to let EmailBuilder apply its internal defaults
    // (header + text + footer). This avoids duplicating JSON here.
    return '';
  }
  
  // Return existing content as JSON string
  return JSON.stringify(content);
};

// Initialize form with campaign data
const form = useForm({
  name: props.campaign?.name || '',
  subject: props.campaign?.subject || '',
  from_name: props.campaign?.from_name || 'UHPH',
  from_email: props.campaign?.from_email || 'noreply@uhphub.com',
  reply_to: props.campaign?.reply_to || 'noreply@uhphub.com',
  preview_text: props.campaign?.preview_text || '',
  content: initializeContent(),
  html_content: props.campaign?.html_content || '',
  template_id: props.campaign?.template_id || '',
  send_type: props.campaign?.send_type || 'immediate',
  // Normalize scheduled_at for consistent local display in edit form
  ...(function() {
    const out = { scheduled_date: '', scheduled_time: '12:00', scheduled_at: null };
    const sa = props.campaign?.scheduled_at;
    if (!sa) return out;
    let input = String(sa);
    const noTzRegex = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/;
    const hasTzRegex = /([Zz]|[+-]\d{2}:?\d{2})$/;
    if (noTzRegex.test(input) && !hasTzRegex.test(input)) {
      input = input.replace(' ', 'T') + 'Z';
    }
    const d = new Date(input);
    if (!isNaN(d.getTime())) {
      out.scheduled_date = new Date(d).toISOString().split('T')[0];
      const hh = String(d.getHours()).padStart(2, '0');
      const mm = String(d.getMinutes()).padStart(2, '0');
      out.scheduled_time = `${hh}:${mm}`;
      out.scheduled_at = sa; // keep original value; form submit will recompute ISO
    }
    return out;
  })(),
  recurring_config: props.campaign?.recurring_config || null,
  // target_groups is stored as an array of group IDs in the DB
  target_groups: Array.isArray(props.campaign?.target_groups) ? props.campaign.target_groups : [],
  send_to_all: props.campaign?.send_to_all || false,
  enable_tracking: props.campaign?.enable_tracking ?? true,
});

const isSending = ref(false);
const sendError = ref(null);
const recipientCount = ref(0);
const isLoadingRecipients = ref(false);
const clientErrors = ref({});
const isValidating = ref(false);

function handleTemplateSelection(template) {
  if (!template) return;

  if (Object.prototype.hasOwnProperty.call(template, 'id')) {
    form.template_id = template.id ?? '';
  }

  if (template.content) {
    try {
      const parsed = typeof template.content === 'string'
        ? JSON.parse(template.content)
        : template.content;
      if (parsed && parsed.blocks) {
        form.content = JSON.stringify(parsed);
        form.clearErrors('content');
        delete clientErrors.value.content;
      }
    } catch (e) {
      console.warn('Failed to apply template content in edit view:', e);
    }
  }

  if (template.html_content) {
    form.html_content = template.html_content;
    form.clearErrors('html_content');
  }

  const trimmedSubject = typeof template.subject === 'string' ? template.subject.trim() : '';
  if (trimmedSubject) {
    form.subject = template.subject;
    form.clearErrors('subject');
    delete clientErrors.value.subject;
  }

  const trimmedPreview = typeof template.preview_text === 'string' ? template.preview_text.trim() : '';
  if (trimmedPreview) {
    form.preview_text = template.preview_text;
    form.clearErrors('preview_text');
    delete clientErrors.value.preview_text;
  }

  const trimmedFromName = typeof template.from_name === 'string' ? template.from_name.trim() : '';
  if (trimmedFromName) {
    form.from_name = template.from_name;
    form.clearErrors('from_name');
    delete clientErrors.value.from_name;
  }

  const trimmedFromEmail = typeof template.from_email === 'string' ? template.from_email.trim() : '';
  if (trimmedFromEmail) {
    form.from_email = template.from_email;
    form.clearErrors('from_email');
    delete clientErrors.value.from_email;
  }
}

const isDraftSaving = ref(false);

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

// Get unique subscribers across all selected groups
const getUniqueSubscribersCount = async (groupIds) => {
  try {
    if (!groupIds || groupIds.length === 0) {
      return 0;
    }
    
    const url = route('newsletter.groups.unique-subscribers');
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
    
    if (response.data && typeof response.data.unique_subscribers_count !== 'undefined') {
      return response.data.unique_subscribers_count;
    }
    
    if (typeof response.data === 'number') {
      return response.data;
    }
    
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
    const token = document.head.querySelector('meta[name="csrf-token"]')?.content;

    const response = await axios.get(url, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token
      },
      withCredentials: true
    });

    if (response.data && typeof response.data.total_active_subscribers !== 'undefined') {
      return response.data.total_active_subscribers;
    }

    if (typeof response.data === 'number') {
      return response.data;
    }

    return 0;
  } catch (error) {
    console.error('Error fetching total active subscribers count:', error);
    return 0;
  }
};

// Update recipient count based on current form state
const updateRecipientCount = async () => {
  try {
    if (!Array.isArray(props.groups)) {
      recipientCount.value = 0;
      return;
    }

    isLoadingRecipients.value = true;

    if (form.send_to_all) {
      const count = await getTotalActiveSubscribers();
      recipientCount.value = count;
    } else {
      const selected = Array.isArray(form.target_groups) ? form.target_groups : [];
      
      if (selected.length > 0) {
        const count = await getUniqueSubscribersCount(selected);
        recipientCount.value = count;
      } else {
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
watch(() => form.from_name, () => validateField('from_name'));
watch(() => form.preview_text, () => validateField('preview_text'));

function updateContent(content) {
  try {
    // Ensure content is a string (JSON)
    const contentStr = typeof content === 'string' ? content : JSON.stringify(content);
    form.content = contentStr;
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
      
    // Validate scheduled date allowing same-day, using local string comparison
    case 'scheduled_date':
      if (form.send_type === 'scheduled') {
        if (!form.scheduled_date) {
          errors.scheduled_date = 'Please select a date for your scheduled campaign';
        } else {
          const pad2 = (n) => String(n).padStart(2, '0');
          const today = new Date();
          const todayLocalStr = `${today.getFullYear()}-${pad2(today.getMonth() + 1)}-${pad2(today.getDate())}`;
          const selectedStr = String(form.scheduled_date);

          if (selectedStr < todayLocalStr) {
            errors.scheduled_date = 'Scheduled date cannot be in the past';
          } else {
            delete errors.scheduled_date;
            // If scheduling for today, proactively require a valid time in the future
            if (selectedStr === todayLocalStr) {
              const bufferMinutes = 2;
              const nowWithBuffer = new Date(Date.now() + bufferMinutes * 60000);
              if (!form.scheduled_time) {
                errors.scheduled_time = 'Please select a time for today';
              } else {
                const [y, m, d] = selectedStr.split('-').map(Number);
                const [hh, mm] = String(form.scheduled_time).split(':').map(Number);
                const scheduledDateTime = new Date(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, 0, 0);
                if (scheduledDateTime <= nowWithBuffer) {
                  errors.scheduled_time = 'Scheduled time must be at least 2 minutes in the future';
                } else {
                  delete errors.scheduled_time;
                }
              }
            }
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
          // Construct datetime using local components to avoid timezone pitfalls
          const [y, m, d] = String(form.scheduled_date).split('-').map(Number);
          const [hh, mm] = String(form.scheduled_time).split(':').map(Number);
          const scheduledDateTime = new Date(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, 0, 0);
          const now = new Date();
          
          // Add a 2-minute buffer to account for any timezone or processing delays
          const bufferMinutes = 2;
          const nowWithBuffer = new Date(now.getTime() + bufferMinutes * 60000);
          
          if (scheduledDateTime <= nowWithBuffer) {
            errors.scheduled_time = 'Scheduled time must be at least 2 minutes in the future';
          } else {
            delete errors.scheduled_time;
          }
        }
      } else {
        delete errors.scheduled_time;
      }
      break;
      
    case 'from_name':
      if (!form.from_name?.trim()) {
        errors.from_name = 'From name is required';
      } else if (form.from_name.length < 2) {
        errors.from_name = 'From name must be at least 2 characters';
      } else {
        delete errors.from_name;
      }
      break;
      
    case 'preview_text':
      if (form.preview_text && form.preview_text.length > 150) {
        errors.preview_text = 'Preview text should be 150 characters or less';
      } else {
        delete errors.preview_text;
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
  
  ['name', 'subject', 'from_name', 'from_email', 'reply_to', 'preview_text', 'scheduled_date', 'scheduled_time', 'target_groups'].forEach(field => {
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

onMounted(() => {
  initRecurringConfig();
  updateRecipientCount();
  
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
    const [y, m, d] = String(form.scheduled_date).split('-').map(Number);
    const [hh, mm] = String(form.scheduled_time).split(':').map(Number);
    const scheduledDateTime = new Date(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, 0, 0);
    
    // Send the local datetime in a format that the backend can interpret correctly
    // Format: YYYY-MM-DD HH:MM:SS (without timezone info, so backend treats it as local)
    const pad2 = (n) => String(n).padStart(2, '0');
    form.scheduled_at = `${y}-${pad2(m)}-${pad2(d)} ${pad2(hh)}:${pad2(mm)}:00`;
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
  form.transform(() => payload).put(route('newsletter.campaigns.update', { campaign: props.campaign.id }), {
    onSuccess: () => {
      // Clear client errors on success
      clientErrors.value = {};
    },
    onError: (errors) => {
      console.error('Error updating campaign:', errors);
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

const saveDraft = async () => {
  // Allow saving draft without full validation to avoid blocking users
  // But we still sanitize minimal fields and scheduling
  try {
    isDraftSaving.value = true;
    sendError.value = null;
    sendStatus.value = { type: 'info', message: 'Saving draft...' };

    // Combine date and time for scheduled sends
    let scheduled_at = null;
    if (form.send_type === 'scheduled' && form.scheduled_date && form.scheduled_time) {
      const [y, m, d] = String(form.scheduled_date).split('-').map(Number);
      const [hh, mm] = String(form.scheduled_time).split(':').map(Number);
      const scheduledDateTime = new Date(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, 0, 0);
      
      // Send the local datetime in a format that the backend can interpret correctly
      const pad2 = (n) => String(n).padStart(2, '0');
      scheduled_at = `${y}-${pad2(m)}-${pad2(d)} ${pad2(hh)}:${pad2(mm)}:00`;
    }

    // Prepare content object
    let contentObj = form.content;
    if (typeof contentObj === 'string') {
      const parsed = safeParseJson(contentObj);
      contentObj = parsed || {};
    }

    // Respect tracking setting for html_content
    let htmlContent = form.html_content ?? '';
    if (!form.enable_tracking && htmlContent) {
      htmlContent = stripTrackingFromHtml(htmlContent);
    }

    // Build payload for draft status
    const payload = {
      name: form.name,
      subject: form.subject,
      from_name: form.from_name,
      from_email: form.from_email,
      reply_to: form.reply_to || form.from_email,
      content: contentObj,
      html_content: typeof htmlContent === 'string' ? htmlContent : '',
      template_id: form.template_id || undefined,
      send_type: form.send_type,
      send_to_all: !!form.send_to_all,
      target_groups: form.send_to_all ? [] : (Array.isArray(form.target_groups) ? form.target_groups : []),
      scheduled_at: scheduled_at || undefined,
      enable_tracking: !!form.enable_tracking,
      status: 'draft',
    };

    const isUpdate = !!(props?.campaign && props.campaign.id);
    const url = isUpdate
      ? route('newsletter.campaigns.update', { campaign: props.campaign.id })
      : route('newsletter.campaigns.store');
    const method = isUpdate ? 'put' : 'post';
    console.log('[saveDraft] method:', method, 'url:', url);

    if (isUpdate) {
      await axios.put(
        url,
        payload,
        { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
      );
    } else {
      await axios.post(
        url,
        payload,
        { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
      );
    }

    sendStatus.value = { type: 'success', message: 'Draft saved successfully.' };
  } catch (e) {
    const errors = e?.response?.data?.errors || {};
    const errorMessages = [];
    Object.entries(errors).forEach(([key, value]) => {
      errorMessages.push(Array.isArray(value) ? value[0] : String(value));
    });
    sendError.value = errorMessages.length ? errorMessages.join(' ') : (e?.response?.data?.message || 'Failed to save draft.');
  } finally {
    isDraftSaving.value = false;
  }
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
  <Head :title="`Edit Campaign: ${form.name}`" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Edit Campaign: {{ form.name }}
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
              :error="form.errors.target_groups || clientErrors.target_groups"
              @update:sendToAll="(v)=>form.send_to_all=v"
              @update:selectedGroupIds="(v)=>form.target_groups=v"
            />

            <!-- Send Options Section (shared) -->
            <SendOptions
              v-model="form.send_type"
              :scheduled-date="form.scheduled_date"
              :scheduled-time="form.scheduled_time"
              :errors="{
                scheduled_date: form.errors.scheduled_date || clientErrors.scheduled_date,
                scheduled_time: form.errors.scheduled_time || clientErrors.scheduled_time
              }"
              @update:scheduledDate="(v)=>form.scheduled_date=v"
              @update:scheduledTime="(v)=>form.scheduled_time=v"
            />
          </div>

          <!-- Email Content Section - Full Width -->
          <EmailContentSection
            :model-value="form.content"
            :templates="templates"
            :initial-html="form.html_content"
            :campaign-id="campaign?.id"
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
                @click.prevent="saveDraft" 
                :class="{ 'opacity-25': isDraftSaving }" 
                :disabled="isDraftSaving" 
                type="button"
                class="inline-flex items-center justify-center"
              >
                <DocumentTextIcon class="-ml-1 mr-2 h-5 w-5" />
                <span v-if="isDraftSaving">Saving...</span>
                <span v-else>Save Draft</span>
              </SecondaryButton>
              
              <PrimaryButton 
              type="submit"
                :class="{ 'opacity-25': form.processing || isValidating }" 
                :disabled="form.processing || isValidating"
                class="inline-flex items-center justify-center bg-uh-red hover:bg-uh-brick focus:ring-uh-red"
              >
                <PaperAirplaneIcon class="-ml-1 mr-2 h-5 w-5" />
                <span v-if="form.processing">Updating...</span>
                <span v-else-if="isValidating">Validating...</span>
                <span v-else>Update Campaign</span>
              </PrimaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
