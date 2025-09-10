<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import Avatar from '@/Components/Avatar.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

// Font Awesome
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { 
    faHouse, 
    faTicket, 
    faUsers,
    faUser,
    faGear,
    faRightFromBracket,
    faBars,
    faXmark,
    faAddressBook,
    faChevronDown,
    faNewspaper,
    faChartBar,
    faArchive
} from '@fortawesome/free-solid-svg-icons';

// RBAC composable
import { useHasAny } from '@/Extensions/useAuthz';

// Add icons to the library
library.add(faHouse, faTicket, faUsers, faUser, faGear, faRightFromBracket, faBars, faXmark, faAddressBook, faChevronDown, faNewspaper, faChartBar, faArchive);

// Permission helpers (team-aware)
const isSuperAdmin = computed(() => usePage().props.auth?.user?.isSuperAdmin === true);
const canManageSuperAdmins = computed(() => usePage().props.auth?.user?.canManageSuperAdmins === true);
const hasAny = (permissions = []) => useHasAny(permissions).value;

// Sidebar state
// Theme state
const darkMode = ref(localStorage.getItem('darkMode') === 'true' || 
                   (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches));

// Animation state
const sunOpacity = ref(0);
const moonOpacity = ref(0);
let sunFadeTimeout = null;
let moonFadeTimeout = null;

