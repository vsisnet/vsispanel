<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base Path for User Files
    |--------------------------------------------------------------------------
    |
    | The base directory where user web files are stored.
    | Each user will have their own subdirectory: {base_path}/{username}/
    |
    */
    'base_path' => env('FILEMANAGER_BASE_PATH', '/var/www/vhosts'),

    /*
    |--------------------------------------------------------------------------
    | Web Root Directory Name
    |--------------------------------------------------------------------------
    |
    | The name of the public web directory within each domain folder.
    |
    */
    'web_root' => 'public_html',

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | Maximum file upload size in bytes.
    | Default: 100MB
    |
    */
    'max_upload_size' => env('FILEMANAGER_MAX_UPLOAD_SIZE', 104857600),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | List of allowed file extensions for upload.
    | Set to empty array to allow all extensions.
    |
    */
    'allowed_extensions' => [
        // Web files
        'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md',
        // PHP
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps',
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        // Archives
        'zip', 'tar', 'gz', 'rar', '7z', 'bz2',
        // Fonts
        'ttf', 'otf', 'woff', 'woff2', 'eot',
        // Config files
        'htaccess', 'env', 'ini', 'conf', 'yaml', 'yml',
        // Media
        'mp3', 'mp4', 'webm', 'ogg', 'wav', 'avi', 'mov',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked Extensions
    |--------------------------------------------------------------------------
    |
    | Extensions that are never allowed regardless of allowed_extensions.
    |
    */
    'blocked_extensions' => [
        'exe', 'sh', 'bat', 'cmd', 'com', 'msi', 'dll', 'so',
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
        'html', 'htm', 'css', 'js', 'json', 'xml', 'txt', 'md',
        'php', 'phtml',
        'htaccess', 'env', 'ini', 'conf', 'yaml', 'yml',
        'sql', 'log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Editable File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size that can be opened for editing in bytes.
    | Default: 2MB
    |
    */
    'max_editable_size' => env('FILEMANAGER_MAX_EDITABLE_SIZE', 2097152),

    /*
    |--------------------------------------------------------------------------
    | Hidden Files
    |--------------------------------------------------------------------------
    |
    | Files and directories that should be hidden from the file manager.
    |
    */
    'hidden_patterns' => [
        '.git',
        '.svn',
        '.DS_Store',
        'Thumbs.db',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Paths
    |--------------------------------------------------------------------------
    |
    | Paths that cannot be deleted or modified.
    | Relative to the user's base directory.
    |
    */
    'protected_paths' => [
        'logs',
        'tmp',
    ],
];
