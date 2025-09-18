<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  user: {
    type: Object,
    required: false,
    default: () => ({})
  },
  size: {
    type: String,
    default: 'md', // xs, sm, md, lg, xl
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  showLink: {
    type: Boolean,
    default: true
  }
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const teams = computed(() => page.props.teams || []);

// Size classes mapping
const sizeClasses = {
  xs: 'h-6 w-6 text-xs',
  sm: 'h-8 w-8 text-sm',
  md: 'h-10 w-10 text-sm',
  lg: 'h-16 w-16 text-lg',
  xl: 'h-24 w-24 text-2xl'
};

// Helper to normalize emails for case-insensitive comparisons
const normalizeEmail = (email) => (email ?? '').toString().trim().toLowerCase();

// Find matching team by email
const matchingTeam = computed(() => {
  if (!teams.value?.length || !props.user?.email) return null;
  const userEmail = normalizeEmail(props.user.email);
  return teams.value.find(team => normalizeEmail(team.email) === userEmail) || null;
});

// Determine avatar image or initials
const avatarImage = computed(() => {
  // Prefer direct user avatar or img if available, then fall back to matching team image
  return props.user?.avatar || props.user?.img || matchingTeam.value?.img || null;
});

const initials = computed(() => {
  if (!props.user?.name) return 'U';
  const names = props.user.name.split(' ');
  if (names.length >= 2) {
    return (names[0][0] + names[names.length - 1][0]).toUpperCase();
  }
  return names[0][0].toUpperCase();
});

// Determine link destination
const linkDestination = computed(() => {
  if (!props.showLink) return null;
  
  // If current user and email matches a team, link to directory edit
  if (normalizeEmail(currentUser.value?.email) === normalizeEmail(props.user?.email) && matchingTeam.value) {
    return { type: 'route', name: 'directory.edit', params: matchingTeam.value.id };
  }
  
  // If not current user but email matches a team, link to directory show
  if (matchingTeam.value) {
    return { type: 'route', name: 'directory.show', params: matchingTeam.value.id };
  }
  
  // If user doesn't have a directory, link to mailto
  if (props.user?.email) {
    return { type: 'mailto', email: props.user.email };
  }
  
  return null;
});

const isCurrentUser = computed(() => {
  return normalizeEmail(currentUser.value?.email) === normalizeEmail(props.user?.email);
});
</script>

<template>
  <div class="flex-shrink-0">
    <!-- With Link -->
    <Link 
      v-if="linkDestination?.type === 'route'" 
      :href="route(linkDestination.name, linkDestination.params)"
      class="block"
    >
      <div 
        :class="[
          sizeClasses[size],
          'rounded-full flex items-center justify-center font-semibold cursor-pointer hover:opacity-80 transition-opacity',
          avatarImage ? '' : 'bg-gradient-to-br from-blue-500 to-purple-600 text-white'
        ]"
      >
        <img 
          v-if="avatarImage" 
          :src="avatarImage" 
          :alt="user?.name || 'User'"
          :class="[sizeClasses[size], 'rounded-full object-cover']"
        />
        <span v-else>{{ initials }}</span>
      </div>
    </Link>

    <!-- With Mailto -->
    <a 
      v-else-if="linkDestination?.type === 'mailto'" 
      :href="`mailto:${linkDestination.email}`"
      class="block"
    >
      <div 
        :class="[
          sizeClasses[size],
          'rounded-full flex items-center justify-center font-semibold cursor-pointer hover:opacity-80 transition-opacity',
          avatarImage ? '' : 'bg-gradient-to-br from-blue-500 to-purple-600 text-white'
        ]"
      >
        <img 
          v-if="avatarImage" 
          :src="avatarImage" 
          :alt="user?.name || 'User'"
          :class="[sizeClasses[size], 'rounded-full object-cover']"
        />
        <span v-else>{{ initials }}</span>
      </div>
    </a>

    <!-- Without Link -->
    <div 
      v-else
      :class="[
        sizeClasses[size],
        'rounded-full flex items-center justify-center font-semibold',
        avatarImage ? '' : 'bg-gradient-to-br from-blue-500 to-purple-600 text-white'
      ]"
    >
      <img 
        v-if="avatarImage" 
        :src="avatarImage" 
        :alt="user?.name || 'User'"
        :class="[sizeClasses[size], 'rounded-full object-cover']"
      />
      <span v-else>{{ initials }}</span>
    </div>
  </div>
</template>
