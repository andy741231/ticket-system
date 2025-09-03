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
    faReply
} from '@fortawesome/free-solid-svg-icons';

// Add icons to the library
library.add(
    faBars, faXmark, faHouse, faTicket, faUsers, faUser, faGear, faRightFromBracket,
    faChevronLeft, faPlus, faSearch, faThLarge, faFilter, faUserTag, faTimes, faTicketAlt,
    faSortUp, faSortDown, faInbox, faSync, faPause, faCheckCircle, faTimesCircle, faLock,
    faArrowDown, faArrowsAltV, faArrowUp, faThumbsUp, faThumbsDown, faCopy, faCheck, faEdit,
    faComments, faPaperclip, faFile, faThumbtack, faTrash, faFaceSmile, faReply
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
        
        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});