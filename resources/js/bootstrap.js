import axios from 'axios';
import { route } from '../../vendor/tightenco/ziggy';
window.axios = axios;

// Provide an asset() helper similar to Laravel's, using the request-derived app URL
// so that URLs respect the actual mount point (e.g. https://uhph.uh.edu/hub).
window.asset = (path) => {
    const appUrl = document.querySelector('meta[name="app-url"]')?.content;
    const baseUrl = appUrl || window.Ziggy?.url || window.location.origin;
    return `${baseUrl.replace(/\/$/, '')}/${(path || '').replace(/^\//, '')}`;
};

// Set default headers for all axios requests
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
// Ensure Axios uses Laravel's XSRF cookie/header names
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// Add a request interceptor for same-origin requests
// Do NOT inject X-CSRF-TOKEN from the meta tag. After logout, Laravel regenerates the token
// and the meta tag can become stale without a full page reload. Axios will automatically
// read the fresh XSRF-TOKEN cookie and send it as X-XSRF-TOKEN, which Laravel validates.
window.axios.interceptors.request.use(config => {
    let isSameOrigin = false;
    try {
        const reqUrl = new URL(config.url, window.location.origin);
        isSameOrigin = reqUrl.origin === window.location.origin;
        
        // Force HTTPS for same-origin requests if current page is HTTPS
        if (isSameOrigin && window.location.protocol === 'https:' && reqUrl.protocol === 'http:') {
            reqUrl.protocol = 'https:';
            config.url = reqUrl.toString();
        }
    } catch (e) {
        isSameOrigin = true;
    }

    if (isSameOrigin) {
        // Prefer JSON responses for XHR requests
        config.headers['Accept'] = 'application/json';
        // Ensure credentials are sent with the request
        config.withCredentials = true;
    }

    return config;
});

// Add a response interceptor to handle 401 responses
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            // Redirect to login if not already on the login page
            const loginPath = typeof window.Ziggy !== 'undefined'
                ? route('login', undefined, false, window.Ziggy)
                : '/login';
            if (!window.location.pathname.startsWith(loginPath)) {
                window.location.href = loginPath;
            }
        }
        return Promise.reject(error);
    }
);
