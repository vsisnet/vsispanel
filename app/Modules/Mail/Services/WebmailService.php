<?php

declare(strict_types=1);

namespace App\Modules\Mail\Services;

use App\Modules\Mail\Models\MailAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WebmailService
{
    /**
     * Token prefix for cache keys.
     */
    private const TOKEN_PREFIX = 'webmail_sso_';

    /**
     * Generate SSO token for webmail access.
     */
    public function generateSsoToken(MailAccount $account): string
    {
        // Check if account is active
        if (!$account->isActive()) {
            throw new \InvalidArgumentException('Cannot generate webmail token for inactive account.');
        }

        // Generate a unique token
        $token = Str::random(64);

        // Store token data in cache
        $tokenData = [
            'account_id' => $account->id,
            'email' => $account->email,
            'created_at' => now()->toIso8601String(),
            'ip' => request()->ip(),
        ];

        $ttl = config('webmail.sso.token_ttl', 300); // 5 minutes default
        Cache::put(self::TOKEN_PREFIX . $token, $tokenData, $ttl);

        return $token;
    }

    /**
     * Validate SSO token and return account.
     */
    public function validateSsoToken(string $token): ?MailAccount
    {
        $tokenData = Cache::get(self::TOKEN_PREFIX . $token);

        if (!$tokenData) {
            return null;
        }

        // Validate IP if enabled
        if (config('webmail.sso.validate_ip', true)) {
            if ($tokenData['ip'] !== request()->ip()) {
                $this->revokeToken($token);
                return null;
            }
        }

        // Get account
        $account = MailAccount::find($tokenData['account_id']);

        if (!$account || !$account->isActive()) {
            $this->revokeToken($token);
            return null;
        }

        // Revoke token after use (single use)
        if (config('webmail.sso.single_use', true)) {
            $this->revokeToken($token);
        }

        return $account;
    }

    /**
     * Revoke SSO token.
     */
    public function revokeToken(string $token): void
    {
        Cache::forget(self::TOKEN_PREFIX . $token);
    }

    /**
     * Revoke all tokens for an account.
     */
    public function revokeAllTokens(MailAccount $account): void
    {
        // In a real implementation, we'd need to track all tokens per account
        // For now, tokens expire naturally
        // This is a placeholder for future enhancement
    }

    /**
     * Get webmail URL with SSO token.
     */
    public function getWebmailUrl(MailAccount $account): string
    {
        $token = $this->generateSsoToken($account);
        $baseUrl = config('webmail.url', '/webmail');

        // Return URL with SSO token
        return $baseUrl . '?_autologin=' . urlencode($token);
    }

    /**
     * Get webmail configuration info for user display.
     */
    public function getWebmailConfig(): array
    {
        return [
            'url' => config('webmail.url', '/webmail'),
            'provider' => config('webmail.provider', 'roundcube'),
            'enabled' => config('webmail.enabled', true),
        ];
    }

    /**
     * Get mail client configuration for user display.
     */
    public function getMailClientConfig(MailAccount $account): array
    {
        $domain = $account->mailDomain?->domain?->name ?? 'mail.example.com';
        $serverHostname = config('webmail.mail_server.hostname', 'mail.' . $domain);

        return [
            'email' => $account->email,
            'username' => $account->email,
            'incoming' => [
                'imap' => [
                    'server' => $serverHostname,
                    'port' => config('webmail.mail_server.imap_port', 993),
                    'security' => config('webmail.mail_server.imap_security', 'SSL/TLS'),
                ],
                'pop3' => [
                    'server' => $serverHostname,
                    'port' => config('webmail.mail_server.pop3_port', 995),
                    'security' => config('webmail.mail_server.pop3_security', 'SSL/TLS'),
                ],
            ],
            'outgoing' => [
                'smtp' => [
                    'server' => $serverHostname,
                    'port' => config('webmail.mail_server.smtp_port', 587),
                    'security' => config('webmail.mail_server.smtp_security', 'STARTTLS'),
                    'auth' => true,
                ],
            ],
        ];
    }

    /**
     * Check if webmail is enabled.
     */
    public function isEnabled(): bool
    {
        return config('webmail.enabled', true);
    }

    /**
     * Get Roundcube database configuration.
     */
    public function getRoundcubeDbConfig(): array
    {
        return [
            'driver' => config('webmail.roundcube.db_driver', 'mysql'),
            'host' => config('webmail.roundcube.db_host', '127.0.0.1'),
            'database' => config('webmail.roundcube.db_database', 'roundcubemail'),
            'username' => config('webmail.roundcube.db_username', 'roundcube'),
            'password' => config('webmail.roundcube.db_password', ''),
        ];
    }

    /**
     * Generate encrypted credentials for Roundcube auto-login.
     */
    public function generateEncryptedCredentials(MailAccount $account, string $password): string
    {
        $credentials = [
            'email' => $account->email,
            'password' => $password,
            'timestamp' => time(),
        ];

        return Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Decrypt credentials for Roundcube auto-login.
     */
    public function decryptCredentials(string $encrypted): ?array
    {
        try {
            $decrypted = Crypt::decryptString($encrypted);
            $credentials = json_decode($decrypted, true);

            // Check if credentials are expired (5 minutes)
            if (time() - ($credentials['timestamp'] ?? 0) > 300) {
                return null;
            }

            return $credentials;
        } catch (\Exception $e) {
            return null;
        }
    }
}
