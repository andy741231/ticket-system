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
import { Head, useForm, Link, router } from '@inertiajs/vue3';
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
  DocumentTextIcon,
  EyeIcon
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
  defaultFromName: String,
  defaultFromEmail: String,
});

const showPreview = ref(false);

// Deliveries (scheduled sends) state
const deliveries = ref({
  data: [],
  meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
  counts: {},
});
const deliveryFilters = ref({ status: '', search: '', page: 1, per_page: 15 });
const isLoadingDeliveries = ref(false);

async function fetchDeliveries() {
  if (!props.campaign?.id) return;
  try {
    isLoadingDeliveries.value = true;
    const params = {
      status: deliveryFilters.value.status || undefined,
      search: deliveryFilters.value.search || undefined,
      page: deliveryFilters.value.page,
      per_page: deliveryFilters.value.per_page,
    };
    const { data } = await axios.get(route('newsletter.campaigns.scheduled-sends', props.campaign.id), { params });
    deliveries.value = data;
  } catch (e) {
    console.error('Error fetching deliveries:', e);
  } finally {
    isLoadingDeliveries.value = false;
  }
}

watch(deliveryFilters, () => {
  deliveryFilters.value.page = deliveryFilters.value.page || 1;
  fetchDeliveries();
}, { deep: true });

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
  from_name: props.campaign?.from_name || props.defaultFromName || 'UHPH News',
  from_email: props.campaign?.from_email || props.defaultFromEmail || 'noreply@central.uh.edu',
  reply_to: props.campaign?.reply_to || props.defaultFromEmail || 'noreply@central.uh.edu',
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

  // Apply template overrides for from_name and from_email, or fall back to env defaults
  const trimmedFromName = typeof template.from_name === 'string' ? template.from_name.trim() : '';
  if (trimmedFromName) {
    form.from_name = template.from_name;
    form.clearErrors('from_name');
    delete clientErrors.value.from_name;
  } else {
    // Fall back to env defaults if template has no override
    form.from_name = props.defaultFromName || 'UHPH News';
  }

  const trimmedFromEmail = typeof template.from_email === 'string' ? template.from_email.trim() : '';
  if (trimmedFromEmail) {
    form.from_email = template.from_email;
    form.clearErrors('from_email');
    delete clientErrors.value.from_email;
  } else {
    // Fall back to env defaults if template has no override
    form.from_email = props.defaultFromEmail || 'noreply@central.uh.edu';
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
    
    // Convert all group IDs to strings to match backend validation
    const stringGroupIds = groupIds.map(id => String(id));
    
    const response = await axios.post(url, {
      group_ids: stringGroupIds
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
  
  // Fetch deliveries if campaign exists and is scheduled
  if (props.campaign?.id && props.campaign.status === 'scheduled') {
    fetchDeliveries();
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

const submit = (status = null) => {
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

  // Determine the correct status based on send_type if not explicitly provided
  if (!status) {
    if (form.send_type === 'scheduled') {
      status = 'scheduled';
    } else if (form.send_type === 'recurring') {
      status = 'scheduled';
    } else {
      status = 'draft';
    }
  }

  const payload = { ...form.data(), status };
  
  // If sending immediately (and not just saving draft), set send_now flag
  if (form.send_type === 'immediate' && status !== 'draft') {
    payload.send_now = true;
  }

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

const saveDraft = () => {
  // Save draft with minimal validation - just ensure we have basic required fields
  form.clearErrors();
  
  // Combine date and time for scheduled sends
  if (form.send_type === 'scheduled' && form.scheduled_date && form.scheduled_time) {
    const [y, m, d] = String(form.scheduled_date).split('-').map(Number);
    const [hh, mm] = String(form.scheduled_time).split(':').map(Number);
    const scheduledDateTime = new Date(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, 0, 0);
    
    const pad2 = (n) => String(n).padStart(2, '0');
    form.scheduled_at = `${y}-${pad2(m)}-${pad2(d)} ${pad2(hh)}:${pad2(mm)}:00`;
  } else {
    form.scheduled_at = null;
  }

  // Ensure html content respects tracking setting
  if (!form.enable_tracking && form.html_content) {
    form.html_content = stripTrackingFromHtml(form.html_content);
  }

  const payload = { ...form.data(), status: 'draft' };
  
  // Ensure content is an object
  if (typeof payload.content === 'string') {
    const parsed = safeParseJson(payload.content);
    if (parsed) {
      payload.content = parsed;
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
  
  isDraftSaving.value = true;
  
  form.transform(() => payload).put(route('newsletter.campaigns.update', { campaign: props.campaign.id }), {
    preserveState: true, // Keep state when saving draft
    preserveScroll: true, // Keep scroll position
    only: [], // Don't reload any props
    onSuccess: () => {
      clientErrors.value = {};
      isDraftSaving.value = false;
    },
    onError: (errors) => {
      console.error('Error saving draft:', errors);
      isDraftSaving.value = false;
    },
    onFinish: () => {
      form.transform((data) => data);
      isDraftSaving.value = false;
    }
  });
};

const sendDraftCampaign = () => {
  if (confirm(`Send this draft campaign to ${recipientCount.value} recipients for testing? The campaign will remain as a draft.`)) {
    router.post(route('newsletter.campaigns.send-draft', props.campaign.id), {}, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        // Success handled by flash message
      },
      onError: (errors) => {
        console.error('Error sending draft:', errors);
      }
    });
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
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Edit Campaign: {{ form.name }}
        </h2>
        <button
          @click="showPreview = !showPreview"
          type="button"
          class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
        >
          <EyeIcon class="w-4 h-4 mr-2" />
          {{ showPreview ? 'Hide Preview' : 'Preview' }}
        </button>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Preview Section -->
        <div v-if="showPreview" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
              Email Preview
            </h3>
            <button @click="showPreview = false" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="p-6">
            <div class="max-w-2xl mx-auto border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
              <div class="bg-gray-50 dark:bg-gray-900 p-4 border-b border-gray-200 dark:border-gray-600">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  Subject: {{ form.subject }}
                </div>
                <div v-if="form.preview_text" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  {{ form.preview_text }}
                </div>
              </div>
              <div class="bg-white dark:bg-gray-800 p-6">
                <div v-html="form.html_content" class="prose dark:prose-invert max-w-none"></div>
              </div>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit()">
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
          <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 shadow-lg sm:rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-5">
              <!-- Action Buttons Container -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                <!-- Left side: Send Draft (if available) -->
                <div>
                  <!-- Send Draft button - only visible for draft campaigns -->
                  <button
                    v-if="campaign.status === 'draft'"
                    @click.prevent="sendDraftCampaign"
                    :disabled="form.processing"
                    type="button"
                    class="group relative inline-flex items-center justify-center px-6 py-2.5 rounded-lg font-medium text-sm
                           bg-gradient-to-r from-uh-mustard to-uh-gold
                           text-white dark:text-gray-900
                           border-2 border-uh-ocher dark:border-uh-gold
                           hover:from-uh-ocher hover:to-uh-mustard
                           hover:shadow-lg hover:shadow-uh-gold/30
                           active:scale-95
                           disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:active:scale-100
                           transition-all duration-200 ease-in-out
                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-gold dark:focus:ring-uh-mustard"
                  >
                    <PaperAirplaneIcon 
                      class="h-5 w-5 mr-2 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" 
                    />
                    <span class="font-semibold">Send Draft</span>
                    <span class="absolute inset-0 rounded-lg bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity duration-200"></span>
                  </button>
                </div>

                <!-- Right side: Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                  <!-- Save Draft -->
                  <button
                    @click.prevent="saveDraft"
                    :disabled="isDraftSaving"
                    type="button"
                    class="group relative inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-sm
                           bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200
                           border-2 border-gray-300 dark:border-gray-600
                           hover:border-gray-400 dark:hover:border-gray-500
                           hover:shadow-md active:scale-95
                           disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:active:scale-100
                           transition-all duration-200 ease-in-out
                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 dark:focus:ring-gray-500"
                  >
                    <DocumentTextIcon 
                      :class="[
                        'h-5 w-5 mr-2 transition-transform duration-200',
                        isDraftSaving ? 'animate-pulse' : 'group-hover:scale-110'
                      ]" 
                    />
                    <span class="font-semibold">
                      {{ isDraftSaving ? 'Saving...' : 'Save Draft' }}
                    </span>
                  </button>
                  
                  <!-- Send Campaign button -->
                  <button
                    type="submit"
                    :disabled="form.processing || isValidating"
                    class="group relative inline-flex items-center justify-center px-6 py-2.5 rounded-lg font-medium text-sm
                           bg-gradient-to-r from-uh-red to-uh-brick
                           text-white
                           border-2 border-uh-chocolate dark:border-uh-red
                           hover:from-uh-brick hover:to-uh-chocolate
                           hover:shadow-lg hover:shadow-uh-red/30
                           active:scale-95
                           disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:active:scale-100
                           transition-all duration-200 ease-in-out
                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-red dark:focus:ring-uh-brick"
                  >
                    <PaperAirplaneIcon 
                      :class="[
                        'h-5 w-5 mr-2 transition-transform duration-200',
                        form.processing || isValidating ? 'animate-pulse' : 'group-hover:translate-x-0.5 group-hover:-translate-y-0.5'
                      ]" 
                    />
                    <span class="font-semibold">
                      <span v-if="form.processing">Sending...</span>
                      <span v-else-if="isValidating">Validating...</span>
                      <span v-else>Send Campaign</span>
                    </span>
                    <span class="absolute inset-0 rounded-lg bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-200"></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>

        <!-- Deliveries (Queue) Section -->
        <div v-if="campaign.id && (campaign.status === 'scheduled' || deliveries.data.length > 0)" class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Scheduled Deliveries</h3>
            <div class="flex items-center gap-2">
               <!-- Controls could go here -->
            </div>
          </div>
          <div class="p-6 space-y-4">
            <div class="flex flex-col md:flex-row gap-3 md:items-end">
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 dark:text-gray-300">Status</label>
                <select v-model="deliveryFilters.status" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md">
                  <option value="">All</option>
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="sent">Sent</option>
                  <option value="failed">Failed</option>
                </select>
              </div>
              <div class="flex-1">
                <input v-model="deliveryFilters.search" type="text" placeholder="Search email..." class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md" />
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 dark:text-gray-300">Per page</label>
                <select v-model.number="deliveryFilters.per_page" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md">
                  <option :value="10">10</option>
                  <option :value="15">15</option>
                  <option :value="25">25</option>
                </select>
              </div>
            </div>

            <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Scheduled</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-if="isLoadingDeliveries">
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">Loading...</td>
                  </tr>
                  <tr v-else-if="deliveries.data.length === 0">
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No scheduled deliveries found</td>
                  </tr>
                  <tr v-for="row in deliveries.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100">{{ row.subscriber?.email || '-' }}</td>
                    <td class="px-6 py-3 text-sm">
                      <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                        :class="{
                          'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': row.status==='pending',
                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': row.status==='processing',
                          'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': row.status==='sent',
                          'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': row.status==='failed',
                        }"
                      >{{ row.status }}</span>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-500">{{ row.scheduled_at ? new Date(row.scheduled_at).toLocaleString() : '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
              <div>
                Page {{ deliveries.meta.current_page }} of {{ deliveries.meta.last_page }}
              </div>
              <div class="flex gap-2">
                <button
                  class="px-3 py-1 border rounded disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="deliveries.meta.current_page <= 1"
                  @click="deliveryFilters.page = deliveries.meta.current_page - 1"
                >Prev</button>
                <button
                  class="px-3 py-1 border rounded disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="deliveries.meta.current_page >= deliveries.meta.last_page"
                  @click="deliveryFilters.page = deliveries.meta.current_page + 1"
                >Next</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
