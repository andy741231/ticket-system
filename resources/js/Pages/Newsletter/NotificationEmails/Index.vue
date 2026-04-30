<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
  ArrowLeftIcon,
  PlusIcon,
  TrashIcon,
  EnvelopeIcon,
  TagIcon,
  XMarkIcon,
  BellSlashIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  emails: Array,
  groups: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError   = computed(() => page.props.flash?.error);

const showAddModal      = ref(false);
const expandedGroupsFor = ref(null);

const createForm = useForm({
  email:     '',
  is_active: true,
  groups:    [],
});

function createEmail() {
  createForm.post(route('newsletter.notification-emails.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset();
      createForm.is_active = true;
      createForm.groups    = [];
      showAddModal.value   = false;
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

function groupIdsFor(item) {
  return (item.groups || []).map((g) => g.id);
}

function isGroupSelected(item, groupId) {
  return groupIdsFor(item).includes(groupId);
}

function updateEmailGroups(item, groupId, checked) {
  const groupIds = new Set(groupIdsFor(item));
  if (checked) {
    groupIds.add(groupId);
  } else {
    groupIds.delete(groupId);
  }
  if (!groupIds.size) {
    alert('Please keep at least one group assigned.');
    return;
  }
  router.put(
    route('newsletter.notification-emails.update', item.id),
    { groups: Array.from(groupIds) },
    { preserveScroll: true }
  );
}

function toggleGroupEditor(id) {
  expandedGroupsFor.value = expandedGroupsFor.value === id ? null : id;
}

function deleteEmail(item) {
  if (confirm(`Remove "${item.email}" from notification recipients?`)) {
    router.delete(route('newsletter.notification-emails.destroy', item.id), {
      preserveScroll: true,
    });
  }
}

const activeCount = computed(() => props.emails.filter((e) => e.is_active).length);
</script>

<template>
  <Head title="Subscription Notification Emails" />
  <AuthenticatedLayout>

    <!-- ── Header ─────────────────────────────────────────────────── -->
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
              Notification Emails
            </h2>
            <p class="mt-1 text-sm text-uh-gray dark:text-uh-cream/70">
              Recipients that receive an alert whenever a new subscriber joins one of their assigned groups.
            </p>
          </div>
        </div>
        <button
          @click="showAddModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 bg-uh-red hover:bg-uh-brick text-white text-sm font-medium rounded-lg transition-colors duration-200 shadow-sm"
        >
          <PlusIcon class="w-4 h-4" />
          Add Email
        </button>
      </div>
    </template>

    <!-- ── Body ───────────────────────────────────────────────────── -->
    <div class="py-8">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        <!-- Flash messages -->
        <div
          v-if="flashSuccess"
          class="flex items-center gap-3 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400"
        >
          <CheckCircleIcon class="w-5 h-5 flex-shrink-0" />
          {{ flashSuccess }}
        </div>
        <div
          v-if="flashError"
          class="flex items-center gap-3 rounded-lg border border-uh-red/20 bg-uh-red/5 dark:bg-uh-red/10 px-4 py-3 text-sm text-uh-red"
        >
          <ExclamationCircleIcon class="w-5 h-5 flex-shrink-0" />
          {{ flashError }}
        </div>

        <!-- Stat strip -->
        <div class="flex items-center gap-5 text-sm text-uh-gray dark:text-gray-400">
          <div>
            <span class="font-semibold text-uh-slate dark:text-uh-white">{{ emails.length }}</span>
            {{ emails.length === 1 ? 'recipient' : 'recipients' }}
          </div>
          <div class="w-px h-4 bg-uh-gray/20 dark:bg-gray-600" />
          <div>
            <span class="font-semibold text-uh-slate dark:text-uh-white">{{ activeCount }}</span>
            active
          </div>
          <div class="w-px h-4 bg-uh-gray/20 dark:bg-gray-600" />
          <div>
            <span class="font-semibold text-uh-slate dark:text-uh-white">{{ groups.length }}</span>
            {{ groups.length === 1 ? 'group' : 'groups' }} available
          </div>
        </div>

        <!-- ── Table card ──────────────────────────────────────────── -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-uh-gray/10 dark:border-gray-700 overflow-hidden">

          <!-- Column headers (only when rows exist) -->
          <div
            v-if="emails.length"
            class="hidden sm:grid grid-cols-12 gap-4 px-5 py-2.5 bg-uh-cream/40 dark:bg-gray-700/40 border-b border-uh-gray/10 dark:border-gray-700 text-xs font-semibold uppercase tracking-wide text-uh-gray dark:text-gray-400"
          >
            <div class="col-span-5">Recipient</div>
            <div class="col-span-5">Groups</div>
            <div class="col-span-2 text-right">Active</div>
          </div>

          <!-- Rows -->
          <template v-if="emails.length">
            <div
              v-for="e in emails"
              :key="e.id"
              class="border-b border-uh-gray/10 dark:border-gray-700 last:border-0"
            >
              <!-- Main row -->
              <div class="grid grid-cols-12 gap-4 px-5 py-4 items-center hover:bg-uh-cream/20 dark:hover:bg-gray-700/20 transition-colors duration-150">

                <!-- Email + date -->
                <div class="col-span-12 sm:col-span-5 min-w-0">
                  <div class="flex items-center gap-2">
                    <span
                      class="w-2 h-2 rounded-full flex-shrink-0"
                      :class="e.is_active ? 'bg-uh-green' : 'bg-uh-gray/30 dark:bg-gray-500'"
                    />
                    <span class="font-medium text-uh-slate dark:text-gray-100 text-sm truncate" :title="e.email">
                      {{ e.email }}
                    </span>
                  </div>
                  <p class="mt-0.5 ml-4 text-xs text-uh-gray dark:text-gray-500">
                    Added {{ new Date(e.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) }}
                  </p>
                </div>

                <!-- Groups pills + edit trigger -->
                <div class="col-span-10 sm:col-span-5">
                  <div class="flex flex-wrap gap-1 mb-1.5">
                    <span
                      v-for="g in e.groups"
                      :key="g.id"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-red/10 text-uh-red dark:bg-uh-red/20"
                    >
                      {{ g.name }}
                    </span>
                    <span
                      v-if="!e.groups || !e.groups.length"
                      class="text-xs text-uh-gray dark:text-gray-500 italic"
                    >
                      No groups assigned
                    </span>
                  </div>
                  <button
                    @click="toggleGroupEditor(e.id)"
                    class="inline-flex items-center gap-1 text-xs text-uh-gray hover:text-uh-slate dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-150"
                  >
                    <TagIcon class="w-3.5 h-3.5" />
                    Edit groups
                    <ChevronUpIcon   v-if="expandedGroupsFor === e.id" class="w-3 h-3" />
                    <ChevronDownIcon v-else class="w-3 h-3" />
                  </button>
                </div>

                <!-- Toggle + delete -->
                <div class="col-span-2 flex items-center justify-end gap-3">
                  <button
                    @click="toggleActive(e)"
                    :title="e.is_active ? 'Deactivate' : 'Activate'"
                    class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-uh-red/30"
                    :class="e.is_active ? 'bg-uh-green' : 'bg-uh-gray/30 dark:bg-gray-600'"
                  >
                    <span
                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                      :class="e.is_active ? 'translate-x-4' : 'translate-x-0'"
                    />
                  </button>
                  <button
                    @click="deleteEmail(e)"
                    title="Remove"
                    class="p-1.5 text-uh-gray hover:text-uh-red dark:text-gray-400 dark:hover:text-uh-red rounded-md hover:bg-uh-red/10 transition-colors duration-150"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <!-- Inline group editor -->
              <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
              >
                <div
                  v-if="expandedGroupsFor === e.id"
                  class="px-5 pt-3 pb-4 bg-uh-cream/20 dark:bg-gray-700/20 border-t border-uh-gray/10 dark:border-gray-700"
                >
                  <p class="text-xs font-semibold text-uh-gray dark:text-gray-400 uppercase tracking-wide mb-2">
                    Assign to Groups
                  </p>
                  <div v-if="groups.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <label
                      v-for="g in groups"
                      :key="g.id"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer select-none transition-colors duration-150"
                      :class="isGroupSelected(e, g.id)
                        ? 'border-uh-red/30 bg-uh-red/5 dark:border-uh-red/40 dark:bg-uh-red/10'
                        : 'border-uh-gray/20 dark:border-gray-600 hover:bg-uh-cream/60 dark:hover:bg-gray-700'"
                    >
                      <input
                        :checked="isGroupSelected(e, g.id)"
                        @change="updateEmailGroups(e, g.id, $event.target.checked)"
                        type="checkbox"
                        class="rounded border-uh-gray/30 text-uh-red shadow-sm focus:ring-uh-red/20"
                      />
                      <span
                        class="text-sm font-medium"
                        :class="isGroupSelected(e, g.id) ? 'text-uh-red' : 'text-uh-slate dark:text-gray-300'"
                      >
                        {{ g.name }}
                      </span>
                    </label>
                  </div>
                  <p v-else class="text-sm text-uh-gray dark:text-gray-400 italic">
                    No active groups available.
                  </p>
                </div>
              </transition>
            </div>
          </template>

          <!-- Empty state -->
          <div v-else class="flex flex-col items-center justify-center py-16 text-center">
            <div class="w-14 h-14 mb-4 bg-uh-gray/10 dark:bg-gray-700 rounded-full flex items-center justify-center">
              <BellSlashIcon class="w-7 h-7 text-uh-gray dark:text-gray-400" />
            </div>
            <h3 class="text-base font-semibold text-uh-slate dark:text-uh-white mb-1">No notification emails yet</h3>
            <p class="text-sm text-uh-gray dark:text-gray-400 max-w-xs mb-5">
              Add recipient addresses to receive alerts whenever someone subscribes to a group.
            </p>
            <button
              @click="showAddModal = true"
              class="inline-flex items-center gap-2 px-4 py-2 bg-uh-red hover:bg-uh-brick text-white text-sm font-medium rounded-lg transition-colors duration-200 shadow-sm"
            >
              <PlusIcon class="w-4 h-4" />
              Add Email
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Add Email Modal ────────────────────────────────────────── -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
      <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <!-- Backdrop -->
        <div
          class="fixed inset-0 bg-gray-900/50 dark:bg-black/60 backdrop-blur-sm transition-opacity"
          @click="showAddModal = false"
        />
        <!-- Panel -->
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md ring-1 ring-uh-gray/10 dark:ring-gray-700">

          <!-- Modal header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-uh-gray/10 dark:border-gray-700">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-uh-red/10 flex items-center justify-center">
                <EnvelopeIcon class="w-4 h-4 text-uh-red" />
              </div>
              <h3 class="text-base font-semibold text-uh-slate dark:text-uh-white">Add Notification Email</h3>
            </div>
            <button
              @click="showAddModal = false"
              class="p-1.5 text-uh-gray hover:text-uh-slate dark:text-gray-400 dark:hover:text-gray-200 rounded-md hover:bg-uh-gray/10 transition-colors"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Modal form -->
          <form @submit.prevent="createEmail" class="px-6 py-5 space-y-5">

            <div>
              <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-1.5">
                Email Address <span class="text-uh-red">*</span>
              </label>
              <input
                v-model="createForm.email"
                type="email"
                required
                placeholder="notify@example.com"
                class="block w-full h-10 px-3.5 border border-uh-gray/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-uh-red focus:ring-uh-red/20 rounded-lg shadow-sm text-sm text-uh-slate"
              />
              <p v-if="createForm.errors.email" class="mt-1 text-xs text-uh-red">
                {{ createForm.errors.email }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-uh-slate dark:text-gray-300 mb-1.5 flex items-center gap-1.5">
                <TagIcon class="w-4 h-4" />
                Notify for Groups <span class="text-uh-red">*</span>
              </label>
              <div class="border border-uh-gray/20 dark:border-gray-600 rounded-lg overflow-hidden">
                <div v-if="groups.length" class="max-h-48 overflow-y-auto divide-y divide-uh-gray/10 dark:divide-gray-700">
                  <label
                    v-for="g in groups"
                    :key="g.id"
                    class="flex items-center gap-3 px-4 py-2.5 cursor-pointer select-none transition-colors duration-150"
                    :class="createForm.groups.includes(g.id)
                      ? 'bg-uh-red/5 dark:bg-uh-red/10'
                      : 'hover:bg-uh-cream/40 dark:hover:bg-gray-700'"
                  >
                    <input
                      v-model="createForm.groups"
                      :value="g.id"
                      type="checkbox"
                      class="rounded border-uh-gray/30 text-uh-red shadow-sm focus:ring-uh-red/20"
                    />
                    <span
                      class="text-sm font-medium"
                      :class="createForm.groups.includes(g.id) ? 'text-uh-red' : 'text-uh-slate dark:text-gray-300'"
                    >
                      {{ g.name }}
                    </span>
                  </label>
                </div>
                <div v-else class="px-4 py-3 text-sm text-uh-gray dark:text-gray-400 italic">
                  No active groups available.
                </div>
              </div>
              <p v-if="createForm.errors.groups" class="mt-1 text-xs text-uh-red">
                {{ createForm.errors.groups }}
              </p>
            </div>

            <!-- Active toggle -->
            <div class="flex items-center gap-3">
              <button
                type="button"
                role="switch"
                :aria-checked="createForm.is_active"
                @click="createForm.is_active = !createForm.is_active"
                class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-uh-red/30"
                :class="createForm.is_active ? 'bg-uh-green' : 'bg-uh-gray/30 dark:bg-gray-600'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                  :class="createForm.is_active ? 'translate-x-4' : 'translate-x-0'"
                />
              </button>
              <span class="text-sm font-medium text-uh-slate dark:text-gray-300">Active immediately</span>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 pt-1 border-t border-uh-gray/10 dark:border-gray-700">
              <button
                type="button"
                @click="showAddModal = false"
                class="px-4 py-2 text-sm font-medium text-uh-slate dark:text-gray-300 bg-uh-cream/60 dark:bg-gray-700 hover:bg-uh-cream dark:hover:bg-gray-600 rounded-lg transition-colors duration-150"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="createForm.processing"
                class="inline-flex items-center gap-2 px-4 py-2 bg-uh-red hover:bg-uh-brick text-white text-sm font-medium rounded-lg transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <EnvelopeIcon class="w-4 h-4" />
                {{ createForm.processing ? 'Adding…' : 'Add Email' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>
