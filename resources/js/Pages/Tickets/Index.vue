<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MultiSelectCheckbox from '@/Components/MultiSelectCheckbox.vue';
import { ref, computed, watch, onBeforeUnmount, onMounted, defineComponent } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useHasAny } from '@/Extensions/useAuthz';
import { format, formatDistanceToNow } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { library } from '@fortawesome/fontawesome-svg-core';
import { 
  faPlus, 
  faSearch, 
  faThLarge, 
  faBars, 
  faFileAlt, 
  faUser, 
  faClock, 
  faSpinner,
  faInbox, 
  faThumbsUp, 
  faThumbsDown, 
  faCheckCircle,
  faTimesCircle
} from '@fortawesome/free-solid-svg-icons';

// Add icons to the library
library.add(
  faPlus, 
  faSearch, 
  faThLarge, 
  faBars, 
  faFileAlt, 
  faUser, 
  faClock, 
  faSpinner,
  faInbox, 
  faThumbsUp, 
  faThumbsDown, 
  faCheckCircle,
  faTimesCircle
);

// Component props
const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            status: '',
            search: '',
            assignee: '',
            date_from: '',
            date_to: '',
            sort_field: 'created_at',
            sort_direction: 'desc',
            scope: null,
        }),
    },
    users: {
        type: Array,
        default: () => [],
    },
    allTags: {
        type: Array,
        default: () => [],
    },
});

const statuses = ['Received', 'Rejected', 'Completed'];
const statusOptions = statuses.map(s => ({ id: s, name: s }));
const selectedStatuses = ref(props.filters.status ? props.filters.status.split(',') : []);

// Tag / label filters
const tagOptions = computed(() => (props.allTags || []).map(name => ({ id: name, name })));
const selectedTags = ref(props.filters.tags ? props.filters.tags.split(',') : []);

// Select all statuses
const selectAllStatuses = () => {
    selectedStatuses.value = [...statuses];
};

// Clear all selected statuses
const clearAllStatuses = () => {
    selectedStatuses.value = [];
};

const viewMode = ref('card'); // 'card' or 'list'
const sortField = ref('created_at');
const sortDirection = ref('desc');
// Ownership scope: 'assigned' | 'submitted' | null (no scope for managers)
const ownershipScope = ref((props.filters && ['assigned','submitted'].includes(props.filters.scope)) ? props.filters.scope : null);

const page = usePage();
const authUser = computed(() => page.props.auth.user);
const canManageTickets = useHasAny(['tickets.ticket.manage']);
const canUpdateTickets = useHasAny(['tickets.ticket.update', 'tickets.ticket.manage']);

// Local storage keys (scoped per user)
const getViewModeKey = () => `tickets.index.viewMode.${authUser.value?.id ?? 'guest'}`;
const getStatusesKey = () => `tickets.index.statuses.${authUser.value?.id ?? 'guest'}`;
const getTagsKey = () => `tickets.index.tags.${authUser.value?.id ?? 'guest'}`;
const getScopeKey = () => `tickets.index.scope.${authUser.value?.id ?? 'guest'}`;

// Initialize user preferences from localStorage
onMounted(() => {
    try {
        const savedView = localStorage.getItem(getViewModeKey());
        if (savedView === 'card' || savedView === 'list') {
            viewMode.value = savedView;
        }
    } catch (e) { /* noop */ }

    try {
        const savedStatuses = localStorage.getItem(getStatusesKey());
        if (savedStatuses) {
            const parsed = JSON.parse(savedStatuses);
            if (Array.isArray(parsed)) {
                // Only keep valid statuses
                selectedStatuses.value = parsed.filter(s => statuses.includes(s));
            }
        }
    } catch (e) { /* noop */ }

    try {
        const savedTags = localStorage.getItem(getTagsKey());
        if (savedTags) {
            const parsed = JSON.parse(savedTags);
            if (Array.isArray(parsed)) {
                const valid = parsed.filter(t => (props.allTags || []).includes(t));
                selectedTags.value = valid;
            }
        }
    } catch (e) { /* noop */ }

    // Initialize ownership scope from localStorage, then fall back to server-provided filters
    try {
        const savedScope = localStorage.getItem(getScopeKey());
        if (savedScope === 'assigned' || savedScope === 'submitted') {
            ownershipScope.value = savedScope;
        } else if (props.filters && typeof props.filters.scope === 'string' && ['assigned','submitted'].includes(props.filters.scope)) {
            ownershipScope.value = props.filters.scope;
        }
    } catch (e) { /* noop */ }
});

// Persist view mode changes
watch(viewMode, (val) => {
    try { localStorage.setItem(getViewModeKey(), val); } catch (e) { /* noop */ }
});

