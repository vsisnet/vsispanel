<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class IpManagementService
{
    protected string $whitelistFile = '/etc/fail2ban/jail.d/whitelist.local';
    protected string $blacklistFile = '/var/vsispanel/config/ip-blacklist.conf';

    public function __construct(
        protected FirewallService $firewallService,
        protected Fail2BanService $fail2BanService
    ) {}

    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(string $ip): bool
    {
        $whitelist = $this->getWhitelist();

        if (in_array($ip, $whitelist)) {
            return true; // Already whitelisted
        }

        $whitelist[] = $ip;

        // Update Fail2Ban ignoreip
        $this->updateFail2BanIgnoreIp($whitelist);

        // Also allow in firewall
        $this->firewallService->allowIp($ip, "Whitelisted IP");

        Log::info('IP added to whitelist', ['ip' => $ip]);

        return true;
    }

    /**
     * Remove IP from whitelist
     */
    public function removeFromWhitelist(string $ip): bool
    {
        $whitelist = $this->getWhitelist();
        $whitelist = array_filter($whitelist, fn($item) => $item !== $ip);

        $this->updateFail2BanIgnoreIp($whitelist);

        Log::info('IP removed from whitelist', ['ip' => $ip]);

        return true;
    }

    /**
     * Get whitelist IPs
     */
    public function getWhitelist(): array
    {
        if (!file_exists($this->whitelistFile)) {
            return ['127.0.0.1', '::1'];
        }

        $content = file_get_contents($this->whitelistFile);

        if (preg_match('/ignoreip\s*=\s*(.+)/i', $content, $matches)) {
            $ips = preg_split('/[\s,]+/', trim($matches[1]));
            return array_filter($ips);
        }

        return ['127.0.0.1', '::1'];
    }

    /**
     * Update Fail2Ban ignore IP configuration
     */
    protected function updateFail2BanIgnoreIp(array $ips): void
    {
        $ipList = implode(' ', array_unique($ips));

        $content = <<<CONF
[DEFAULT]
ignoreip = {$ipList}
CONF;

        $dir = dirname($this->whitelistFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->whitelistFile, $content);

        // Reload Fail2Ban
        $this->fail2BanService->reload();
    }

    /**
     * Add IP to blacklist
     */
    public function addToBlacklist(string $ip, ?string $reason = null): bool
    {
        // Ban in Fail2Ban
        $this->fail2BanService->banIp($ip, 'sshd');

        // Block in firewall
        $this->firewallService->blockIp($ip, $reason ?? "Blacklisted IP: {$ip}");

        // Store in blacklist file
        $this->saveToBlacklistFile($ip, $reason);

        Log::info('IP added to blacklist', ['ip' => $ip, 'reason' => $reason]);

        return true;
    }

    /**
     * Remove IP from blacklist
     */
    public function removeFromBlacklist(string $ip): bool
    {
        // Unban from Fail2Ban
        $this->fail2BanService->unbanIp($ip);

        // Remove from blacklist file
        $this->removeFromBlacklistFile($ip);

        Log::info('IP removed from blacklist', ['ip' => $ip]);

        return true;
    }

    /**
     * Get blacklist IPs
     */
    public function getBlacklist(): array
    {
        $blacklist = [];

        // Get from Fail2Ban banned IPs
        $bannedIps = $this->fail2BanService->getBannedIps();

        foreach ($bannedIps as $banned) {
            $blacklist[$banned['ip']] = [
                'ip' => $banned['ip'],
                'source' => 'fail2ban',
                'jail' => $banned['jail'],
                'reason' => "Banned by Fail2Ban ({$banned['jail']})",
            ];
        }

        // Get from blacklist file
        $fileBlacklist = $this->getBlacklistFromFile();
        foreach ($fileBlacklist as $item) {
            if (!isset($blacklist[$item['ip']])) {
                $blacklist[$item['ip']] = $item;
            }
        }

        return array_values($blacklist);
    }

    /**
     * Check if IP is blacklisted
     */
    public function isBlacklisted(string $ip): bool
    {
        $blacklist = $this->getBlacklist();

        foreach ($blacklist as $item) {
            if ($item['ip'] === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is whitelisted
     */
    public function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->getWhitelist());
    }

    /**
     * Get IP information from external API
     */
    public function getIpInfo(string $ip): array
    {
        $cacheKey = "ip_info_{$ip}";

        return Cache::remember($cacheKey, 3600, function () use ($ip) {
            try {
                $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}");

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'ip' => $ip,
                        'country' => $data['country'] ?? 'Unknown',
                        'country_code' => $data['countryCode'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'city' => $data['city'] ?? '',
                        'isp' => $data['isp'] ?? 'Unknown',
                        'org' => $data['org'] ?? '',
                        'as' => $data['as'] ?? '',
                        'timezone' => $data['timezone'] ?? '',
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch IP info', ['ip' => $ip, 'error' => $e->getMessage()]);
            }

            return [
                'ip' => $ip,
                'country' => 'Unknown',
                'isp' => 'Unknown',
            ];
        });
    }

    /**
     * Save IP to blacklist file
     */
    protected function saveToBlacklistFile(string $ip, ?string $reason = null): void
    {
        $dir = dirname($this->blacklistFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entry = json_encode([
            'ip' => $ip,
            'reason' => $reason,
            'added_at' => now()->toIso8601String(),
        ]) . "\n";

        file_put_contents($this->blacklistFile, $entry, FILE_APPEND);
    }

    /**
     * Remove IP from blacklist file
     */
    protected function removeFromBlacklistFile(string $ip): void
    {
        if (!file_exists($this->blacklistFile)) {
            return;
        }

        $lines = file($this->blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newLines = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry && $entry['ip'] !== $ip) {
                $newLines[] = $line;
            }
        }

        file_put_contents($this->blacklistFile, implode("\n", $newLines) . "\n");
    }

    /**
     * Get blacklist from file
     */
    protected function getBlacklistFromFile(): array
    {
        if (!file_exists($this->blacklistFile)) {
            return [];
        }

        $lines = file($this->blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $blacklist = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $blacklist[] = [
                    'ip' => $entry['ip'],
                    'source' => 'manual',
                    'reason' => $entry['reason'] ?? 'Manually blacklisted',
                    'added_at' => $entry['added_at'] ?? null,
                ];
            }
        }

        return $blacklist;
    }
}
