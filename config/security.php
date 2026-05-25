<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxy IP Addresses
    |--------------------------------------------------------------------------
    |
    | Only requests coming from these proxy IP addresses will be allowed to
    | supply forwarded headers for client IP detection. Use a comma-separated
    | list via the TRUSTED_PROXY_IPS environment variable, or manage the
    | array directly here.
    |
    */
    'trusted_proxies' => array_filter(array_map('trim', explode(',', env('TRUSTED_PROXY_IPS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Check Endpoint Rate Limiting
    |--------------------------------------------------------------------------
    */
    'check' => [
        'attempts' => env('CHECK_RATE_LIMIT_ATTEMPTS', 10),
        'decay_seconds' => env('CHECK_RATE_LIMIT_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Configuration
    |--------------------------------------------------------------------------
    */
    'captcha' => [
        'ttl' => env('CAPTCHA_TTL_SECONDS', 300),
        'max_attempts' => env('CAPTCHA_MAX_ATTEMPTS', 3),
    ],
];

