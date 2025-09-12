<?php

return [
    'paths' => [
        'api/*',
        'login',
        'logout',
        'sanctum/csrf-cookie',
        'forgot-password',
        'reset-password',
        'register',
        'user/profile-information',
        'email/verification-notification',
        'user/password',
        'user/confirm-password',
        'email/verify/*',
        'two-factor-challenge',
        'user/two-factor-authentication',
        'user/confirmed-password-status',
        'user/confirmed-password',
        'user/other-browser-sessions',
        'user/profile-photo',
        'user/password/confirm',
        'user/two-factor-authentication',
        'user/two-factor-qr-code',
        'user/two-factor-secret-key',
        'user/two-factor-recovery-codes',
        'user/profile',
        'user/api-tokens/*',
        'build/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://uhph.uh.edu',
        'https://www.uhph.uh.edu',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Inertia',
        'X-Inertia-Version',
        'X-XSRF-TOKEN',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
