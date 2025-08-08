<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import { Link, router } from '@inertiajs/vue3';

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
    faXmark
} from '@fortawesome/free-solid-svg-icons';

// Add icons to the library
library.add(faHouse, faTicket, faUsers, faUser, faGear, faRightFromBracket, faBars, faXmark);

// Sidebar state
// Theme state
const darkMode = ref(localStorage.getItem('darkMode') === 'true' || 
                   (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches));

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
    if (isSidebarOpen.value && sidebar && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
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
    
    // Listen for system color scheme changes
    const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = (e) => {
        if (!('darkMode' in localStorage)) {
            darkMode.value = e.matches;
            updateDarkMode();
        }
    };
    darkModeMediaQuery.addEventListener('change', handleChange);
    
    return () => {
        window.removeEventListener('resize', checkScreenSize);
        document.removeEventListener('click', handleClickOutside);
        darkModeMediaQuery.removeEventListener('change', handleChange);
    };
});

// Clean up event listeners
onUnmounted(() => {
    window.removeEventListener('resize', checkScreenSize);
    document.removeEventListener('click', handleClickOutside);
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
    <div :class="{ 'dark': darkMode }" class="flex h-screen bg-gray-100 dark:bg-gray-900 overflow-hidden font-sans">
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
            class="fixed inset-y-0 left-0 z-30 flex flex-col w-64 transform overflow-y-auto bg-gradient-to-b from-gray-900 to-gray-800 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0"
            :class="{
                '-translate-x-full': !isSidebarOpen && isMobile,
                'translate-x-0': isSidebarOpen || !isMobile,
                'shadow-2xl': isMobile
            }"
        >
            <div class="flex h-full flex-col">
                <!-- Logo -->
                <div class="flex h-20 shrink-0 items-center px-4 border-b border-gray-700/50">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <img class="rounded-lg h-12 w-12" src="https://placehold.co/60/c8102e/fff?text=UHPH">
                        <span class="text-white ml-3 text-xl font-bold whitespace-nowrap">
                            The Hub
                        </span>
                    </Link>
                </div>

                <!-- Navigation Links -->
                <nav class="mt-6 px-2 overflow-hidden flex-1">
                    <div class="space-y-1">
                        <NavLink 
                            :href="route('dashboard')" 
                            :active="route().current('dashboard')"
                            class="group flex items-center rounded-lg px-3 py-3 text-sm font-medium relative"
                            :class="{
                                'bg-gradient-to-r from-uh-red/20 to-transparent text-white shadow-uh-red/20': route().current('dashboard'),
                                'text-gray-300 hover:bg-gray-800/50 hover:text-white': !route().current('dashboard')
                            }"
                            @click="navigate(route('dashboard'))"
                        >
                            <span class="absolute left-0 w-1 h-6 bg-uh-red rounded-r-lg" :class="{ 'opacity-0': !route().current('dashboard') }"></span>
                            <font-awesome-icon :icon="['fas', 'house']" class="h-5 w-5 flex-shrink-0" 
                                :class="{
                                    'text-uh-red': route().current('dashboard'),
                                    'text-gray-400 group-hover:text-white': !route().current('dashboard')
                                }" 
                            />
                            <span class="ml-4 text-sm font-medium">
                                Dashboard
                            </span>
                        </NavLink>

                        <NavLink 
                            :href="route('tickets.index')" 
                            :active="route().current('tickets.*')"
                            class="group flex items-center rounded-lg px-3 py-3 text-sm font-medium relative"
                            :class="{
                                'bg-gradient-to-r from-uh-red/20 to-transparent text-white shadow-uh-red/20': route().current('tickets.*'),
                                'text-gray-300 hover:bg-gray-800/50 hover:text-white': !route().current('tickets.*')
                            }"
                            @click="navigate(route('tickets.index'))"
                        >
                            <span class="absolute left-0 w-1 h-6 bg-uh-red rounded-r-lg" :class="{ 'opacity-0': !route().current('tickets.*') }"></span>
                            <font-awesome-icon :icon="['fas', 'ticket']" class="h-5 w-5 flex-shrink-0"
                                :class="{
                                    'text-uh-red': route().current('tickets.*'),
                                    'text-gray-400 group-hover:text-white': !route().current('tickets.*')
                                }"
                            />
                            <span class="ml-4 text-sm font-medium">
                                Tickets
                            </span>
                        </NavLink>

                        <NavLink 
                            v-if="$page.props.auth.user.roles?.includes('admin')"
                            :href="route('admin.users.index')" 
                            :active="route().current('admin.users.*')"
                            class="group flex items-center rounded-lg px-3 py-3 text-sm font-medium relative"
                            :class="{
                                'bg-gradient-to-r from-uh-red/20 to-transparent text-white shadow-uh-red/20': route().current('admin.users.*'),
                                'text-gray-300 hover:bg-gray-800/50 hover:text-white': !route().current('admin.users.*')
                            }"
                            @click="navigate(route('admin.users.index'))"
                        >
                            <span class="absolute left-0 w-1 h-6 bg-uh-red rounded-r-lg" :class="{ 'opacity-0': !route().current('admin.users.*') }"></span>
                            <font-awesome-icon :icon="['fas', 'users']" class="h-5 w-5 flex-shrink-0"
                                :class="{
                                    'text-uh-red': route().current('admin.users.*'),
                                    'text-gray-400 group-hover:text-white': !route().current('admin.users.*')
                                }"
                            />
                            <span class="ml-4 text-sm font-medium">
                                User Management
                            </span>
                        </NavLink>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top navigation -->
            <header class="bg-white dark:bg-gray-800 shadow-sm h-20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                    <div class="flex justify-between items-center">
                        <!-- Sidebar Toggle Button -->
                        <button 
                            @click="toggleSidebar"
                            class="lg:hidden p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none rounded-full transition-colors duration-200"
                            aria-controls="mobile-sidebar"
                            :aria-expanded="isSidebarOpen"
                        >
                            <span class="sr-only">Open sidebar</span>
                            <font-awesome-icon :icon="isSidebarOpen ? 'bars' : 'bars'" class="h-5 w-5" />
                        </button>
                        
                        <!-- Page Title -->
                        <div class="hidden md:block flex-1 px-4">
                            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-200">
                                {{ formatPageTitle($page.component) }}
                            </h1>
                        </div>
                        
                        <div class="flex items-center">
                            <div class="flex items-center space-x-2 md:space-x-3">
                                <!-- Theme Toggle Button -->
                                <button 
                                    @click="toggleDarkMode" 
                                    class="p-3 rounded-full text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 
                                           focus:outline-none ring-uh-teal dark:ring-uh-red/50 ring-2 focus:ring-uh-red
                                           transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-700"
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
                                        class="flex items-center p-1.5 rounded-full bg-white dark:bg-gray-800 text-sm 
                                               focus:outline-none ring-uh-teal dark:ring-uh-red/50 ring-2 focus:ring-uh-teal 
                                               hover:ring-uh-teal hover:ring-2 hover:ring-gray-200 dark:hover:ring-gray-600 transition-all duration-200"
                                        @click="userMenuOpen = !userMenuOpen"
                                        id="user-menu-button" 
                                        :aria-expanded="userMenuOpen"
                                        aria-haspopup="true"
                                    >
                                        <span class="sr-only">Open user menu</span>
                                        <div class="flex items-center">
                                            <span class="mr-3 px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                {{ $page.props.auth.user.name }}
                                            </span>
                                            <div class="h-9 w-9 rounded-full bg-uh-red flex items-center justify-center text-white font-semibold text-sm">
                                                {{ $page.props.auth.user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) }}
                                            </div>
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
            <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900 p-4 md:p-6">
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
