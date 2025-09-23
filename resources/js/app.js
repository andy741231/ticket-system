import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n from './i18n';

// Font Awesome
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { 
    faBars, 
    faXmark, 
    faHouse, 
    faTicket, 
    faUsers,
    faUser,
    faGear,
    faRightFromBracket,
    faChevronLeft,
    faPlus,
    faSearch,
    faThLarge,
    faFilter,
    faUserTag,
    faTimes,
    faTicketAlt,
    faSortUp,
    faSortDown,
    faInbox,
    faSync,
    faPause,
    faCheckCircle,
    faTimesCircle,
    faLock,
    faArrowDown,
    faArrowsAltV,
    faArrowUp,
    faThumbsUp,
    faThumbsDown,
    faCopy,
    faCheck,
    faEdit,
    faComments,
    faPaperclip,
    faFile,
    faThumbtack,
    faTrash,
    faFaceSmile,
    faReply,
    // Annotation toolbar icons
    faMousePointer,
    faMapPin,
    faSquare,
    faCircle,
    faArrowRight,
    faPencilAlt,
    faFont,
    faSearchMinus,
    faSearchPlus,
    faExpandArrowsAlt,
    faUndo,
    faRedo,
    faKeyboard,
    faExclamationTriangle,
    faCommentDots,
    faStickyNote,
    // Additional icons for annotation canvas
    faHandPaper,
    faDrawPolygon,
    faICursor,
    faCompressArrowsAlt,
    faLink,
    faPaperPlane,
    faSpinner
} from '@fortawesome/free-solid-svg-icons';

// Import regular icons
import {
    faSquare as farSquare,
    faCircle as farCircle
} from '@fortawesome/free-regular-svg-icons';

// Add icons to the library
library.add(
    faBars, faXmark, faHouse, faTicket, faUsers, faUser, faGear, faRightFromBracket,
    faChevronLeft, faPlus, faSearch, faThLarge, faFilter, faUserTag, faTimes, faTicketAlt,
    faSortUp, faSortDown, faInbox, faSync, faPause, faCheckCircle, faTimesCircle, faLock,
    faArrowDown, faArrowsAltV, faArrowUp, faThumbsUp, faThumbsDown, faCopy, faCheck, faEdit,
    faComments, faPaperclip, faFile, faThumbtack, faTrash, faFaceSmile, faReply,
    // Annotation icons
    faMousePointer, faMapPin, faSquare, faCircle, faArrowRight, faPencilAlt, faFont,
    faSearchMinus, faSearchPlus, faExpandArrowsAlt, faUndo, faRedo, faKeyboard,
    faExclamationTriangle, faCommentDots, faStickyNote, faHandPaper, faDrawPolygon,
    faICursor, faCompressArrowsAlt, faLink, faPaperPlane, faSpinner,
    // Regular icons
    farSquare, farCircle
);

