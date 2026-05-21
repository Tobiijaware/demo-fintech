<?php

$defaultOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://demo-backoffice-lac.vercel.app',
];

$fromEnv = env('CORS_ALLOWED_ORIGINS');
$extra = $fromEnv ? explode(',', (string) $fromEnv) : [];

return [

    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        'trim',
        [...$defaultOrigins, ...$extra],
    )))),

    /** Vercel preview deployments (e.g. *-team.vercel.app) */
    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\\.vercel\\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
