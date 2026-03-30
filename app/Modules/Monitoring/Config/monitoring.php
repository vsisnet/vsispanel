<?php

return [
    // Metrics collection interval in seconds
    'collect_interval' => env('MONITORING_COLLECT_INTERVAL', 60),

    // Retention period for metrics in days
    'retention_days' => env('MONITORING_RETENTION_DAYS', 90),

    // Alert notification email
    'alert_email' => env('MONITORING_ALERT_EMAIL'),

    // Telegram Bot
    'telegram_bot_token' => env('MONITORING_TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('MONITORING_TELEGRAM_CHAT_ID'),

    // Slack Webhook
    'slack_webhook_url' => env('MONITORING_SLACK_WEBHOOK_URL'),

    // Discord Webhook
    'discord_webhook_url' => env('MONITORING_DISCORD_WEBHOOK_URL'),

    // OpenClaw AI Agent Webhook
    'openclaw_webhook_url' => env('MONITORING_OPENCLAW_WEBHOOK_URL'),

    // Server public IP (used in all alert notifications)
    'server_ip' => env('MONITORING_SERVER_IP'),

    // Managed services to monitor
    'services' => [
        'nginx' => 'nginx',
        'mysql' => 'mysql',
        'redis' => 'redis-server',
        'php-fpm' => 'php8.3-fpm',
        'vsispanel-horizon' => 'vsispanel-horizon',
        'vsispanel-reverb' => 'vsispanel-reverb',
        'postfix' => 'postfix',
        'dovecot' => 'dovecot',
        'named' => 'named',
        'fail2ban' => 'fail2ban',
    ],
];