// Persist ownership scope changes (only when valid)
watch(ownershipScope, (val) => {
    if (val === 'assigned' || val === 'submitted') {
        try { localStorage.setItem(getScopeKey(), val); } catch (e) { /* noop */ }
    } else {
        // Clear saved preference when unsetting scope
        try { localStorage.removeItem(getScopeKey()); } catch (e) { /* noop */ }
    }
});

// Sort tickets based on current sort field and direction
const sortedTickets = computed(() => {
    if (!props.tickets.data) return [];
    
    return [...props.tickets.data].sort((a, b) => {
        let valueA = a[sortField.value];
        let valueB = b[sortField.value];
        
        // Handle date fields
        if (sortField.value === 'created_at') {
            valueA = new Date(valueA);
            valueB = new Date(valueB);
        }
        
        // Handle string comparison
        if (typeof valueA === 'string') valueA = valueA.toLowerCase();
        if (typeof valueB === 'string') valueB = valueB.toLowerCase();
        
        // Compare values
        if (valueA < valueB) return sortDirection.value === 'asc' ? -1 : 1;
        if (valueA > valueB) return sortDirection.value === 'asc' ? 1 : -1;
        return 0;
    });
});

// Handle column header click for sorting
const sortBy = (field) => {
    if (sortField.value === field) {
        // Toggle direction if same field
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        // New field, default to ascending
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    performSearch();
};

// Get sort indicator for a column
const getSortIndicator = (field) => {
    if (sortField.value !== field) return '';
    return sortDirection.value === 'asc' ? '↑' : '↓';
};

// Check if user can edit a ticket
const canEdit = (ticket) => {
    if (!authUser.value) return false;
    
    // Users with update/manage permission can edit any ticket
    if (canUpdateTickets.value) return true;
    
    // Users can only edit their own tickets that are not resolved/closed
    const isOwner = ticket.user_id === authUser.value.id;
    const isEditable = !['Resolved', 'Closed', 'Rejected'].includes(ticket.status);
    
    return isOwner && isEditable;
};

const statusBadgeClasses = {
    'Received': { 
        class: 'bg-uh-teal/20 dark:text-uh-cream',
        icon: 'inbox',
    },
    'Approved': { 
        class: 'bg-uh-green/20 dark:text-uh-cream',
        icon: 'thumbs-up',
    },
    'Rejected': { 
        class: 'bg-uh-red/20 dark:text-uh-cream',
        icon: 'thumbs-down',
    },
    'Completed': { 
        class: 'bg-uh-teal/20 dark:text-uh-cream',
        icon: 'check-circle',
    },
};

    
// Visible columns based on role
const visibleColumns = ref({
    id: true,
    title: true,
    status: true,
    assignee: false, // Will be set by the watcher
    created_at: true,
    updated_at: false,
    actions: true,
});

// Watch for ticket management permission changes and update visibleColumns
watch(canManageTickets, (newValue) => {
    visibleColumns.value.assignee = newValue;
}, { immediate: true });

// Debounce search to avoid too many requests
let searchTimeout = null;
const search = ref(props.filters.search || '');

// Clear timeout on component unmount
onBeforeUnmount(() => {
    clearTimeout(searchTimeout);
});

// Function to perform the search
const performSearch = () => {
    const params = {
        search: search.value,
        status: selectedStatuses.value.join(','),
        tags: selectedTags.value.join(','),
        assignee: props.filters.assignee,
        date_from: props.filters.date_from,
        date_to: props.filters.date_to,
        sort_field: sortField.value,
        sort_direction: sortDirection.value,
    };
    if (ownershipScope.value === 'assigned' || ownershipScope.value === 'submitted') {
        params.scope = ownershipScope.value;
    }

    // Check if filters effectively changed to decide whether to reset page
    const currentUrlParams = new URLSearchParams(window.location.search);
    let filtersChanged = false;
    const keysToCheck = ['search', 'status', 'tags', 'assignee', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'scope'];

    for (const key of keysToCheck) {
        const rawVal = params[key];
        // Normalize to string; treat null/undefined as empty string
        const newVal = (rawVal === null || rawVal === undefined) ? '' : String(rawVal);
        const currentVal = currentUrlParams.get(key) || '';
        
        if (newVal !== currentVal) {
            // Handle default sort values which might be implicit in URL
            if (key === 'sort_field' && currentVal === '' && newVal === 'created_at') continue;
            if (key === 'sort_direction' && currentVal === '' && newVal === 'desc') continue;
            
            filtersChanged = true;
            break;
        }
    }

    // If filters haven't changed, preserve the current page
    if (!filtersChanged && currentUrlParams.has('page')) {
        params.page = currentUrlParams.get('page');
    }

    // Fetch filtered/sorted results from the server
    router.get(route('tickets.index'), params, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
        only: ['tickets', 'filters'],
    });
}

