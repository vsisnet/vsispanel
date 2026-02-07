<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SSL Certificate Storage Path
    |--------------------------------------------------------------------------
    |
    | The base path where SSL certificates will be stored.
    |
    */
    'storage_path' => env('SSL_STORAGE_PATH', '/etc/ssl/vsispanel'),

    /*
    |--------------------------------------------------------------------------
    | Let's Encrypt Configuration
    |--------------------------------------------------------------------------
    */
    'letsencrypt' => [
        'email' => env('LETSENCRYPT_EMAIL', 'admin@example.com'),
        'staging' => env('LETSENCRYPT_STAGING', false),
        'certbot_path' => env('CERTBOT_PATH', '/usr/bin/certbot'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Renewal Configuration
    |--------------------------------------------------------------------------
    */
    'auto_renewal' => [
        'enabled' => env('SSL_AUTO_RENEWAL_ENABLED', true),
        'days_before_expiry' => env('SSL_RENEWAL_DAYS_BEFORE', 30),
        'max_attempts' => env('SSL_RENEWAL_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry Warning Threshold
    |--------------------------------------------------------------------------
    |
    | Number of days before expiry to show warning.
    |
    */
    'expiry_warning_days' => env('SSL_EXPIRY_WARNING_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Nginx SSL Configuration
    |--------------------------------------------------------------------------
    */
    'nginx' => [
        'ssl_protocols' => 'TLSv1.2 TLSv1.3',
        'ssl_ciphers' => 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384',
        'ssl_prefer_server_ciphers' => true,
        'ssl_session_timeout' => '1d',
        'ssl_session_cache' => 'shared:SSL:50m',
        'ssl_stapling' => true,
        'ssl_stapling_verify' => true,
    ],
];
