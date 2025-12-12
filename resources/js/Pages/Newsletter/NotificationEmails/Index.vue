<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
  ArrowLeftIcon,
  PlusIcon,
  TrashIcon,
  EnvelopeIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  emails: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const createForm = useForm({
  email: '',
  is_active: true,
});

function createEmail() {
  createForm.post(route('newsletter.notification-emails.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset();
      createForm.is_active = true;
    },
  });
}

function toggleActive(item) {
  router.put(
    route('newsletter.notification-emails.update', item.id),
    { is_active: !item.is_active },
    { preserveScroll: true }
  );
}

function deleteEmail(item) {
  if (confirm(`Remove notification email "${item.email}"?`)) {
    router.delete(route('newsletter.notification-emails.destroy', item.id), {
      preserveScroll: true,
    });
  }
}
</script>

<template>
  <Head title="Subscription Notification Emails" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Link
            :href="route('newsletter.subscribers.index')"
            class="inline-flex items-center p-2 text-uh-gray hover:text-uh-slate bg-uh-cream/50 hover:bg-uh-cream rounded-lg transition-colors duration-200"
          >
            <ArrowLeftIcon class="w-5 h-5" />
          </Link>
          <div>
            <h2 class="text-2xl font-bold text-uh-slate dark:text-uh-white leading-tight">
              Subscription Notification Emails
            </h2>
            <p class="mt-1 text-sm text-uh-gray dark:text-uh-cream/70">
              Manage which email addresses receive notifications when someone subscribes via the public API.
            </p>
          </div>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div v-if="flashSuccess" class="mb-4 rounded-lg border border-uh-green/20 bg-uh-green/10 px-4 py-3 text-uh-green">
          {{ flashSuccess }}
        </div>
        <div v-if="flashError" class="mb-4 rounded-lg border border-uh-red/20 bg-uh-red/10 px-4 py-3 text-uh-red">
          {{ flashError }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-white flex items-center gap-2">
                <PlusIcon class="w-5 h-5" />
                Add Email
              </h3>
            </div>
            <div class="p-6">
              <form @submit.prevent="createEmail" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-2">Email</label>
                  <input
                    v-model="createForm.email"
                    type="email"
                    required
                    class="block w-full h-11 px-4 border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-uh-slate"
                  />
                  <div v-if="createForm.errors.email" class="mt-1 text-sm text-uh-red">
                    {{ createForm.errors.email }}
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <input
                    id="is_active"
                    v-model="createForm.is_active"
                    type="checkbox"
                    class="rounded border-uh-gray/30 text-uh-red shadow-sm focus:ring-uh-red/20"
                  />
                  <label for="is_active" class="text-sm font-medium text-uh-slate dark:text-gray-300">
                    Active
                  </label>
                </div>

                <div class="pt-2">
                  <button
                    type="submit"
                    :disabled="createForm.processing"
                    class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-uh-red hover:bg-uh-brick text-white font-medium rounded-lg transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <EnvelopeIcon class="w-4 h-4 mr-2" />
                    Add
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700">
              <div class="px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-uh-slate dark:text-uh-white">
                  Emails ({{ emails.length }})
                </h3>
              </div>

              <div class="p-6">
                <div v-if="emails.length" class="space-y-3">
                  <div
                    v-for="e in emails"
                    :key="e.id"
                    class="flex items-center justify-between rounded-lg border border-uh-gray/10 dark:border-gray-600 px-4 py-3"
                  >
                    <div>
                      <div class="font-medium text-uh-slate dark:text-gray-100">{{ e.email }}</div>
                      <div class="text-xs text-uh-gray dark:text-gray-400">Added {{ new Date(e.created_at).toLocaleString() }}</div>
                    </div>

                    <div class="flex items-center gap-2">
                      <button
                        @click="toggleActive(e)"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors duration-200"
                        :class="e.is_active
                          ? 'text-uh-green bg-uh-green/10 border-uh-green/20 hover:bg-uh-green/20'
                          : 'text-uh-gray bg-uh-gray/10 border-uh-gray/20 hover:bg-uh-gray/20'"
                      >
                        {{ e.is_active ? 'Active' : 'Inactive' }}
                      </button>
                      <button
                        @click="deleteEmail(e)"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-uh-red bg-uh-red/10 hover:bg-uh-red/20 border border-uh-red/20 rounded-lg transition-colors duration-200"
                      >
                        <TrashIcon class="w-3 h-3 mr-1" />
                        Remove
                      </button>
                    </div>
                  </div>
                </div>

                <div v-else class="text-center py-12">
                  <div class="w-16 h-16 mx-auto mb-4 bg-uh-gray/10 rounded-full flex items-center justify-center">
                    <EnvelopeIcon class="w-8 h-8 text-uh-gray" />
                  </div>
                  <h3 class="text-lg font-medium text-uh-slate dark:text-uh-white mb-2">No notification emails yet</h3>
                  <p class="text-uh-gray dark:text-gray-400">Add an email address to start receiving subscription notifications.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
