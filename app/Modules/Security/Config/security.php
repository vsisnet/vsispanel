<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Security Score Cache
    |--------------------------------------------------------------------------
    |
    | How long to cache the security score in seconds.
    |
    */
    'score_cache_ttl' => env('SECURITY_SCORE_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | How many days to retain audit logs.
    |
    */
    'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Failed Login Threshold
    |--------------------------------------------------------------------------
    |
    | Number of failed login attempts before alerting.
    |
    */
    'failed_login_threshold' => env('FAILED_LOGIN_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Security Score Weights
    |--------------------------------------------------------------------------
    |
    | Weights for different security checks.
    |
    */
    'score_weights' => [
        'firewall' => 25,
        'fail2ban' => 15,
        'waf' => 15,
        'ssl' => 15,
        'ssh' => 10,
        'updates' => 5,
        'passwords' => 5,
        'backup' => 10,
    ],
];
