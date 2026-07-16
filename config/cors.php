<?php

/*
 * Almanac public API CORS. The docs/demo surface (almanac.philiprehberger.com)
 * and the local Next dev server call the v1 API cross-origin. Auth is a
 * stateless Bearer token, never a cookie, so credentialed CORS is off.
 *
 * Set ALMANAC_CORS_ORIGINS (comma-separated) to override the allowlist for a
 * self-hosted deploy. The API vhost should not also emit its own
 * Access-Control-* headers — this is the single source of truth.
 */

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env(
    'ALMANAC_CORS_ORIGINS',
    'https://almanac.philiprehberger.com,http://localhost:3000',
)))));

return [
    'paths' => ['v1/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    'max_age' => 600,

    'supports_credentials' => false,
];
