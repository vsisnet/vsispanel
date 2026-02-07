<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Web Server
    |--------------------------------------------------------------------------
    |
    | The default web server to use when creating new domains.
    |
    */

    'default' => env('WEBSERVER_DEFAULT', 'nginx'),

    /*
    |--------------------------------------------------------------------------
    | Default PHP Version
    |--------------------------------------------------------------------------
    |
    | The default PHP version to use when creating new domains.
    |
    */

    'default_php_version' => env('WEBSERVER_DEFAULT_PHP', '8.3'),

    /*
    |--------------------------------------------------------------------------
    | Nginx Settings
    |--------------------------------------------------------------------------
    */

    'nginx' => [
        // Sites available directory
        'sites_available' => env('NGINX_SITES_AVAILABLE', '/etc/nginx/sites-available'),

        // Sites enabled directory
        'sites_enabled' => env('NGINX_SITES_ENABLED', '/etc/nginx/sites-enabled'),

        // Config backup directory
        'config_backup_dir' => env('NGINX_BACKUP_DIR', '/var/vsispanel/backups/nginx'),

        // Nginx binary path
        'binary' => env('NGINX_BINARY', '/usr/sbin/nginx'),

        // User that nginx runs as
        'user' => env('NGINX_USER', 'www-data'),

        // Group that nginx runs as
        'group' => env('NGINX_GROUP', 'www-data'),

        // Default server tokens
        'server_tokens' => env('NGINX_SERVER_TOKENS', false),

        // Template paths
        'template_path' => resource_path('views/templates/nginx'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apache Settings
    |--------------------------------------------------------------------------
    */

    'apache' => [
        // Sites available directory
        'sites_available' => env('APACHE_SITES_AVAILABLE', '/etc/apache2/sites-available'),

        // Sites enabled directory
        'sites_enabled' => env('APACHE_SITES_ENABLED', '/etc/apache2/sites-enabled'),

        // Config backup directory
        'config_backup_dir' => env('APACHE_BACKUP_DIR', '/var/vsispanel/backups/apache'),

        // Apache binary path
        'binary' => env('APACHE_BINARY', '/usr/sbin/apache2'),

        // User that apache runs as
        'user' => env('APACHE_USER', 'www-data'),

        // Group that apache runs as
        'group' => env('APACHE_GROUP', 'www-data'),

        // Template paths
        'template_path' => resource_path('views/templates/apache'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP-FPM Settings
    |--------------------------------------------------------------------------
    */

    'php_fpm' => [
        // Base configuration directory
        'config_dir' => env('PHP_FPM_CONFIG_DIR', '/etc/php'),

        // Pool directory template (version-specific)
        'pool_dir' => env('PHP_FPM_POOL_DIR', '/etc/php/{version}/fpm/pool.d'),

        // Socket directory
        'socket_dir' => env('PHP_FPM_SOCKET_DIR', '/run/php'),

        // Available PHP versions
        'available_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],

        // Default pool settings
        'default_pool_settings' => [
            'pm' => 'dynamic',
            'max_children' => 5,
            'start_servers' => 2,
            'min_spare_servers' => 1,
            'max_spare_servers' => 3,
            'max_requests' => 500,
        ],

        // Disabled functions for security
        'disabled_functions' => [
            'exec', 'passthru', 'shell_exec', 'system',
            'proc_open', 'popen', 'pcntl_exec',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Settings
    |--------------------------------------------------------------------------
    */

    'ssl' => [
        // SSL certificate storage directory
        'cert_dir' => env('SSL_CERT_DIR', '/etc/vsispanel/ssl'),

        // Let's Encrypt certificate directory
        'letsencrypt_dir' => env('LETSENCRYPT_DIR', '/etc/letsencrypt/live'),

        // Default SSL protocols
        'protocols' => 'TLSv1.2 TLSv1.3',

        // Default SSL ciphers
        'ciphers' => 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384',

        // Enable HSTS
        'hsts_enabled' => true,
        'hsts_max_age' => 31536000, // 1 year

        // OCSP Stapling
        'ocsp_stapling' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */

    'security_headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        // Log format for access logs
        'access_log_format' => 'combined',

        // Default log retention (days)
        'retention_days' => 30,

        // Log rotation size
        'rotation_size' => '100M',
    ],

];
