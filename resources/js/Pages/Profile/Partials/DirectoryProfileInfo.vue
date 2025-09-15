<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  team: {
    type: Object,
    default: null,
  },
});
</script>

<template>
  <section v-if="team">
    <header>
      <h2 class="dark:text-uh-cream text-lg font-medium text-gray-900">
        Profile Information
      </h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-uh-cream">
        This is your Directory profile. Use the button below to edit it.
      </p>
    </header>

    <div class="mt-6 flex items-center gap-6">
      <div>
        <img
          v-if="team.img"
          :src="team.img"
          alt="Profile image"
          class="w-24 h-24 rounded-full object-cover border border-gray-200 dark:border-gray-600"
        />
        <div v-else class="w-24 h-24 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500">
          <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
        </div>
      </div>
      <div class="flex-1">
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Keep your public directory profile up to date so teammates can find you easily.
        </p>
      </div>
      <div>
        <Link :href="route('directory.edit', team.id)" as="button">
          <PrimaryButton>
            Edit Directory Profile
          </PrimaryButton>
        </Link>
      </div>
    </div>
  </section>
</template>
