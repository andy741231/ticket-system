<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'w-full flex items-center px-4 py-3 text-sm font-medium text-white bg-gray-900/20 rounded-md transition duration-150 ease-in-out'
        : 'w-full flex items-center px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white rounded-md transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
