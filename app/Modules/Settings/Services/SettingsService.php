<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Monitoring\Services\AlertEvaluator;
use App\Modules\Settings\Models\SystemSetting;
use App\Services\SystemCommandExecutor;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SettingsService
{
    /**
     * Mapping from settings keys to existing config keys (env fallback).
     */
    private const CONFIG_MAP = [
        'notifications.email.recipients' => 'monitoring.alert_email',
        'notifications.telegram.bot_token' => 'monitoring.telegram_bot_token',
        'notifications.telegram.chat_id' => 'monitoring.telegram_chat_id',
        'notifications.slack.webhook_url' => 'monitoring.slack_webhook_url',
        'notifications.discord.webhook_url' => 'monitoring.discord_webhook_url',
    ];

    public function __construct(
        private SystemCommandExecutor $executor,
    ) {}

    /**
     * Get a setting value with env fallback.
     */
    public function get(string $dotKey, mixed $default = null): mixed
    {
        [$group, $key] = $this->parseDotKey($dotKey);

        $setting = SystemSetting::where('group', $group)
            ->where('key', $key)
            ->first();

        if ($setting && $setting->value !== null && $setting->value !== '') {
            return $setting->casted_value;
        }

        // Fallback to existing config/env values
        if (isset(self::CONFIG_MAP[$dotKey])) {
            return config(self::CONFIG_MAP[$dotKey], $default);
        }

        if ($dotKey === 'general.timezone') {
            return config('app.timezone', 'UTC');
        }

        return $default;
    }

    /**
     * Set a setting value (upsert).
     */
    public function set(string $dotKey, mixed $value, string $type = 'string'): void
    {
        [$group, $key] = $this->parseDotKey($dotKey);

        $storeValue = match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        SystemSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $storeValue, 'type' => $type],
        );
    }

    /**
     * Get all settings in a group as associative array.
     */
    public function getGroup(string $group): array
    {
        $settings = SystemSetting::group($group)->get();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->casted_value;
        }

        return $result;
    }

    /**
     * Get all settings grouped.
     */
    public function getAllGrouped(): array
    {
        $all = SystemSetting::all();
        $grouped = [];

        foreach ($all as $setting) {
            $grouped[$setting->group][$setting->key] = $setting->casted_value;
        }

        // Apply defaults for missing settings
        $defaults = $this->getDefaults();
        foreach ($defaults as $group => $keys) {
            foreach ($keys as $key => $info) {
                if (!isset($grouped[$group][$key])) {
                    $dotKey = "{$group}.{$key}";
                    $grouped[$group][$key] = $this->get($dotKey, $info['default']);
                }
            }
        }

        return $grouped;
    }

    /**
     * Batch update settings from validated request data.
     */
    public function updateBatch(array $data): void
    {
        foreach ($data as $dotKey => $value) {
            $type = $this->getTypeForKey($dotKey);
            $this->set($dotKey, $value, $type);
        }

        // Apply timezone change to system if modified
        if (isset($data['general.timezone'])) {
            $this->setSystemTimezone($data['general.timezone']);
        }

        // Re-apply notification config overrides
        $this->applyNotificationConfigOverrides();
    }

    /**
     * Get available timezones grouped by region.
     */
    public function getTimezones(): array
    {
        $timezones = DateTimeZone::listIdentifiers();
        $grouped = [];

        foreach ($timezones as $tz) {
            $parts = explode('/', $tz, 2);
            $region = $parts[0];
            $grouped[$region][] = $tz;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Get server time information via timedatectl.
     */
    public function getServerTime(): array
    {
        $result = $this->executor->executeAsRoot('timedatectl', ['status']);

        if (!$result->success) {
            return [
                'current_time' => now()->toIso8601String(),
                'timezone' => config('app.timezone', 'UTC'),
                'ntp_enabled' => false,
                'ntp_synced' => false,
            ];
        }

        $output = $result->stdout;
        $timezone = config('app.timezone', 'UTC');
        $ntpEnabled = false;
        $ntpSynced = false;

        if (preg_match('/Time zone:\s*(.+?)\s*\(/', $output, $m)) {
            $timezone = trim($m[1]);
        }
        if (preg_match('/NTP service:\s*(.+)/i', $output, $m)) {
            $ntpEnabled = strtolower(trim($m[1])) === 'active';
        }
        if (preg_match('/System clock synchronized:\s*(.+)/i', $output, $m)) {
            $ntpSynced = strtolower(trim($m[1])) === 'yes';
        }

        return [
            'current_time' => now()->toIso8601String(),
            'timezone' => $timezone,
            'ntp_enabled' => $ntpEnabled,
            'ntp_synced' => $ntpSynced,
        ];
    }

    /**
     * Sync server time via NTP.
     */
    public function syncTime(): bool
    {
        $result = $this->executor->executeAsRoot('timedatectl', ['set-ntp', 'true']);

        if ($result->success) {
            Log::channel('commands')->info('Server time NTP sync enabled');
        }

        return $result->success;
    }

    /**
     * Set system timezone via timedatectl.
     */
    public function setSystemTimezone(string $timezone): bool
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return false;
        }

        $result = $this->executor->executeAsRoot('timedatectl', ['set-timezone', $timezone]);

        if ($result->success) {
            // Also update Laravel config
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
            Log::channel('commands')->info('System timezone changed', ['timezone' => $timezone]);
        }

        return $result->success;
    }

    /**
     * Test a notification channel.
     */
    public function testNotification(string $channel): bool
    {
        $this->applyNotificationConfigOverrides();

        try {
            $evaluator = app(AlertEvaluator::class);

            return $evaluator->sendTestNotification($channel);
        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private const CACHE_KEY_NOTIFICATION_CONFIG = 'vsispanel:notification_config';
    private const CACHE_KEY_MAIL_CONFIG = 'vsispanel:mail_config';

    /**
     * Apply notification config overrides from DB to Laravel config.
     * Called at boot and after settings update.
     * Falls back to Redis cache when MySQL is unavailable.
     */
    public function applyNotificationConfigOverrides(): void
    {
        try {
            $configValues = [];

            foreach (self::CONFIG_MAP as $settingKey => $configKey) {
                [$group, $key] = $this->parseDotKey($settingKey);

                $setting = SystemSetting::where('group', $group)
                    ->where('key', $key)
                    ->first();

                if ($setting && $setting->value !== null && $setting->value !== '') {
                    config([$configKey => $setting->casted_value]);
                    $configValues[$configKey] = $setting->casted_value;
                }
            }

            // Cache to Redis on successful MySQL read
            Cache::store('redis')->put(self::CACHE_KEY_NOTIFICATION_CONFIG, json_encode($configValues));
        } catch (QueryException $e) {
            Log::warning('MySQL unavailable for notification config, using Redis cache', [
                'error' => $e->getMessage(),
            ]);
            $this->applyNotificationConfigFromRedis();
        }

        // Apply mail provider config overrides
        $this->applyMailConfigOverrides();
    }

    /**
     * Apply notification config from Redis cache (MySQL fallback).
     */
    private function applyNotificationConfigFromRedis(): void
    {
        $cached = Cache::store('redis')->get(self::CACHE_KEY_NOTIFICATION_CONFIG);
        if (! $cached) {
            Log::error('No cached notification config available in Redis');

            return;
        }

        $configValues = json_decode($cached, true);
        foreach ($configValues as $configKey => $value) {
            config([$configKey => $value]);
        }
    }

    /**
     * Apply mail provider configuration from DB to Laravel mail config.
     * Falls back to Redis cache when MySQL is unavailable.
     */
    private function applyMailConfigOverrides(): void
    {
        try {
            $this->applyMailConfigFromMySQL();
        } catch (QueryException $e) {
            Log::warning('MySQL unavailable for mail config, using Redis cache', [
                'error' => $e->getMessage(),
            ]);
            $this->applyMailConfigFromRedis();
        }
    }

    private function applyMailConfigFromMySQL(): void
    {
        $mailSettings = SystemSetting::group('mail')->get()->keyBy('key');

        if ($mailSettings->isEmpty()) {
            return;
        }

        // Cache raw mail settings to Redis
        $cacheData = $mailSettings->mapWithKeys(fn ($s) => [$s->key => $s->casted_value])->toArray();
        Cache::store('redis')->put(self::CACHE_KEY_MAIL_CONFIG, json_encode($cacheData));

        $this->applyMailConfig($cacheData);
    }

    private function applyMailConfigFromRedis(): void
    {
        $cached = Cache::store('redis')->get(self::CACHE_KEY_MAIL_CONFIG);
        if (! $cached) {
            return;
        }

        $this->applyMailConfig(json_decode($cached, true));
    }

    private function applyMailConfig(array $settings): void
    {
        $getValue = fn (string $key) => $settings[$key] ?? null;

        $provider = $getValue('provider');
        if (! $provider) {
            return;
        }

        // Set the default mailer based on provider
        $mailer = match ($provider) {
            'ses' => 'ses',
            'sendmail' => 'sendmail',
            'gmail_oauth' => 'gmail_oauth',
            default => 'smtp',
        };
        config(['mail.default' => $mailer]);

        // Set from address/name
        $fromAddress = $getValue('from_address');
        $fromName = $getValue('from_name');
        if ($fromAddress) {
            config(['mail.from.address' => $fromAddress]);
        }
        if ($fromName) {
            config(['mail.from.name' => $fromName]);
        }

        // Apply SMTP settings (used by smtp, gmail, outlook providers)
        if (in_array($provider, ['smtp', 'gmail', 'outlook'], true)) {
            $host = $getValue('smtp_host');
            $port = $getValue('smtp_port');
            $username = $getValue('smtp_username');
            $password = $getValue('smtp_password');
            $encryption = $getValue('smtp_encryption');

            if ($host) {
                config(['mail.mailers.smtp.host' => $host]);
            }
            if ($port) {
                config(['mail.mailers.smtp.port' => (int) $port]);
            }
            if ($username) {
                config(['mail.mailers.smtp.username' => $username]);
            }
            if ($password) {
                config(['mail.mailers.smtp.password' => $password]);
            }
            if ($encryption) {
                $scheme = match ($encryption) {
                    'ssl' => 'smtps',
                    'tls', 'none' => null,
                    default => null,
                };
                config(['mail.mailers.smtp.scheme' => $scheme]);
            }
        }

        // Apply SES settings
        if ($provider === 'ses') {
            $sesKey = $getValue('ses_key');
            $sesSecret = $getValue('ses_secret');
            $sesRegion = $getValue('ses_region');

            if ($sesKey) {
                config(['services.ses.key' => $sesKey]);
            }
            if ($sesSecret) {
                config(['services.ses.secret' => $sesSecret]);
            }
            if ($sesRegion) {
                config(['services.ses.region' => $sesRegion]);
            }
        }

        // Apply Gmail OAuth settings
        if ($provider === 'gmail_oauth') {
            $gmailToken = $getValue('gmail_token');
            $gmailEmail = $getValue('gmail_email');

            if ($gmailToken && $gmailEmail) {
                $tokenData = is_string($gmailToken) ? json_decode($gmailToken, true) : $gmailToken;
                config(['mail.mailers.gmail_oauth' => [
                    'transport' => 'gmail_oauth',
                    'username' => $gmailEmail,
                    'client_id' => $tokenData['client_id'] ?? '',
                    'client_secret' => $tokenData['client_secret'] ?? '',
                    'refresh_token' => $tokenData['refresh_token'] ?? '',
                ]]);
            }
        }
    }

    /**
     * Parse dot-notation key into [group, key].
     * "notifications.telegram.bot_token" → ["notifications", "telegram.bot_token"]
     */
    private function parseDotKey(string $dotKey): array
    {
        $pos = strpos($dotKey, '.');
        if ($pos === false) {
            return [$dotKey, ''];
        }

        return [substr($dotKey, 0, $pos), substr($dotKey, $pos + 1)];
    }

    /**
     * Get the type for a setting key.
     */
    private function getTypeForKey(string $dotKey): string
    {
        $booleanKeys = [
            'notifications.email.enabled',
            'notifications.telegram.enabled',
            'notifications.slack.enabled',
            'notifications.discord.enabled',
        ];

        if (in_array($dotKey, $booleanKeys, true)) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * Default settings with their types and default values.
     */
    private function getDefaults(): array
    {
        return [
            'general' => [
                'panel_name' => ['type' => 'string', 'default' => 'VSISPanel'],
                'timezone' => ['type' => 'string', 'default' => config('app.timezone', 'UTC')],
            ],
            'mail' => [
                'provider' => ['type' => 'string', 'default' => 'smtp'],
                'from_address' => ['type' => 'string', 'default' => config('mail.from.address', '')],
                'from_name' => ['type' => 'string', 'default' => config('mail.from.name', '')],
                'smtp_host' => ['type' => 'string', 'default' => config('mail.mailers.smtp.host', '')],
                'smtp_port' => ['type' => 'string', 'default' => (string) config('mail.mailers.smtp.port', '587')],
                'smtp_username' => ['type' => 'string', 'default' => config('mail.mailers.smtp.username', '')],
                'smtp_password' => ['type' => 'string', 'default' => ''],
                'smtp_encryption' => ['type' => 'string', 'default' => 'tls'],
                'ses_key' => ['type' => 'string', 'default' => ''],
                'ses_secret' => ['type' => 'string', 'default' => ''],
                'ses_region' => ['type' => 'string', 'default' => 'us-east-1'],
                'gmail_token' => ['type' => 'string', 'default' => ''],
                'gmail_email' => ['type' => 'string', 'default' => ''],
            ],
            'notifications' => [
                'email.enabled' => ['type' => 'boolean', 'default' => false],
                'email.recipients' => ['type' => 'string', 'default' => ''],
                'telegram.enabled' => ['type' => 'boolean', 'default' => false],
                'telegram.bot_token' => ['type' => 'string', 'default' => ''],
                'telegram.chat_id' => ['type' => 'string', 'default' => ''],
                'slack.enabled' => ['type' => 'boolean', 'default' => false],
                'slack.webhook_url' => ['type' => 'string', 'default' => ''],
                'discord.enabled' => ['type' => 'boolean', 'default' => false],
                'discord.webhook_url' => ['type' => 'string', 'default' => ''],
            ],
        ];
    }
}
