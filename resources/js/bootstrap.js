import axios from 'axios';
window.axios = axios;

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
            if (!window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