// Toggle dark mode
const toggleDarkMode = () => {
    darkMode.value = !darkMode.value;
    // Force update the class immediately
    if (darkMode.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    // Save preference to localStorage
    localStorage.setItem('darkMode', darkMode.value);
    console.log('Dark mode:', darkMode.value ? 'enabled' : 'disabled');

    
};

// ----- Global Flash Messages (Toastify) -----
const page = usePage();

const extractFlash = (flash) => {
    if (!flash) return null;
    // Support multiple conventions
    if (flash.success) return { text: flash.success, style: 'success' };
    if (flash.error) return { text: flash.error, style: 'error' };
    if (flash.warning) return { text: flash.warning, style: 'warning' };
    if (flash.info) return { text: flash.info, style: 'info' };
    if (flash.status && flash.message) return { text: flash.message, style: flash.status };
    if (flash.message) return { text: flash.message, style: 'info' };
    return null;
};

// Trigger toast when flash props change
watch(
    () => page.props.flash,
    (val) => {
        const f = extractFlash(val);
        if (!f || !f.text) return;
        const theme = darkMode.value ? 'dark' : 'light';
        const opts = { autoClose: 4000, theme };
        if (f.style === 'success') return toast.success(f.text, opts);
        if (f.style === 'error') return toast.error(f.text, opts);
        if (f.style === 'warning') return toast.warn(f.text, opts);
        return toast.info(f.text, opts);
    },
    { immediate: true }
);

// Update dark mode class on HTML element based on saved preference
const updateDarkMode = () => {
    const isDark = localStorage.getItem('darkMode') === 'true' || 
                  (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    darkMode.value = isDark;
    
    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

const isSidebarOpen = ref(false);
const isMobile = ref(false);
const userMenuOpen = ref(false);
// Collapsible state for User Management submenu
const userMgmtOpen = ref(route().current('admin.*'));
// Collapsible state for Newsletter submenu (open when on any authenticated newsletter route)
const newsletterOpen = ref(route().current('newsletter.*'));

// Media query references for cleanup
let darkModeMediaQuery = null;
let darkModeChangeHandler = null;

// Check screen size and handle sidebar state
const checkScreenSize = () => {
    isMobile.value = window.innerWidth < 1024; // lg breakpoint
    
    if (!isMobile.value) {
        // Always show sidebar on desktop
        isSidebarOpen.value = true;
    } else {
        // Close sidebar by default on mobile
        isSidebarOpen.value = false;
    }
};

// Toggle sidebar
const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

// Close sidebar and user menu when clicking outside
const handleClickOutside = (event) => {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.querySelector('[aria-controls="mobile-sidebar"]');
    const userMenu = document.getElementById('user-menu-button');
    
    // Close sidebar if open and click is outside
    if (
        isSidebarOpen.value &&
        sidebar &&
        !sidebar.contains(event.target) &&
        (!sidebarToggle || !sidebarToggle.contains(event.target))
    ) {
        isSidebarOpen.value = false;
    }
    
    // Close user menu if open and click is outside
    if (userMenuOpen.value && userMenu && !userMenu.contains(event.target)) {
        userMenuOpen.value = false;
    }
};

// Configuration for special page titles
const pageTitles = {
    // Format: 'path-fragment': 'Display Name'
    'tickets': 'Tickets',
    'users': 'Users',
    'dashboard': 'Dashboard',
    'profile': 'My Profile',
    'directory': 'Directory',
    'newsletter': 'Newsletter',
    // Add more special cases as needed
};

/**
 * Format page title from component path
 * @param {string} componentPath - Full component path (e.g., 'Pages/Users/Index')
 * @returns {string} Formatted page title
 */
const formatPageTitle = (componentPath) => {
    if (!componentPath) return '';
    
    // Convert to lowercase for case-insensitive matching
    const path = componentPath.toLowerCase();
    
    // Check for special cases in the configuration
    const specialCase = Object.entries(pageTitles).find(([key]) => 
        path.includes(key.toLowerCase())
    );
    
    if (specialCase) {
        return specialCase[1];
    }
    
    // Default formatting for other pages
    const component = componentPath.split('/').pop() || '';
    if (!component) return '';
    
    // Convert camelCase/PascalCase to Title Case with spaces
    return component
        // Add space before capital letters
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        // Capitalize first letter of each word
        .replace(/\b\w/g, char => char.toUpperCase())
        // Clean up any multiple spaces
        .replace(/\s+/g, ' ')
        .trim();
};

// Set up event listeners
onMounted(() => {
    checkScreenSize();
    updateDarkMode();
    window.addEventListener('resize', checkScreenSize);
    document.addEventListener('click', handleClickOutside);
    
    // Set initial animation state
    if (darkMode.value) {
        sunOpacity.value = 0;
        moonOpacity.value = 0;
    } else {
        sunOpacity.value = 0;
        moonOpacity.value = 0;
    }

    // Listen for system color scheme changes
    darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    darkModeChangeHandler = (e) => {
        if (!('darkMode' in localStorage)) {
            darkMode.value = e.matches;
            updateDarkMode();
        }
    };
    darkModeMediaQuery.addEventListener('change', darkModeChangeHandler);
});

// Clean up event listeners
onUnmounted(() => {
    window.removeEventListener('resize', checkScreenSize);
    document.removeEventListener('click', handleClickOutside);
    if (darkModeMediaQuery && darkModeChangeHandler) {
        darkModeMediaQuery.removeEventListener('change', darkModeChangeHandler);
    }
});

// Handle navigation
const navigate = (url) => {
    router.visit(url);
    // Close sidebar after navigation on mobile
    if (isMobile.value) {
        isSidebarOpen.value = false;
    }
};
</script>

<template>
    <div :class="{ 'dark': darkMode }" class="relative flex h-screen bg-gray-100 dark:bg-gray-900 overflow-hidden font-sans">
        

        <!-- Mobile sidebar backdrop -->
        <transition
            enter-active-class="transition-opacity ease-linear duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="isSidebarOpen && isMobile"
                class="fixed inset-0 z-20 bg-black/70 lg:hidden backdrop-blur-sm"
                @click="toggleSidebar"
            ></div>
        </transition>

        <!-- Sidebar -->
        <aside 
            id="sidebar"
            class="fixed inset-y-0 left-0 z-30 flex flex-col w-64 transform overflow-y-auto bg-uh-white text-uh-black dark:bg-gray-800 dark:text-uh-white transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0"
            :class="{
                '-translate-x-full': !isSidebarOpen && isMobile,
                'translate-x-0': isSidebarOpen || !isMobile,
                'shadow-2xl': isMobile
            }"
        >
            <div class="flex h-full flex-col">
                <!-- Logo -->
                <div class="flex h-20 shadow shrink-0 items-center px-4">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <img class="rounded-lg h-12 w-12" src="https://placehold.co/60/c8102e/fff?text=UHPH">
                        <span class="text-white ml-3 text-xl font-bold whitespace-nowrap">
                            The Hub
                        </span>
                    </Link>
                </div>

                <!-- Navigation Links -->
                <nav class="mt-6 px-2 overflow-hidden flex-1">
                    <div class="space-y-2">
                        <NavLink 
                            :href="route('dashboard')" 
                            :active="route().current('dashboard')"
                            class="group flex items-center px-3 py-2.5 rounded-md text-sm font-medium"
                            :class="[
                                route().current('dashboard')
                                    ? 'bg-uh-red text-white shadow-md'
                                    : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                            ]"
                            @click="navigate(route('dashboard'))"
                        >
                            <font-awesome-icon 
                                :icon="['fas', 'house']" 
                                class="h-5 w-5 flex-shrink-0"
                                :class="[
                                    route().current('dashboard')
                                        ? 'text-white'
                                        : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                ]"
                            />
                            <span class="ml-3 font-medium">Dashboard</span>
                        </NavLink>

                        <NavLink 
                            v-if="isSuperAdmin || hasAny(['tickets.app.access'])"
                            :href="route('tickets.index')" 
                            :active="route().current('tickets.*')"
                            class="group flex items-center px-3 py-2.5 rounded-md text-sm font-medium"
                            :class="[
                                route().current('tickets.*')
                                    ? 'bg-uh-red text-white shadow-md'
                                    : 'text-uh-slate dark:text-gray-400     hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                            ]"
                            @click="navigate(route('tickets.index'))"
                        >
                            <font-awesome-icon 
                                :icon="['fas', 'ticket']" 
                                class="h-5 w-5 flex-shrink-0 transition-colors duration-200"
                                :class="[
                                    route().current('tickets.*')
                                        ? 'text-white'
                                        : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                ]"
                            />
                            <span class="ml-3 font-medium">Tickets</span>
                        </NavLink>

                        <NavLink 
                            v-if="isSuperAdmin || hasAny(['directory.app.access'])"
                            :href="route('directory.index')" 
                            :active="route().current('directory.*')"
                            class="group flex items-center px-3 py-2.5 rounded-md text-sm font-medium transition-all duration-200"
                            :class="[
                                route().current('directory.*')
                                    ? 'bg-uh-red text-white shadow-md'
                                    : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                            ]"
                            @click="navigate(route('directory.index'))"
                        >
                            <font-awesome-icon 
                                :icon="['fas', 'address-book']" 
                                class="h-5 w-5 flex-shrink-0 transition-colors duration-200"
                                :class="[
                                    route().current('directory.*')
                                        ? 'text-white'
                                        : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                ]"
                            />
                            <span class="ml-3 font-medium">Directory</span>
                        </NavLink>

                        <!-- Newsletter: Top-level toggle-only (no direct navigation) -->
                        <button
                            v-if="isSuperAdmin || hasAny(['newsletter.app.access'])"
                            type="button"
                            class="group flex w-full items-center px-3 py-2.5 rounded-md text-sm font-medium transition-all duration-200"
                            :class="[
                                (newsletterOpen || route().current('newsletter.*'))
                                    ? 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                    : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                            ]"
                            @click="newsletterOpen = !newsletterOpen"
                            :aria-expanded="newsletterOpen"
                            :aria-controls="'newsletter-submenu'"
                            title="Toggle newsletter menu"
                        >
                            <font-awesome-icon 
                                :icon="['fas', 'newspaper']" 
                                class="h-5 w-5 flex-shrink-0 transition-colors duration-200"
                                :class="[
                                    route().current('newsletter.*')
                                        ? 'text-white'
                                        : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-uh-forest'
                                ]"
                            />
                            <span class="ml-3 font-medium flex-1 text-left">Newsletter</span>
                            <font-awesome-icon 
                                :icon="['fas','chevron-down']" 
                                class="h-4 w-4 transition-transform duration-200 ml-auto"
                                :class="{ 'rotate-180': newsletterOpen }"
                            />
                        </button>

                        <!-- Newsletter Submenu -->
                        <div
                            v-if="isSuperAdmin || hasAny(['newsletter.app.access'])"
                            v-show="newsletterOpen"
                            id="newsletter-submenu"
                            class="pl-5 space-y-1 mt-2"
                        >
                            <NavLink 
                                :href="route('newsletter.index')" 
                                :active="route().current('newsletter.index')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.index')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.index'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'newspaper']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.index')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Latest News</span>
                            </NavLink>

                            <NavLink 
                                v-if="isSuperAdmin || hasAny(['newsletter.manage'])"
                                :href="route('newsletter.dashboard')" 
                                :active="route().current('newsletter.dashboard')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.dashboard')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.dashboard'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'house']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.dashboard')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Dashboard</span>
                            </NavLink>

                            <NavLink 
                                v-if="isSuperAdmin || hasAny(['newsletter.manage'])"
                                :href="route('newsletter.campaigns.index')" 
                                :active="route().current('newsletter.campaigns.*')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.campaigns.*')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.campaigns.index'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'newspaper']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.campaigns.*')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Campaigns</span>
                            </NavLink>

                            <NavLink 
                                v-if="isSuperAdmin || hasAny(['newsletter.manage'])"
                                :href="route('newsletter.templates.index')" 
                                :active="route().current('newsletter.templates.*')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.templates.*')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.templates.index'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'gear']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.templates.*')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Templates</span>
                            </NavLink>

                            <NavLink 
                                v-if="isSuperAdmin || hasAny(['newsletter.manage'])"
                                :href="route('newsletter.subscribers.index')" 
                                :active="route().current('newsletter.subscribers.*')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.subscribers.*')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.subscribers.index'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'users']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.subscribers.*')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Subscribers</span>
                            </NavLink>

                            <NavLink 
                                v-if="isSuperAdmin || hasAny(['newsletter.manage'])"
                                :href="route('newsletter.analytics')" 
                                :active="route().current('newsletter.analytics')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.analytics')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.analytics'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'chart-bar']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.analytics')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Analytics</span>
                            </NavLink>

                            <NavLink 
                                v-if="isSuperAdmin || hasAny(['newsletter.manage'])"
                                :href="route('newsletter.campaigns.timecapsule')" 
                                :active="route().current('newsletter.campaigns.timecapsule')"
                                class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    route().current('newsletter.campaigns.timecapsule')
                                        ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                                @click="navigate(route('newsletter.campaigns.timecapsule'))"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'archive']" 
                                    class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('newsletter.campaigns.timecapsule')
                                            ? 'text-white'
                                            : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                    ]"
                                />
                                <span class="ml-3 font-medium">Time Capsule</span>
                            </NavLink>
                        </div>

                        <!-- User Management Section -->
                        <div v-if="
                            isSuperAdmin ||
                            hasAny(['hub.app.access']) ||
                            hasAny(['admin.rbac.roles.manage', 'admin.rbac.permissions.manage', 'admin.rbac.overrides.manage']) ||
                            canManageSuperAdmins
                        ">
                            <button
                                @click="userMgmtOpen = !userMgmtOpen"
                                class="group flex items-center w-full px-3 py-2.5 rounded-md text-sm font-medium transition-all duration-200"
                                :class="[
                                    (userMgmtOpen || route().current('admin.*'))
                                        ? 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                ]"
                            >
                                <font-awesome-icon 
                                    :icon="['fas', 'users']" 
                                    class="h-5 w-5 flex-shrink-0 transition-colors duration-200"
                                    :class="[
                                        route().current('admin.*')
                                            ? 'text-white'
                                        : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-uh-forest'
                                    ]"
                                />
                                <span class="ml-3 font-medium flex-1 text-left">User Management</span>
                                <font-awesome-icon 
                                    :icon="['fas','chevron-down']" 
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{ 'rotate-180': userMgmtOpen }"
                                />
                            </button>

                            <!-- User Management Submenu -->
                            <div
                                v-show="userMgmtOpen"
                                class="pl-5 space-y-1 mt-2"
                            >
                                <NavLink 
                                    v-if="isSuperAdmin || hasAny(['hub.app.access'])"
                                    :href="route('admin.users.index')" 
                                    :active="route().current('admin.users.*')"
                                    class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                    :class="[
                                        route().current('admin.users.*')
                                            ? 'bg-uh-red text-white shadow-md'
                                    : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                    ]"
                                    @click="navigate(route('admin.users.index'))"
                                >
                                    <font-awesome-icon 
                                        :icon="['fas', 'users']" 
                                        class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                        :class="[
                                            route().current('admin.users.*')
                                                ? 'text-white'
                                                : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                        ]"
                                    />
                                    <span class="ml-3 font-medium">All Users</span>
                                </NavLink>

                                <NavLink 
                                    v-if="isSuperAdmin || hasAny(['hub.app.access'])"
                                    :href="route('admin.invites.index')" 
                                    :active="route().current('admin.invites.*')"
                                    class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                    :class="[
                                        route().current('admin.invites.*')
                                            ? 'bg-uh-red text-white shadow-md'
                                        : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                    ]"
                                    @click="navigate(route('admin.invites.index'))"
                                >
                                    <font-awesome-icon 
                                        :icon="['fas', 'users']" 
                                        class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                        :class="[
                                            route().current('admin.invites.*')
                                                ? 'text-white'
                                                : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                        ]"
                                    />
                                    <span class="ml-3 font-medium">Invites</span>
                                </NavLink>

                                <NavLink 
                                    v-if="isSuperAdmin || hasAny(['admin.rbac.roles.manage', 'admin.rbac.permissions.manage', 'admin.rbac.overrides.manage'])"
                                    :href="route('admin.rbac.dashboard')" 
                                    :active="route().current('admin.rbac.*')"
                                    class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                    :class="[
                                        route().current('admin.rbac.*')
                                            ? 'bg-uh-red text-white shadow-md'
                                    : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                    ]"
                                    @click="navigate(route('admin.rbac.dashboard'))"
                                >
                                    <font-awesome-icon 
                                        :icon="['fas', 'gear']" 
                                        class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                        :class="[
                                            route().current('admin.rbac.*')
                                                ? 'text-white'
                                                : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                        ]"
                                    />
                                    <span class="ml-3 font-medium">RBAC Admin</span>
                                </NavLink>

                                <NavLink 
                                    v-if="canManageSuperAdmins"
                                    :href="route('admin.superadmin.index')" 
                                    :active="route().current('admin.superadmin.*')"
                                    class="group flex items-center w-full px-3 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                    :class="[
                                        route().current('admin.superadmin.*')
                                            ? 'bg-uh-red text-white shadow-md'
                                    : 'text-uh-slate dark:text-gray-400 hover:bg-gray-100/60 dark:hover:bg-gray-700/60 hover:text-uh-forest'
                                    ]"
                                    @click="navigate(route('admin.superadmin.index'))"
                                >
                                    <font-awesome-icon 
                                        :icon="['fas', 'user']" 
                                        class="h-4 w-4 flex-shrink-0 transition-colors duration-200"
                                        :class="[
                                            route().current('admin.superadmin.*')
                                                ? 'text-white'
                                                : 'text-gray-400 dark:group-hover:text-gray-200 group-hover:text-white'
                                        ]"
                                    />
                                    <span class="ml-3 font-medium">Super Admin</span>
                                </NavLink>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Main content -->
        <div class="relative z-10 flex-1 flex flex-col overflow-hidden">
            <!-- Top navigation -->
            <header class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm shadow-sm h-20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                    <div class="flex justify-between items-center">
                        <!-- Sidebar Toggle Button -->
                        <button 
                            @click="toggleSidebar"
                            class="lg:hidden p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none rounded-full"
                            aria-controls="mobile-sidebar"
                            :aria-expanded="isSidebarOpen"
                        >
                            <span class="sr-only">Open sidebar</span>
                            <font-awesome-icon :icon="isSidebarOpen ? ['fas','xmark'] : ['fas','bars']" class="h-5 w-5" />
                        </button>
                        
                        <!-- Page Title -->
                        <!-- <div class="hidden md:block flex-1 px-4">
                            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-200">
                                {{ formatPageTitle($page.component) }}
                            </h1>
                        </div>
                        -->
                        <div></div>
                        <div class="flex items-center">
                            <div class="flex items-center space-x-2 md:space-x-3">
                               
                                <!-- Theme Toggle Button -->
                                <button 
                                    @click="toggleDarkMode" 
                                    class="p-3 rounded-full text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 
                                           focus:outline-none shadow dark:bg-gray-900
                                           hover:bg-gray-100 dark:hover:bg-gray-700"
                                    :title="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                                    aria-label="Toggle dark mode"
                                >
                                    <svg v-if="darkMode" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                                    </svg>
                                </button>

                                <!-- User Menu -->
                                <div class="relative" @click.stop>
                                    <button 
                                        type="button" 
                                        class="p-1.5 rounded-full text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 
                                           focus:outline-none shadow dark:bg-gray-900
                                           hover:bg-gray-100 dark:hover:bg-gray-700"
                                        @click="userMenuOpen = !userMenuOpen"
                                        id="user-menu-button" 
                                        :aria-expanded="userMenuOpen"
                                        aria-haspopup="true"
                                    >
                                        <span class="sr-only">Open user menu</span>
                                        <div class="flex items-center">
                                            <span class="mr-3 px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                {{ $page.props.auth?.user?.name || 'Account' }}
                                            </span>
                                            <Avatar v-if="$page.props.auth?.user" :user="$page.props.auth.user" size="sm" :show-link="false" />
                                        </div>
                                    </button>

                                    <!-- Dropdown Menu -->
                                    <transition
                                        enter-active-class="transition ease-out duration-100 transform"
                                        enter-from-class="opacity-0 scale-95"
                                        enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75 transform"
                                        leave-from-class="opacity-100 scale-100"
                                        leave-to-class="opacity-0 scale-95"
                                    >
                                        <div 
                                            v-show="userMenuOpen"
                                            class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                                            role="menu"
                                            aria-orientation="vertical"
                                            aria-labelledby="user-menu-button"
                                            tabindex="-1"
                                        >
                                            <div class="py-1" role="none">
                                                <!-- Account Management -->
                                                <div class="block px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                                    Account
                                                </div>
                                                
                                                <a 
                                                    :href="route('profile.edit')" 
                                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                                                    role="menuitem"
                                                    tabindex="-1"
                                                >
                                                    Profile Settings
                                                </a>
                                                
                                                <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                                                
                                                <!-- Authentication -->
                                                <form @submit.prevent="$inertia.post(route('logout'))" class="w-full" role="none">
                                                    <button 
                                                        type="submit" 
                                                        class="w-full text-left block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 
                                                               hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                                                        role="menuitem"
                                                        tabindex="-1"
                                                    >
                                                        Sign out
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </transition>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main content area -->
            <main class="flex-1 overflow-y-auto bg-transparent p-4 md:p-6">
                <!-- Page Heading -->
                <header class="mb-6" v-if="$slots.header">
                    <div class="px-4 sm:px-0">
                        <slot name="header" />
                    </div>
                </header>

                <!-- Page Content -->
                <div class="px-4 sm:px-0">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
