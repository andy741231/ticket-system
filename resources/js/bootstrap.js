import axios from 'axios';
window.axios = axios;

// Set default headers for all axios requests
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
// Ensure Axios uses Laravel's XSRF cookie/header names
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// Add a request interceptor to include the CSRF token
window.axios.interceptors.request.use(config => {
    // Add the token for our own-origin requests (relative URLs or absolute same-origin URLs)
    let isSameOrigin = false;
    try {
        const reqUrl = new URL(config.url, window.location.origin);
        isSameOrigin = reqUrl.origin === window.location.origin;
    } catch (e) {
        // If URL constructor fails, treat as relative URL
        isSameOrigin = true;
    }

    if (isSameOrigin) {
        // Get the CSRF token from the meta tag
        const token = document.head.querySelector('meta[name="csrf-token"]');
        
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token.content;
        }
        
        // Add the Accept header for JSON responses
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
            if (!window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
