<?php

return [
    // Previous server domains:
    // daryum.com,www.daryum.com,dev.daryum.com
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'daryum-app.city2tec.com,daryum-backend.city2tec.com,localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1')),
    'guard' => ['web'],
    'expiration' => null,
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
