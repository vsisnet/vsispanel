<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Backup Password
    |--------------------------------------------------------------------------
    |
    | The default password for encrypting backup repositories.
    | This should be changed in production environments.
    |
    */
    'default_password' => env('BACKUP_DEFAULT_PASSWORD', 'vsispanel_backup_secret'),

    /*
    |--------------------------------------------------------------------------
    | Restic Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the restic binary. Ensure restic is installed on the server.
    |
    */
    'restic_bin' => env('RESTIC_BIN', '/usr/bin/restic'),

    /*
    |--------------------------------------------------------------------------
    | Default Local Backup Path
    |--------------------------------------------------------------------------
    |
    | Default path for local backup storage.
    |
    */
    'default_local_path' => env('BACKUP_LOCAL_PATH', '/var/backups/vsispanel'),

    /*
    |--------------------------------------------------------------------------
    | Backup Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time for backup operations in seconds.
    |
    */
    'timeout' => env('BACKUP_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Default Retention Policy
    |--------------------------------------------------------------------------
    |
    | Default retention settings for backups.
    |
    */
    'default_retention' => [
        'keep_last' => 5,
        'keep_daily' => 7,
        'keep_weekly' => 4,
        'keep_monthly' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Exclude Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns to exclude from backups by default.
    |
    */
    'default_excludes' => [
        '*.log',
        '*.tmp',
        '.cache',
        'node_modules',
        '.git',
        'vendor',
        '*.sock',
        '.env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Paths by Type
    |--------------------------------------------------------------------------
    |
    | Default paths to backup based on backup type.
    |
    */
    'paths' => [
        'full' => [
            '/home',
            '/var/www',
            '/etc/nginx',
            '/etc/apache2',
            '/var/lib/mysql',
            '/etc/vsispanel',
        ],
        'files' => [
            '/home',
            '/var/www',
        ],
        'databases' => [
            '/var/lib/mysql',
        ],
        'emails' => [
            '/var/mail',
            '/var/vmail',
        ],
        'config' => [
            '/etc/nginx',
            '/etc/apache2',
            '/etc/postfix',
            '/etc/dovecot',
            '/etc/vsispanel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule Presets
    |--------------------------------------------------------------------------
    |
    | Preset schedule options for backup configurations.
    |
    */
    'schedules' => [
        'hourly' => '0 * * * *',
        'daily' => '0 2 * * *',
        'weekly' => '0 2 * * 0',
        'monthly' => '0 2 1 * *',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth is handled by the centralized OAuth Proxy Server.
    | The proxy manages all OAuth credentials and token exchange.
    | VSISPanel only needs the proxy URL and client API key.
    |
    */
    'oauth' => [
        // OAuth Proxy Server base URL
        'proxy_url' => env('OAUTH_PROXY_URL', 'https://app-oauth.vsis.net'),

        // OAuth Proxy Client ID (API key for this application)
        'proxy_client_id' => env('OAUTH_PROXY_CLIENT_ID', ''),

        // Supported providers (configured in OAuth Proxy Server)
        'supported_providers' => ['google', 'microsoft', 'dropbox'],
    ],
];
