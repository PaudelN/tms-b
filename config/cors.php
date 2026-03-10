<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:3000'],

    'allowed_origins_patterns' => [],

    // config/cors.php
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-XSRF-TOKEN',  // ← Add this — Sanctum requires it
        'Accept',
    ],
    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
