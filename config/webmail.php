<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Webmail Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the webmail integration,
    | including Roundcube settings and SSO configuration.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable Webmail
    |--------------------------------------------------------------------------
    |
    | Set this to false to disable webmail access for all users.
    |
    */
    'enabled' => env('WEBMAIL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Webmail Provider
    |--------------------------------------------------------------------------
    |
    | The webmail provider to use. Currently only Roundcube is supported.
    |
    */
    'provider' => env('WEBMAIL_PROVIDER', 'roundcube'),

    /*
    |--------------------------------------------------------------------------
    | Webmail URL
    |--------------------------------------------------------------------------
    |
    | The URL where Roundcube webmail is accessible.
    |
    */
    'url' => env('WEBMAIL_URL', '/webmail'),

    /*
    |--------------------------------------------------------------------------
    | SSO Configuration
    |--------------------------------------------------------------------------
    |
    | Single Sign-On configuration for seamless webmail access.
    |
    */
    'sso' => [
        // Token time-to-live in seconds (default: 5 minutes)
        'token_ttl' => env('WEBMAIL_SSO_TOKEN_TTL', 300),

        // Validate IP address for token usage
        'validate_ip' => env('WEBMAIL_SSO_VALIDATE_IP', true),

        // Single use tokens (recommended for security)
        'single_use' => env('WEBMAIL_SSO_SINGLE_USE', true),

        // SSO plugin endpoint in Roundcube
        'plugin_endpoint' => env('WEBMAIL_SSO_PLUGIN_ENDPOINT', '?_task=login&_action=vsispanel_sso'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Server Configuration
    |--------------------------------------------------------------------------
    |
    | Mail server settings displayed to users for email client configuration.
    |
    */
    'mail_server' => [
        // Server hostname (uses domain's mail subdomain by default)
        'hostname' => env('MAIL_SERVER_HOSTNAME'),

        // IMAP settings
        'imap_port' => env('MAIL_IMAP_PORT', 993),
        'imap_security' => env('MAIL_IMAP_SECURITY', 'SSL/TLS'),

        // POP3 settings
        'pop3_port' => env('MAIL_POP3_PORT', 995),
        'pop3_security' => env('MAIL_POP3_SECURITY', 'SSL/TLS'),

        // SMTP settings
        'smtp_port' => env('MAIL_SMTP_PORT', 587),
        'smtp_security' => env('MAIL_SMTP_SECURITY', 'STARTTLS'),
        'smtp_port_ssl' => env('MAIL_SMTP_PORT_SSL', 465),
    ],

    /*
    |--------------------------------------------------------------------------
    | Roundcube Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to Roundcube webmail.
    |
    */
    'roundcube' => [
        // Installation path
        'path' => env('ROUNDCUBE_PATH', '/var/www/roundcube'),

        // Config file path
        'config_path' => env('ROUNDCUBE_CONFIG_PATH', '/var/www/roundcube/config'),

        // API key for SSO validation (shared secret between VSISPanel and Roundcube)
        'api_key' => env('ROUNDCUBE_API_KEY', ''),

        // Database configuration
        'db_driver' => env('ROUNDCUBE_DB_DRIVER', 'mysql'),
        'db_host' => env('ROUNDCUBE_DB_HOST', '127.0.0.1'),
        'db_database' => env('ROUNDCUBE_DB_DATABASE', 'roundcubemail'),
        'db_username' => env('ROUNDCUBE_DB_USERNAME', 'roundcube'),
        'db_password' => env('ROUNDCUBE_DB_PASSWORD', ''),

        // IMAP server for Roundcube
        'imap_host' => env('ROUNDCUBE_IMAP_HOST', 'ssl://localhost'),
        'imap_port' => env('ROUNDCUBE_IMAP_PORT', 993),

        // SMTP server for Roundcube
        'smtp_host' => env('ROUNDCUBE_SMTP_HOST', 'tls://localhost'),
        'smtp_port' => env('ROUNDCUBE_SMTP_PORT', 587),

        // Plugins to enable
        'plugins' => [
            'archive',
            'zipdownload',
            'managesieve',
            'password',
            'vsispanel_sso', // Custom SSO plugin
        ],

        // Default skin
        'skin' => env('ROUNDCUBE_SKIN', 'elastic'),

        // Session lifetime in minutes
        'session_lifetime' => env('ROUNDCUBE_SESSION_LIFETIME', 60),

        // Maximum upload size (matches PHP settings)
        'upload_max_filesize' => env('ROUNDCUBE_UPLOAD_MAX', '25M'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for email client auto-configuration (Autodiscover/Autoconfig).
    |
    */
    'autoconfig' => [
        // Enable autoconfig endpoints
        'enabled' => env('MAIL_AUTOCONFIG_ENABLED', true),

        // Autodiscover XML endpoint
        'autodiscover_path' => '/autodiscover/autodiscover.xml',

        // Mozilla autoconfig endpoint
        'autoconfig_path' => '/mail/config-v1.1.xml',
    ],
];
