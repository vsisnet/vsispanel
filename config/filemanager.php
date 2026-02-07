<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    |
    | The base path where domain directories are stored.
    |
    */
    'base_path' => env('FILE_MANAGER_BASE_PATH', '/var/www/vhosts'),

    /*
    |--------------------------------------------------------------------------
    | Web Root Directory
    |--------------------------------------------------------------------------
    |
    | The name of the web root directory within each domain.
    |
    */
    'web_root' => env('FILE_MANAGER_WEB_ROOT', 'public_html'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | If set, only these extensions will be allowed for upload/create.
    | Leave empty to allow all except blocked extensions.
    |
    */
    'allowed_extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Blocked Extensions
    |--------------------------------------------------------------------------
    |
    | These extensions are never allowed for security reasons.
    |
    */
    'blocked_extensions' => [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Editable Extensions
    |--------------------------------------------------------------------------
    |
    | File extensions that can be edited in the browser.
    |
    */
    'editable_extensions' => [
        'txt', 'html', 'htm', 'css', 'scss', 'sass', 'less',
        'js', 'ts', 'jsx', 'tsx', 'vue', 'svelte',
        'json', 'xml', 'yaml', 'yml', 'toml',
        'php', 'phtml', 'inc',
        'py', 'rb', 'pl', 'sh', 'bash', 'zsh',
        'sql', 'md', 'markdown', 'rst', 'csv',
        'htaccess', 'htpasswd', 'conf', 'ini', 'env',
        'log', 'gitignore', 'dockerignore', 'editorconfig',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden Patterns
    |--------------------------------------------------------------------------
    |
    | Files/directories matching these patterns will be hidden.
    |
    */
    'hidden_patterns' => [
        '.git',
        '.svn',
        '.hg',
        '.DS_Store',
        'Thumbs.db',
        '*.bak',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Paths
    |--------------------------------------------------------------------------
    |
    | Paths that cannot be modified or deleted.
    |
    */
    'protected_paths' => [
        // Add paths relative to domain root
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Upload Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size for uploads in bytes.
    | Default: 100MB
    |
    */
    'max_upload_size' => env('FILE_MANAGER_MAX_UPLOAD', 104857600),

    /*
    |--------------------------------------------------------------------------
    | Max Editable Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size that can be edited in browser.
    | Default: 2MB
    |
    */
    'max_editable_size' => env('FILE_MANAGER_MAX_EDITABLE', 2097152),
];
