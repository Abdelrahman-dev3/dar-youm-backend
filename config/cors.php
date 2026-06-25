<?php

$frontendUrl = rtrim(env('FRONTEND_URL', 'http://daryum-app.city2tec.com'), '/');
$backendUrl = rtrim(env('APP_URL', 'http://daryum-backend.city2tec.com'), '/');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter([
        // Previous server origins:
        // 'https://daryum.com',
        // 'https://www.daryum.com',
        // 'https://dev.daryum.com',
        // 'http://dev.daryum.com',
        $frontendUrl,
        $backendUrl,
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ])),
    'allowed_origins_patterns' => [
        '#^http://localhost(:[0-9]+)?$#',
        '#^http://127\.0\.0\.1(:[0-9]+)?$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