// Initialize dark mode from localStorage or system preference
const initializeDarkMode = () => {
    const isDark = localStorage.getItem('darkMode') === 'true' || 
                  (!('darkMode' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

// Run dark mode initialization
initializeDarkMode();

const appName = import.meta.env.VITE_APP_NAME || 'The Hub';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        
        // Register Font Awesome component globally
        app
            .use(plugin)
            .use(ZiggyVue, Ziggy)
            .use(i18n)
            .component('font-awesome-icon', FontAwesomeIcon);
        
        // Global error handler for unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            
            // Try to extract meaningful error message and type
            let errorMessage = 'An unexpected error occurred';
            let errorType = 'general';
            let errorDetails = null;
            
            if (event.reason?.response?.data) {
                const data = event.reason.response.data;
                errorMessage = data.message || errorMessage;
                errorType = data.type || errorType;
                errorDetails = data.details || null;
            } else if (event.reason?.message) {
                errorMessage = event.reason.message;
            }
            
            // Show styled error notification with type-specific styling
            showErrorNotification(errorMessage, errorType, errorDetails);
            event.preventDefault(); // Prevent the default browser error popup
        });
        
        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Function to show styled error notifications with type-specific styling
function showErrorNotification(message, type = 'general', details = null) {
    // Get type-specific styling
    const typeConfig = getErrorTypeConfig(type);
    
    // Create error notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 max-w-md ${typeConfig.bgClass} border ${typeConfig.borderClass} rounded-lg p-4 shadow-lg transition-all duration-300 transform translate-x-full`;
    
    const detailsHtml = details ? `
        <div class="mt-2 text-xs ${typeConfig.detailsClass} bg-opacity-50 dark:bg-gray-900 p-2 rounded">
            <pre class="whitespace-pre-wrap">${typeof details === 'object' ? JSON.stringify(details, null, 2) : details}</pre>
        </div>
    ` : '';
    
    notification.innerHTML = `
        <div class="flex items-start">
            <div class="flex-shrink-0">
                ${typeConfig.icon}
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium ${typeConfig.titleClass}">${typeConfig.title}</h3>
                <p class="mt-1 text-sm ${typeConfig.messageClass}">${message}</p>
                ${detailsHtml}
            </div>
            <div class="ml-4 flex-shrink-0">
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex ${typeConfig.closeClass} hover:opacity-75 focus:outline-none">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after duration based on type
    const duration = type === 'validation' ? 8000 : 5000; // Validation errors stay longer
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Get type-specific configuration for error notifications
function getErrorTypeConfig(type) {
    const configs = {
        authentication: {
            title: 'Authentication Required',
            bgClass: 'bg-yellow-50 dark:bg-yellow-900/20',
            borderClass: 'border-yellow-200 dark:border-yellow-800',
            titleClass: 'text-yellow-800 dark:text-yellow-200',
            messageClass: 'text-yellow-700 dark:text-yellow-300',
            detailsClass: 'text-yellow-600 dark:text-yellow-400 bg-yellow-100 dark:bg-yellow-900/40',
            closeClass: 'text-yellow-400 dark:text-yellow-300',
            icon: '<svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>'
        },
        authorization: {
            title: 'Access Denied',
            bgClass: 'bg-red-50 dark:bg-gray-900',
            borderClass: 'border-red-200 dark:border-red-800',
            titleClass: 'text-red-800 dark:text-red-200',
            messageClass: 'text-red-700 dark:text-red-300',
            detailsClass: 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/40',
            closeClass: 'text-red-400 dark:text-red-300',
            icon: '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" /></svg>'
        },
        validation: {
            title: 'Validation Error',
            bgClass: 'bg-orange-50 dark:bg-orange-900/20',
            borderClass: 'border-orange-200 dark:border-orange-800',
            titleClass: 'text-orange-800 dark:text-orange-200',
            messageClass: 'text-orange-700 dark:text-orange-300',
            detailsClass: 'text-orange-600 dark:text-orange-400 bg-orange-100 dark:bg-orange-900/40',
            closeClass: 'text-orange-400 dark:text-orange-300',
            icon: '<svg class="h-5 w-5 text-orange-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>'
        },
        database: {
            title: 'Database Error',
            bgClass: 'bg-red-50 dark:bg-gray-900',
            borderClass: 'border-red-200 dark:border-red-800',
            titleClass: 'text-red-800 dark:text-red-200',
            messageClass: 'text-red-700 dark:text-red-300',
            detailsClass: 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/40',
            closeClass: 'text-red-400 dark:text-red-300',
            icon: '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 104 0 2 2 0 00-4 0zm6 0a2 2 0 104 0 2 2 0 00-4 0z" clip-rule="evenodd" /></svg>'
        },
        not_found: {
            title: 'Not Found',
            bgClass: 'bg-gray-50 dark:bg-gray-800',
            borderClass: 'border-gray-200 dark:border-gray-700',
            titleClass: 'text-gray-800 dark:text-gray-200',
            messageClass: 'text-gray-700 dark:text-gray-300',
            detailsClass: 'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-900/40',
            closeClass: 'text-gray-400 dark:text-gray-300',
            icon: '<svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>'
        },
        rate_limit: {
            title: 'Rate Limited',
            bgClass: 'bg-purple-50 dark:bg-gray-900',
            borderClass: 'border-purple-200 dark:border-purple-800',
            titleClass: 'text-purple-800 dark:text-purple-200',
            messageClass: 'text-purple-700 dark:text-purple-300',
            detailsClass: 'text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/40',
            closeClass: 'text-purple-400 dark:text-purple-300',
            icon: '<svg class="h-5 w-5 text-purple-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" /></svg>'
        }
    };
    
    // Default to general error styling
    return configs[type] || {
        title: 'Error',
        bgClass: 'bg-red-50 dark:bg-gray-900',
        borderClass: 'border-red-200 dark:border-red-800',
        titleClass: 'text-red-800 dark:text-red-200',
        messageClass: 'text-red-700 dark:text-red-300',
        detailsClass: 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/40',
        closeClass: 'text-red-400 dark:text-red-300',
        icon: '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>'
    };
}

// Make the function globally available
window.showErrorNotification = showErrorNotification;