<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import { computed, ref } from 'vue'

const props = defineProps({
  superAdmins: { type: Array, required: true },
  users: { type: Array, required: true },
})

const page = usePage()
const canManage = computed(() => !!page.props.auth?.user?.canManageSuperAdmins)

const grantForm = useForm({ user_id: '' })
const revokeForm = useForm({ user_id: '' })

const superAdminIds = computed(() => new Set(props.superAdmins.map(u => u.id)))
const grantableUsers = computed(() => props.users.filter(u => !superAdminIds.value.has(u.id)))

function grant() {
  if (!canManage.value) return
  if (!grantForm.user_id) return
  if (!confirm('Grant super admin to this user? This gives full access.')) return
  grantForm.post(route('admin.superadmin.grant'), {
    preserveScroll: true,
    onSuccess: () => grantForm.reset('user_id'),
  })
}

function revoke(id) {
  if (!canManage.value) return
  if (!confirm('Revoke super admin from this user?')) return
  revokeForm.user_id = id
  revokeForm.post(route('admin.superadmin.revoke'), {
    preserveScroll: true,
    onFinish: () => revokeForm.reset('user_id'),
  })
}
</script>

<template>
  <Head title="Super Admin Management" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Super Admin Management
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Current Super Admins</h3>
          </div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-if="superAdmins.length === 0" class="py-4 text-gray-500 dark:text-gray-400">None</div>
            <div v-for="u in superAdmins" :key="u.id" class="py-3 flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ u.name }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ u.email }}</div>
              </div>
              <DangerButton v-if="canManage" @click="revoke(u.id)">Revoke</DangerButton>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Grant Super Admin</h3>
          <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
              <select v-model="grantForm.user_id" class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <option value="" disabled>Select a user…</option>
                <option v-for="u in grantableUsers" :key="u.id" :value="u.id">{{ u.name }} — {{ u.email }}</option>
              </select>
            </div>
            <PrimaryButton :disabled="!canManage || !grantForm.user_id || grantForm.processing" @click="grant">
              Grant
            </PrimaryButton>
          </div>
          <p v-if="!canManage" class="mt-3 text-sm text-yellow-700 dark:text-yellow-400">You must be a super admin to manage super admins.</p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
