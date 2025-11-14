<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useHasAny } from '@/Extensions/useAuthz';
import { computed } from 'vue';

const props = defineProps({
  team: Object,
});

const page = usePage();

// Load affiliate programs if they exist
const affiliatePrograms = props.team.affiliate_programs || [];

// Permission: directory managers OR users viewing their own profile can edit
const canManageDirectory = useHasAny(['directory.profile.manage']);
const isOwnProfile = computed(() => {
  const user = page.props.auth?.user;
  return user && user.email === props.team?.email;
});
const canEditProfile = computed(() => canManageDirectory.value || isOwnProfile.value);
</script>

<template>
  <Head :title="team.name" />

  <AuthenticatedLayout>
    <template #header>
    </template>

    <div class="">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-4">
          <div>
          </div>
          <Link v-if="canEditProfile" :href="route('directory.edit', team.id)" as="button">
              <PrimaryButton>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 mr-1">
                                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l9.06-9.06.92.92L5.92 19.58zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                                    </svg>
                Edit 
              </PrimaryButton>
          </Link>
       </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1 flex justify-center">
                            <img v-if="team.img" :src="team.img" :alt="team.name" class="rounded-full h-48 w-48 object-cover">
                            <svg v-else class="w-48 h-48 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="md:col-span-2">
                            <h3 class="text-2xl font-bold">{{ team.name }}</h3>
                            <p class="text-lg text-uh-teal dark:text-uh-teal-light">{{ team.title }}</p>
                            <p v-if="team.degree" class="text-md text-gray-500 dark:text-gray-400">{{ team.degree }}</p>
                            <div class="flex justify-start space-x-4 mt-2">
                      <a :href="`mailto:${team.email}`" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                      </a>
                      <a :href="`https://teams.microsoft.com/l/chat/0/0?users=${team.email}`" target="_blank" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                      </a>
                      <a :href="`https://teams.microsoft.com/l/call/0/0?users=${team.email}`" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                      </a>
                    </div>
                            <div v-if="team.bio" class="mt-8">
                                <h4 class="text-lg font-semibold">Biography</h4>
                                <div v-html="team.bio" class="mt-2 space-y-4 leading-relaxed"></div>
                            </div>
                            
                            <!-- Affiliate Programs Section -->
                            <!-- <div v-if="affiliatePrograms.length > 0" class="mt-8">
                                <h4 class="text-lg font-semibold">Affiliate Programs</h4>
                                <div class="mt-4 space-y-4">
                                    <div v-for="(program, index) in affiliatePrograms" :key="index" class="border rounded-lg p-4 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100 mb-2">{{ program.title }}</h5>
                                        <p class="text-gray-700 dark:text-gray-300">{{ program.program }}</p>
                                    </div>
                                </div>
                            </div> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </AuthenticatedLayout>
</template>
