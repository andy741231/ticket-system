<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  email: { type: String, required: true },
  token: { type: String, required: true },
  prefill: { type: Object, default: () => ({}) },
})

const form = useForm({
  name: props.prefill?.name || '',
  username: props.prefill?.username || '',
  password: '',
  password_confirmation: '',
})

const submitting = computed(() => form.processing)

function submit() {
  form.post(route('invites.process', props.token))
}
</script>

<template>
  <Head title="Accept Invitation" />
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
          Accept Invitation
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-300">
          Invited for <span class="font-medium">{{ email }}</span>
        </p>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="submit">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="name" class="sr-only">Full name</label>
            <input id="name" v-model="form.name" name="name" type="text" required
                   class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 dark:text-white dark:bg-gray-800 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                   placeholder="Full name" />
          </div>
          <div>
            <label for="username" class="sr-only">Username</label>
            <input id="username" v-model="form.username" name="username" type="text"
                   class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 dark:text-white dark:bg-gray-800 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                   placeholder="Username (optional)" />
          </div>
          <div>
            <label for="password" class="sr-only">Password</label>
            <input id="password" v-model="form.password" name="password" type="password" required
                   class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 dark:text-white dark:bg-gray-800 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                   placeholder="Password" />
          </div>
          <div>
            <label for="password_confirmation" class="sr-only">Confirm Password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" name="password_confirmation" type="password" required
                   class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 dark:text-white dark:bg-gray-800 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                   placeholder="Confirm Password" />
          </div>
        </div>

        <div v-if="form.hasErrors" class="text-red-600 text-sm">
          <ul class="list-disc pl-5">
            <li v-for="(v, k) in form.errors" :key="k">{{ v }}</li>
          </ul>
        </div>

        <div>
          <button type="submit"
                  :disabled="submitting"
                  class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <span v-if="submitting">Submitting...</span>
            <span v-else>Create account and continue</span>
          </button>
        </div>
      </form>

      <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-300">
        Already have an account?
        <Link :href="route('login')" class="font-medium text-indigo-600 hover:text-indigo-500">Sign in</Link>
      </p>
    </div>
  </div>
</template>
