<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

const form = useForm({
  to: '',
  subject: 'Test Email from The Hub',
  message: `Hello,

This is a test email from The Hub.

Time: ${new Date().toLocaleString()}

Thanks.`,
});

const sending = ref(false);
const page = usePage();
const diagnostics = computed(() => page.props.flash?.info || '');

const submit = () => {
  sending.value = true;
  form.post(route('tools.email-test.send'), {
    preserveScroll: true,
    onFinish: () => {
      sending.value = false;
    },
  });
};
</script>

<template>
  <Head title="Email Test" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
        Email Test
      </h2>
    </template>

    <div class="max-w-3xl mx-auto">
      <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Send a test email using current SMTP settings. SSL verification is disabled and self-signed certificates are allowed per configuration.
        </p>

        <!-- Diagnostics Panel (server-provided via flash.info) -->
        <div v-if="diagnostics" class="rounded-md border border-blue-200 bg-blue-50 dark:bg-gray-900 dark:border-gray-700 p-4">
          <div class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-2">Diagnostics</div>
          <pre class="text-xs whitespace-pre-wrap text-blue-900 dark:text-gray-200">{{ diagnostics }}</pre>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label for="to" class="block text-sm font-medium text-gray-700 dark:text-gray-200">To</label>
            <input
              id="to"
              type="email"
              v-model="form.to"
              required
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-uh-red focus:ring-uh-red"
              placeholder="recipient@example.com"
            />
            <div v-if="form.errors.to" class="text-sm text-red-600 mt-1">{{ form.errors.to }}</div>
          </div>

          <div>
            <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Subject</label>
            <input
              id="subject"
              type="text"
              v-model="form.subject"
              required
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-uh-red focus:ring-uh-red"
            />
            <div v-if="form.errors.subject" class="text-sm text-red-600 mt-1">{{ form.errors.subject }}</div>
          </div>

          <div>
            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Message</label>
            <textarea
              id="message"
              rows="8"
              v-model="form.message"
              required
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-uh-red focus:ring-uh-red"
            />
            <div v-if="form.errors.message" class="text-sm text-red-600 mt-1">{{ form.errors.message }}</div>
          </div>

          <div class="flex items-center gap-3">
            <button
              type="submit"
              :disabled="sending || form.processing"
              class="inline-flex items-center px-4 py-2 bg-uh-red text-white border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-uh-red/90 focus:outline-none focus:ring-2 focus:ring-uh-red disabled:opacity-25 transition"
            >
              <svg v-if="sending || form.processing" class="-ml-1 mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              Send Test Email
            </button>

            <a :href="route('dashboard')" class="text-sm text-gray-600 dark:text-gray-300 hover:underline">Cancel</a>
          </div>
        </form>
      </div>

      <div class="mt-6 text-xs text-gray-500 dark:text-gray-400">
        <p><strong>Note:</strong> Configure SMTP in your .env:</p>
        <pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-900 rounded text-[11px] overflow-x-auto">
MAIL_MAILER=smtp
MAIL_HOST=post-office.uh.edu
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@uh.edu
MAIL_FROM_NAME="The Hub"
        </pre>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
