<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        env('PUBLIC_SITE_URL', 'https://oruidoscomputadores.pt'),
        'https://www.oruidoscomputadores.pt',
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
