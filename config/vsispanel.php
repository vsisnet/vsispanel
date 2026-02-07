<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Command Execution Settings
    |--------------------------------------------------------------------------
    |
    | Configure the system command executor behavior, including timeout,
    | allowed commands, and logging preferences.
    |
    */

    // Default timeout for command execution (seconds)
    'command_timeout' => env('VSISPANEL_COMMAND_TIMEOUT', 30),

    // Whether to log all command executions
    'log_commands' => env('VSISPANEL_LOG_COMMANDS', true),

    // Whether sudo requires password (for production servers)
    'sudo_password_required' => env('VSISPANEL_SUDO_PASSWORD', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Commands Whitelist
    |--------------------------------------------------------------------------
    |
    | Only commands in this list can be executed. Wildcards (*) are supported.
    | Commands not in this list will be rejected for security.
    |
    */

    'allowed_commands' => [
        // Web servers
        'nginx',
        'apache2',
        'apachectl',

        // PHP
        'php',
        'php*-fpm',
        'php-fpm*',

        // MySQL/MariaDB
        'mysql',
        'mysqldump',
        'mysqlimport',
        'mariadb',
        'mariadb-dump',

        // System
        'systemctl',
        'service',
        'journalctl',

        // SSL/Certificates
        'certbot',
        'openssl',

        // Mail (Postfix/Dovecot)
        'postmap',
        'postqueue',
        'postsuper',
        'postconf',

        // DKIM
        'opendkim-genkey',
        'dig',

        // Firewall
        'ufw',
        'iptables',
        'fail2ban-client',

        // User management
        'useradd',
        'userdel',
        'usermod',
        'groupadd',
        'groupdel',
        'passwd',
        'chpasswd',

        // File operations
        'chown',
        'chmod',
        'mkdir',
        'rm',
        'cp',
        'mv',
        'ln',
        'touch',

        // Archive/Backup
        'tar',
        'gzip',
        'gunzip',
        'zip',
        'unzip',
        'restic',
        'borg',

        // Shell/Utilities (for piping commands)
        'bash',
        'cat',
        'zcat',
        'test',

        // DNS
        'named',
        'pdns_control',
        'pdnsutil',
        'rndc',

        // Mail
        'postfix',
        'postconf',
        'dovecot',
        'doveadm',
        'rspamd',

        // FTP
        'proftpd',
        'pure-ftpd',
        'pure-pw',

        // Monitoring & Info
        'df',
        'free',
        'uptime',
        'ps',
        'top',
        'htop',
        'netstat',
        'ss',
        'ip',
        'hostname',
        'uname',
        'cat',
        'head',
        'tail',
        'grep',
        'awk',
        'sed',
        'which',
        'whoami',
        'id',
        'date',
        'timedatectl',

        // Cron
        'crontab',

        // Git (for deployment)
        'git',

        // Composer & Node
        'composer',
        'npm',
        'node',
        'yarn',

        // Redis
        'redis-cli',
        'redis-server',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Services Whitelist
    |--------------------------------------------------------------------------
    |
    | Only services in this list can be managed via ServiceManager.
    | Wildcards (*) are supported for versioned services like php*-fpm.
    |
    */

    'allowed_services' => [
        // Web servers
        'nginx',
        'apache2',

        // PHP-FPM (multiple versions)
        'php*-fpm',
        'php-fpm',

        // Database
        'mysql',
        'mariadb',
        'postgresql',

        // Cache
        'redis-server',
        'redis',
        'memcached',

        // Mail
        'postfix',
        'dovecot',
        'opendkim',
        'spamassassin',
        'rspamd',

        // DNS
        'named',
        'bind9',
        'pdns',

        // FTP
        'proftpd',
        'pure-ftpd',
        'vsftpd',

        // Security
        'fail2ban',
        'ufw',

        // Queue/Workers
        'supervisor',
        'supervisord',

        // Monitoring
        'prometheus',
        'node_exporter',
        'grafana-server',

        // Cron
        'cron',
        'crond',

        // SSH
        'ssh',
        'sshd',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        // Default rate limit for API requests
        'default' => env('VSISPANEL_RATE_LIMIT_DEFAULT', 60),

        // Rate limit for authentication endpoints
        'auth' => env('VSISPANEL_RATE_LIMIT_AUTH', 10),

        // Rate limit for heavy operations
        'heavy' => env('VSISPANEL_RATE_LIMIT_HEAVY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Manager Settings
    |--------------------------------------------------------------------------
    */

    'file_manager' => [
        // Base path for user files (home directories)
        'base_path' => env('VSISPANEL_FILES_BASE_PATH', '/home'),

        // Maximum upload size (in bytes)
        'max_upload_size' => env('VSISPANEL_MAX_UPLOAD_SIZE', 100 * 1024 * 1024), // 100MB

        // Allowed file extensions for upload
        'allowed_extensions' => [
            'php', 'html', 'htm', 'css', 'js', 'json', 'xml',
            'txt', 'md', 'yaml', 'yml', 'ini', 'conf', 'cfg',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'tar', 'gz', 'rar', '7z',
            'mp3', 'mp4', 'avi', 'mov', 'webm',
            'ttf', 'woff', 'woff2', 'eot',
            'sql', 'sh', 'htaccess',
        ],

        // Forbidden file patterns
        'forbidden_patterns' => [
            '*.exe', '*.bat', '*.cmd', '*.com',
            '*.msi', '*.dll', '*.so',
            '.htpasswd', '.env', '*.pem', '*.key',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */

    'backup' => [
        // Backup storage path
        'path' => env('VSISPANEL_BACKUP_PATH', '/var/backups/vsispanel'),

        // Backup retention (days)
        'retention_days' => env('VSISPANEL_BACKUP_RETENTION', 30),

        // Maximum backup size (bytes)
        'max_size' => env('VSISPANEL_BACKUP_MAX_SIZE', 10 * 1024 * 1024 * 1024), // 10GB

        // Backup tool (restic or borg)
        'tool' => env('VSISPANEL_BACKUP_TOOL', 'restic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL/Certificate Settings
    |--------------------------------------------------------------------------
    */

    'ssl' => [
        // Let's Encrypt email
        'letsencrypt_email' => env('VSISPANEL_LETSENCRYPT_EMAIL', ''),

        // Certificate storage path
        'cert_path' => env('VSISPANEL_CERT_PATH', '/etc/letsencrypt/live'),

        // Auto-renewal enabled
        'auto_renew' => env('VSISPANEL_SSL_AUTO_RENEW', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Server Settings
    |--------------------------------------------------------------------------
    */

    'mail' => [
        // Virtual mail directories
        'mail_base_dir' => env('VSISPANEL_MAIL_BASE_DIR', '/var/mail/vhosts'),
        'virtual_domains_file' => env('VSISPANEL_VIRTUAL_DOMAINS_FILE', '/etc/postfix/virtual_domains'),
        'virtual_mailboxes_file' => env('VSISPANEL_VIRTUAL_MAILBOXES_FILE', '/etc/postfix/virtual_mailboxes'),
        'virtual_users_file' => env('VSISPANEL_VIRTUAL_USERS_FILE', '/etc/postfix/virtual_users'),
        'virtual_aliases_file' => env('VSISPANEL_VIRTUAL_ALIASES_FILE', '/etc/postfix/virtual_aliases'),

        // Dovecot settings
        'dovecot_passwd_file' => env('VSISPANEL_DOVECOT_PASSWD_FILE', '/etc/dovecot/passwd'),
        'dovecot_userdb_file' => env('VSISPANEL_DOVECOT_USERDB_FILE', '/etc/dovecot/users'),

        // Virtual mail user
        'vmail_uid' => env('VSISPANEL_VMAIL_UID', 5000),
        'vmail_gid' => env('VSISPANEL_VMAIL_GID', 5000),

        // DKIM settings
        'dkim_key_dir' => env('VSISPANEL_DKIM_KEY_DIR', '/etc/opendkim/keys'),
        'dkim_selector' => env('VSISPANEL_DKIM_SELECTOR', 'mail'),

        // Default quota (MB)
        'default_quota' => env('VSISPANEL_MAIL_DEFAULT_QUOTA', 1024),

        // Rspamd settings
        'rspamd_api_url' => env('VSISPANEL_RSPAMD_API_URL', 'http://127.0.0.1:11334'),
        'rspamd_api_password' => env('VSISPANEL_RSPAMD_API_PASSWORD', ''),
        'rspamd_config_path' => env('VSISPANEL_RSPAMD_CONFIG_PATH', '/etc/rspamd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Settings (PowerDNS)
    |--------------------------------------------------------------------------
    */

    'dns' => [
        // PowerDNS API settings
        'powerdns_api_url' => env('VSISPANEL_PDNS_API_URL', 'http://127.0.0.1:8081'),
        'powerdns_api_key' => env('VSISPANEL_PDNS_API_KEY', ''),
        'powerdns_server_id' => env('VSISPANEL_PDNS_SERVER_ID', 'localhost'),

        // Default nameservers
        'primary_ns' => env('VSISPANEL_PRIMARY_NS', 'ns1.example.com'),
        'secondary_ns' => env('VSISPANEL_SECONDARY_NS', 'ns2.example.com'),
        'admin_email' => env('VSISPANEL_DNS_ADMIN_EMAIL', 'admin@example.com'),

        // Default SOA values
        'default_ttl' => env('VSISPANEL_DNS_DEFAULT_TTL', 3600),
        'soa_refresh' => env('VSISPANEL_DNS_SOA_REFRESH', 10800),
        'soa_retry' => env('VSISPANEL_DNS_SOA_RETRY', 3600),
        'soa_expire' => env('VSISPANEL_DNS_SOA_EXPIRE', 604800),
        'soa_minimum' => env('VSISPANEL_DNS_SOA_MINIMUM', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | FTP Settings
    |--------------------------------------------------------------------------
    */

    'ftp' => [
        // FTP server type: 'proftpd' or 'pure-ftpd'
        'server' => env('VSISPANEL_FTP_SERVER', 'proftpd'),

        // ProFTPD settings
        'config_path' => env('VSISPANEL_FTP_CONFIG_PATH', '/etc/proftpd/proftpd.conf'),
        'users_db_path' => env('VSISPANEL_FTP_USERS_DB', '/etc/proftpd/ftpd.passwd'),

        // Pure-FTPd settings
        'pureftpd_db' => env('VSISPANEL_PUREFTPD_DB', '/etc/pure-ftpd/pureftpd.pdb'),
        'pureftpd_passwd' => env('VSISPANEL_PUREFTPD_PASSWD', '/etc/pure-ftpd/pureftpd.passwd'),

        // Default UID/GID for FTP users
        'default_uid' => env('VSISPANEL_FTP_UID', 33),
        'default_gid' => env('VSISPANEL_FTP_GID', 33),

        // Default quota (MB, 0 = unlimited)
        'default_quota' => env('VSISPANEL_FTP_DEFAULT_QUOTA', 0),

        // Default max connections
        'default_max_connections' => env('VSISPANEL_FTP_MAX_CONNECTIONS', 2),
        'default_max_connections_per_ip' => env('VSISPANEL_FTP_MAX_CONNECTIONS_PER_IP', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hosting Settings
    |--------------------------------------------------------------------------
    */

    'hosting' => [
        // Web root for hosted websites
        'web_root' => env('VSISPANEL_WEB_ROOT', '/var/www'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Paths
    |--------------------------------------------------------------------------
    */

    'paths' => [
        // Nginx configuration
        'nginx_conf' => env('VSISPANEL_NGINX_CONF', '/etc/nginx'),
        'nginx_sites' => env('VSISPANEL_NGINX_SITES', '/etc/nginx/sites-available'),
        'nginx_enabled' => env('VSISPANEL_NGINX_ENABLED', '/etc/nginx/sites-enabled'),

        // Apache configuration
        'apache_conf' => env('VSISPANEL_APACHE_CONF', '/etc/apache2'),
        'apache_sites' => env('VSISPANEL_APACHE_SITES', '/etc/apache2/sites-available'),

        // PHP configuration
        'php_conf' => env('VSISPANEL_PHP_CONF', '/etc/php'),

        // Web root
        'web_root' => env('VSISPANEL_WEB_ROOT', '/var/www'),

        // User home base
        'home_base' => env('VSISPANEL_HOME_BASE', '/home'),

        // Log directory
        'logs' => env('VSISPANEL_LOGS_PATH', '/var/log/vsispanel'),
    ],

];