// Watch for search input changes with debounce
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 500);
});

watch(selectedStatuses, (val) => {
    try { localStorage.setItem(getStatusesKey(), JSON.stringify(val)); } catch (e) { /* noop */ }
    performSearch();
}, { deep: true });

watch(selectedTags, (val) => {
    try { localStorage.setItem(getTagsKey(), JSON.stringify(val)); } catch (e) { /* noop */ }
    performSearch();
}, { deep: true });

// Trigger search on ownership scope change
watch(ownershipScope, () => {
    performSearch();
});



// Format date with date-fns
const formatDateTime = (dateString, formatString = 'MMM d, yyyy h:mm a') => {
    if (!dateString) return 'N/A';
    return format(new Date(dateString), formatString);
};

const timeAgo = (dateString) => {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60,
    };
    
    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
        }
    }
    
    return 'Just now';
};
</script>

<template>
    <Head title="Support Tickets" />

    <AuthenticatedLayout>
        <template #header>
            
        </template>

        <div class="">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-gray-200 dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <div class="text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                            </div>
                            <Link :href="route('tickets.create')" as="button">
                                <PrimaryButton>
                                    <font-awesome-icon icon="plus" class="h-5 w-5 mr-1" />
                                    New Ticket
                                </PrimaryButton>
                            </Link>
                        </div>

                        <!-- Search and Filter Controls -->
                        <div class="flex justify-between items-center mb-4">
                            <div class="bg-uh-white dark:bg-gray-700 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 p-4 flex flex-col md:flex-row gap-4 w-full">
                                <!-- Search Bar -->
                                <div class="relative flex-1 max-w-2xl">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <font-awesome-icon icon="search" class="h-4 w-4 text-gray-400" />
                                    </div>
                                    <input
                                        type="text"
                                        v-model="search"
                                        class="h-full bg-gray-100 block w-full pl-10 pr-10 py-2.5 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-uh-teal/50 focus:border-uh-teal transition-colors duration-200"
                                        placeholder="Search tickets by title or description..."
                                    />
                                    <button 
                                        v-if="search"
                                        @click="search = ''"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none"
                                        type="button"
                                    >
                                        <font-awesome-icon icon="times" class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Ownership Scope -->
                                <div class="border border-gray-200 dark:border-gray-600 flex items-center space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 shrink-0">
                                    <button 
                                        @click="ownershipScope = (ownershipScope === 'assigned' ? null : 'assigned')" 
                                        :class="{'bg-gray-200 dark:bg-transparent text-uh-slate dark:text-uh-cream': ownershipScope === 'assigned', 'text-gray-500 dark:text-gray-400': ownershipScope !== 'assigned'}" 
                                        class="p-3 rounded-md text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-uh-teal/50"
                                        aria-label="Assigned to me"
                                        :aria-pressed="ownershipScope === 'assigned'"
                                        title="Assigned to me"
                                    >
                                        <font-awesome-icon :icon="['fas', 'user']" class="h-5 w-5" />
                                        <span class="sr-only">Assigned to me</span>
                                    </button>
                                    <button 
                                        @click="ownershipScope = (ownershipScope === 'submitted' ? null : 'submitted')" 
                                        :class="{'bg-gray-200 dark:bg-transparent text-uh-slate dark:text-uh-cream': ownershipScope === 'submitted', 'text-gray-500 dark:text-gray-400': ownershipScope !== 'submitted'}" 
                                        class="p-3 rounded-md text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-uh-teal/50"
                                        aria-label="Submitted by me"
                                        :aria-pressed="ownershipScope === 'submitted'"
                                        title="Submitted by me"
                                    >
                                        <font-awesome-icon :icon="['fas', 'inbox']" class="h-5 w-5" />
                                        <span class="sr-only">Submitted by me</span>
                                    </button>
                                </div>

                                <!-- Status Filters -->
                                <div class="bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 flex flex-col gap-3">
                                    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 w-full">
                                        <div class="w-full sm:w-64 sm:flex-none min-w-0">
                                            <MultiSelectCheckbox
                                                v-model="selectedStatuses"
                                                :options="statusOptions"
                                                placeholder="Filter by status"
                                                class="ms-status"
                                            />
                                        </div>
                                        <button 
                                            @click="clearAllStatuses" 
                                            class="w-full sm:w-auto shrink-0 px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600 dark:text-gray-200 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-teal/50"
                                        >
                                            Clear All
                                        </button>
                                    </div>

                                    <!-- Labels / Tags Filter -->
                                    <div class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 w-full">
                                        <div class="w-full sm:w-64 sm:flex-none min-w-0">
                                            <MultiSelectCheckbox
                                                v-model="selectedTags"
                                                :options="tagOptions"
                                                placeholder="Filter by labels"
                                                class="ms-tags"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- View Mode -->
                                <div class="border border-gray-200 dark:border-gray-600 flex items-center space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                                    <button 
                                        @click="viewMode = 'card'" 
                                        :class="{'bg-gray-200 dark:bg-transparent text-uh-slate dark:text-uh-cream': viewMode === 'card', 'text-gray-500 dark:text-gray-400': viewMode !== 'card'}" 
                                        class="p-3 rounded-md text-sm font-medium focus:outline-none"
                                        aria-label="Card view"
                                    >
                                        <font-awesome-icon :icon="['fas', 'th-large']" class="h-5 w-5" />
                                    </button>
                                    <button 
                                        @click="viewMode = 'list'" 
                                        :class="{'bg-gray-200 dark:bg-transparent text-uh-slate dark:text-uh-cream': viewMode === 'list', 'text-gray-500 dark:text-gray-400': viewMode !== 'list'}" 
                                        class="p-3 rounded-md text-sm font-medium focus:outline-none"
                                        aria-label="List view"
                                    >
                                        <font-awesome-icon :icon="['fas', 'bars']" class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tickets List -->
                        <div v-if="viewMode === 'card'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div v-if="tickets.data.length === 0" class="col-span-full text-center py-12">
                                <font-awesome-icon icon="ticket" class="mx-auto h-12 w-12 text-uh-gray" />
                                <h3 class="mt-2 text-sm font-medium text-uh-slate dark:text-uh-cream">No tickets</h3>
                                <p class="mt-1 text-sm text-uh-gray dark:text-uh-cream/70">
                                    Get started by creating a new ticket.
                                </p>
                                <div class="mt-6">
                                    <Link :href="route('tickets.create')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-uh-teal hover:bg-uh-green focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-teal">
                                        <font-awesome-icon icon="plus" class="-ml-1 mr-2 h-5 w-5" />
                                        New Ticket
                                    </Link>
                                </div>
                            </div>

                            <div v-for="ticket in tickets.data" :key="ticket.id" class="block bg-white dark:bg-gray-700 rounded-lg shadow overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col w-full">
                               <Link :href="route('tickets.show', ticket)">
                                <div class="p-6">
                                    <div class="flex justify-between items-start">
                                        <div class="w-0 flex-1">
                                            {{ ticket.title }}
                                            <p class="mt-1 text-sm text-uh-gray dark:text-uh-cream/70 line-clamp-2" v-html="ticket.description"></p>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', statusBadgeClasses[ticket.status]?.class || 'bg-uh-gray/20 text-uh-slate']">
                                                {{ ticket.status }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-between items-center">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400 mr-2">Submitted By:</span>
                                            <Avatar :user="ticket.user" size="xs" />
                                            <span class="ml-2 text-sm text-uh-slate dark:text-uh-cream">{{ ticket.user.name }}</span>
                                        </div>
                                    </div>
                                    <!-- Tags -->
                                    <div v-if="ticket.tags && ticket.tags.length > 0" class="mt-3 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="tag in ticket.tags"
                                            :key="tag.id"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-teal/20 text-uh-slate dark:text-uh-cream border border-uh-teal/30"
                                        >
                                            {{ tag.name }}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                            </div>
                        </div>

                        <div v-else>
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-t-lg">
                                <div class="overflow-x-auto">
                                    <div class="inline-block min-w-full align-middle">
                                        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-t-lg" style="-webkit-overflow-scrolling: touch;">
                                            <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-600">
                                                <thead class="bg-uh-slate/10 dark:bg-uh-slate/20">
                                                    <tr>
                                                        <th @click="sortBy('title')" scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-uh-slate dark:text-uh-cream sm:pl-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                            <div class="flex items-center">
                                                                Ticket
                                                                <span class="ml-1">{{ getSortIndicator('title') }}</span>
                                                            </div>
                                                        </th>
                                                        <th @click="sortBy('status')" scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-uh-slate dark:text-uh-cream lg:table-cell cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                            <div class="flex items-center">
                                                                Status
                                                                <span class="ml-1">{{ getSortIndicator('status') }}</span>
                                                            </div>
                                                        </th>
                                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-uh-slate dark:text-uh-cream lg:table-cell">
                                                        <div class="flex items-center">
                                                            Assignee
                                                        </div>
                                                    </th>
                                                        <th @click="sortBy('created_at')" scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-uh-slate dark:text-uh-cream cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                            <div class="flex items-center">
                                                                <span>Created</span>
                                                                <span class="ml-1">{{ getSortIndicator('created_at') }}</span>
                                                            </div>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-uh-white dark:bg-uh-slate/10 divide-y divide-uh-slate/10 dark:divide-uh-slate/20">
                                                    <tr v-if="sortedTickets.length === 0">
                                                        <td colspan="5" class="px-6 py-12 text-center">
                                                            <font-awesome-icon icon="ticket" class="mx-auto h-12 w-12 text-uh-gray" />
                                                            <h3 class="mt-2 text-sm font-medium text-uh-slate dark:text-uh-cream">No tickets</h3>
                                                            <p class="mt-1 text-sm text-uh-gray dark:text-uh-cream/70">
                                                                Get started by creating a new ticket.
                                                            </p>
                                                            <div class="mt-6">
                                                                <Link :href="route('tickets.create')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-uh-teal hover:bg-uh-green focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-uh-teal">
                                                                    <font-awesome-icon icon="plus" class="-ml-1 mr-2 h-5 w-5" />
                                                                    New Ticket
                                                                </Link>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <tr v-for="ticket in sortedTickets" :key="ticket.id" 
    @click="$inertia.visit(route('tickets.show', ticket.id))"
    class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer"
>
                                                        <td class="py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-gray-100 sm:pl-6">
                                                            <div class="flex flex-col gap-2">
                                                                <Link :href="route('tickets.show', ticket.id)" class="font-medium text-uh-slate dark:text-uh-cream hover:text-uh-teal dark:hover:text-uh-teal/80 transition-colors">
                                                                    {{ ticket.title }}
                                                                </Link>
                                                                <!-- Tags -->
                                                                <div v-if="ticket.tags && ticket.tags.length > 0" class="flex flex-wrap gap-1">
                                                                    <span
                                                                        v-for="tag in ticket.tags"
                                                                        :key="tag.id"
                                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-teal/20 text-uh-slate dark:text-uh-cream border border-uh-teal/30"
                                                                    >
                                                                        {{ tag.name }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <!-- Status Badge -->
                                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400 lg:table-cell">
                                                            <span :class="[statusBadgeClasses[ticket.status]?.class || 'bg-gray-100 text-gray-800', 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize']">
                                                                {{ ticket.status }}
                                                            </span>
                                                        </td>
                                                        <!-- Assignees (Admin Only) -->
                                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400 lg:table-cell">
                                                            <div class="flex items-center gap-1 flex-wrap">
                                                                <template v-if="ticket.assignees && ticket.assignees.length">
                                                                    <span v-for="user in ticket.assignees" :key="user.id" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-uh-teal/10 dark:bg-uh-teal/20 text-uh-slate dark:text-uh-cream">
                                                                        <Avatar :user="user" size="xs" class="mr-1" />
                                                                        {{ user.name }}
                                                                    </span>
                                                                </template>
                                                                <span v-else class="text-gray-400">Unassigned</span>
                                                            </div>
                                                        </td>
                                                        <!-- Created At -->
                                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                                            <div class="sm:block">
                                                                <div>{{ formatDateTime(ticket.created_at, 'MMM d, yyyy') }}</div>
                                                                <div class="text-xs text-gray-400">{{ timeAgo(ticket.created_at) }}</div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="tickets.links.length > 3" class="mt-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <span class="font-medium">{{ tickets.from }}</span> to <span class="font-medium">{{ tickets.to }}</span> of <span class="font-medium">{{ tickets.total }}</span> results
                                </div>
                                <div class="flex space-x-2">
                                    <template v-for="(link, index) in tickets.links" :key="index">
                                        <Link 
                                            v-if="link.url"
                                            :href="link.url"
                                            v-html="link.label"
                                            :class="{
                                                'px-4 py-2 text-sm font-medium rounded-md': true,
                                                'bg-uh-teal text-white': link.active,
                                                'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700': !link.active && link.url,
                                                'text-gray-400 dark:text-gray-500 cursor-not-allowed': !link.url,
                                            }"
                                            preserve-scroll
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Override MultiSelectCheckbox button bg in dark mode to gray-800 */
.dark .ms-status :deep(button) {
  /* Preferred if @apply works in your setup */
  @apply bg-gray-800;

  /* Or fallback without Tailwind @apply */
  /* background-color: rgb(55 65 81) !important; */
}
</style>
