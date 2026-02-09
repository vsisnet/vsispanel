<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class Fail2BanService
{
    protected string $configDir = '/etc/fail2ban';
    protected string $jailDir = '/etc/fail2ban/jail.d';

    /**
     * Get Fail2Ban status
     */
    public function getStatus(): array
    {
        $result = $this->runCommand('fail2ban-client status');

        if (!$result['success']) {
            return [
                'running' => false,
                'error' => $result['error'],
            ];
        }

        $status = [
            'running' => true,
            'jails' => [],
            'total_banned' => 0,
        ];

        // Parse output: "Number of jail: X\nJail list: sshd, ..."
        if (preg_match('/Number of jail:\s*(\d+)/', $result['output'], $matches)) {
            $status['jail_count'] = (int)$matches[1];
        }

        if (preg_match('/Jail list:\s*(.+)/i', $result['output'], $matches)) {
            $jailList = array_map('trim', explode(',', $matches[1]));
            $status['jails'] = array_filter($jailList);
        }

        return $status;
    }

    /**
     * Get all jails with their details
     */
    public function getJails(): array
    {
        $status = $this->getStatus();

        if (!$status['running']) {
            return [];
        }

        $jails = [];
        foreach ($status['jails'] as $jailName) {
            $jails[] = $this->getJailStatus($jailName);
        }

        return $jails;
    }

    /**
     * Get detailed status of a specific jail
     */
    public function getJailStatus(string $jail): array
    {
        $result = $this->runCommand("fail2ban-client status {$jail}");

        $status = [
            'name' => $jail,
            'enabled' => false,
            'currently_banned' => 0,
            'total_banned' => 0,
            'banned_ips' => [],
            'filter' => null,
            'actions' => [],
        ];

        if (!$result['success']) {
            return $status;
        }

        $status['enabled'] = true;
        $output = $result['output'];

        // Parse currently banned
        if (preg_match('/Currently banned:\s*(\d+)/i', $output, $matches)) {
            $status['currently_banned'] = (int)$matches[1];
        }

        // Parse total banned
        if (preg_match('/Total banned:\s*(\d+)/i', $output, $matches)) {
            $status['total_banned'] = (int)$matches[1];
        }

        // Parse banned IP list
        if (preg_match('/Banned IP list:\s*(.+)/i', $output, $matches)) {
            $ips = array_map('trim', explode(' ', trim($matches[1])));
            $status['banned_ips'] = array_filter($ips);
        }

        // Parse filter
        if (preg_match('/File list:\s*(.+)/i', $output, $matches)) {
            $status['filter'] = trim($matches[1]);
        }

        // Get jail configuration
        $config = $this->getJailConfig($jail);
        $status = array_merge($status, $config);

        return $status;
    }

    /**
     * Get jail configuration
     */
    public function getJailConfig(string $jail): array
    {
        $config = [
            'bantime' => 600,
            'findtime' => 600,
            'maxretry' => 5,
        ];

        // Try to get from fail2ban-client
        $bantimeResult = $this->runCommand("fail2ban-client get {$jail} bantime");
        if ($bantimeResult['success'] && preg_match('/(\d+)/', $bantimeResult['output'], $m)) {
            $config['bantime'] = (int)$m[1];
        }

        $findtimeResult = $this->runCommand("fail2ban-client get {$jail} findtime");
        if ($findtimeResult['success'] && preg_match('/(\d+)/', $findtimeResult['output'], $m)) {
            $config['findtime'] = (int)$m[1];
        }

        $maxretryResult = $this->runCommand("fail2ban-client get {$jail} maxretry");
        if ($maxretryResult['success'] && preg_match('/(\d+)/', $maxretryResult['output'], $m)) {
            $config['maxretry'] = (int)$m[1];
        }

        return $config;
    }

    /**
     * Get all banned IPs across all jails
     */
    public function getBannedIps(): array
    {
        $jails = $this->getJails();
        $bannedIps = [];

        foreach ($jails as $jail) {
            foreach ($jail['banned_ips'] as $ip) {
                $bannedIps[] = [
                    'ip' => $ip,
                    'jail' => $jail['name'],
                    'bantime' => $jail['bantime'] ?? 600,
                ];
            }
        }

        return $bannedIps;
    }

    /**
     * Ban an IP address
     */
    public function banIp(string $ip, string $jail = 'sshd'): array
    {
        $result = $this->runCommand("fail2ban-client set {$jail} banip {$ip}");

        if ($result['success']) {
            Log::info('IP banned via Fail2Ban', ['ip' => $ip, 'jail' => $jail]);
        }

        return $result;
    }

    /**
     * Unban an IP address
     */
    public function unbanIp(string $ip, ?string $jail = null): array
    {
        if ($jail) {
            $result = $this->runCommand("fail2ban-client set {$jail} unbanip {$ip}");
        } else {
            // Unban from all jails
            $result = $this->runCommand("fail2ban-client unban {$ip}");
        }

        if ($result['success']) {
            Log::info('IP unbanned from Fail2Ban', ['ip' => $ip, 'jail' => $jail ?? 'all']);
        }

        return $result;
    }

    /**
     * Set jail configuration
     */
    public function setJailConfig(string $jail, array $config): array
    {
        $results = [];

        if (isset($config['bantime'])) {
            $results['bantime'] = $this->runCommand(
                "fail2ban-client set {$jail} bantime {$config['bantime']}"
            );
        }

        if (isset($config['findtime'])) {
            $results['findtime'] = $this->runCommand(
                "fail2ban-client set {$jail} findtime {$config['findtime']}"
            );
        }

        if (isset($config['maxretry'])) {
            $results['maxretry'] = $this->runCommand(
                "fail2ban-client set {$jail} maxretry {$config['maxretry']}"
            );
        }

        Log::info('Fail2Ban jail config updated', ['jail' => $jail, 'config' => $config]);

        return $results;
    }

    /**
     * Create a custom jail configuration file
     */
    public function createCustomJail(string $name, array $config): bool
    {
        $template = $this->generateJailConfig($name, $config);
        $path = "{$this->jailDir}/{$name}.conf";

        if ($this->writeSystemFile($path, $template)) {
            Log::info('Custom Fail2Ban jail created', ['name' => $name]);
            $this->restart();
            return true;
        }

        return false;
    }

    /**
     * Generate jail configuration content
     */
    protected function generateJailConfig(string $name, array $config): string
    {
        $enabled = $config['enabled'] ?? true;
        $port = $config['port'] ?? 'http,https';
        $filter = $config['filter'] ?? $name;
        $logpath = $config['logpath'] ?? '/var/log/syslog';
        $maxretry = $config['maxretry'] ?? 5;
        $findtime = $config['findtime'] ?? 600;
        $bantime = $config['bantime'] ?? 3600;
        $action = $config['action'] ?? '%(action_mwl)s';

        return <<<CONF
[{$name}]
enabled = {$this->boolToString($enabled)}
port = {$port}
filter = {$filter}
logpath = {$logpath}
maxretry = {$maxretry}
findtime = {$findtime}
bantime = {$bantime}
action = {$action}
CONF;
    }

    /**
     * Convert boolean to string for config
     */
    protected function boolToString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Restart Fail2Ban service
     */
    public function restart(): array
    {
        $result = $this->runCommand('systemctl restart fail2ban');

        if ($result['success']) {
            Log::info('Fail2Ban restarted');
        }

        return $result;
    }

    /**
     * Reload Fail2Ban configuration
     */
    public function reload(): array
    {
        $result = $this->runCommand('fail2ban-client reload');

        if ($result['success']) {
            Log::info('Fail2Ban configuration reloaded');
        }

        return $result;
    }

    /**
     * Check if Fail2Ban is running
     */
    public function isRunning(): bool
    {
        $result = $this->runCommand('systemctl is-active fail2ban');
        return $result['success'] && str_contains($result['output'], 'active');
    }

    /**
     * Check if Fail2Ban is installed
     */
    public function isInstalled(): bool
    {
        $result = $this->runCommand('which fail2ban-client');
        return $result['success'] && !empty(trim($result['output']));
    }

    /**
     * Install Fail2Ban
     */
    public function install(): array
    {
        // Update package list
        $updateResult = $this->runCommand('apt-get update');
        if (!$updateResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to update package list: ' . $updateResult['error'],
            ];
        }

        // Install fail2ban
        $installResult = $this->runCommand('DEBIAN_FRONTEND=noninteractive apt-get install -y fail2ban');
        if (!$installResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to install Fail2Ban: ' . $installResult['error'],
            ];
        }

        // Enable and start service
        $this->runCommand('systemctl enable fail2ban');
        $this->runCommand('systemctl start fail2ban');

        // Create default local configuration
        $this->createDefaultConfig();

        Log::info('Fail2Ban installed successfully');

        return [
            'success' => true,
            'message' => 'Fail2Ban installed successfully',
        ];
    }

    /**
     * Create default local configuration
     */
    protected function createDefaultConfig(): void
    {
        $localConfig = <<<'CONF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
banaction = iptables-multiport
backend = systemd

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
CONF;

        $path = "{$this->configDir}/jail.local";
        if (!file_exists($path)) {
            $this->writeSystemFile($path, $localConfig);
        }
    }

    /**
     * Enable a jail
     */
    public function enableJail(string $jail): array
    {
        // Create or update jail config
        $configPath = "{$this->jailDir}/{$jail}.local";
        $config = "[{$jail}]\nenabled = true\n";

        if (!$this->writeSystemFile($configPath, $config)) {
            return [
                'success' => false,
                'error' => 'Failed to write jail config',
            ];
        }

        return $this->reload();
    }

    /**
     * Disable a jail
     */
    public function disableJail(string $jail): array
    {
        $configPath = "{$this->jailDir}/{$jail}.local";
        $config = "[{$jail}]\nenabled = false\n";

        if (!$this->writeSystemFile($configPath, $config)) {
            return [
                'success' => false,
                'error' => 'Failed to write jail config',
            ];
        }

        return $this->reload();
    }

    /**
     * Get available jails (including disabled)
     */
    public function getAvailableJails(): array
    {
        $jails = [];

        // Get from jail.conf
        $jailConf = "{$this->configDir}/jail.conf";
        if (file_exists($jailConf)) {
            $content = file_get_contents($jailConf);
            if (preg_match_all('/^\[([a-zA-Z0-9_-]+)\]$/m', $content, $matches)) {
                foreach ($matches[1] as $jail) {
                    if ($jail !== 'DEFAULT' && $jail !== 'INCLUDES') {
                        $jails[$jail] = ['name' => $jail, 'source' => 'system'];
                    }
                }
            }
        }

        // Get from jail.d
        $jailDFiles = glob("{$this->jailDir}/*.conf");
        foreach ($jailDFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match_all('/^\[([a-zA-Z0-9_-]+)\]$/m', $content, $matches)) {
                foreach ($matches[1] as $jail) {
                    if ($jail !== 'DEFAULT') {
                        $jails[$jail] = ['name' => $jail, 'source' => 'custom'];
                    }
                }
            }
        }

        return array_values($jails);
    }

    /**
     * Delete a custom jail
     */
    public function deleteJail(string $jail): array
    {
        $configPath = "{$this->jailDir}/{$jail}.conf";
        $localPath = "{$this->jailDir}/{$jail}.local";

        $this->deleteSystemFile($configPath);
        $this->deleteSystemFile($localPath);

        return $this->reload();
    }

    /**
     * Start Fail2Ban service
     */
    public function start(): array
    {
        return $this->runCommand('systemctl start fail2ban');
    }

    /**
     * Stop Fail2Ban service
     */
    public function stop(): array
    {
        return $this->runCommand('systemctl stop fail2ban');
    }

    /**
     * Get whitelisted IPs (ignoreip)
     */
    public function getWhitelist(): array
    {
        $whitelist = [];

        // Read from jail.local file
        $jailLocal = "{$this->configDir}/jail.local";
        if (file_exists($jailLocal)) {
            $content = file_get_contents($jailLocal);
            if (preg_match('/^ignoreip\s*=\s*(.+)$/m', $content, $matches)) {
                $ips = preg_split('/[\s,]+/', trim($matches[1]));
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (!empty($ip)) {
                        $whitelist[] = $ip;
                    }
                }
            }
        }

        // Also check via fail2ban-client
        $result = $this->runCommand('fail2ban-client get sshd ignoreip');
        if ($result['success'] && !empty(trim($result['output']))) {
            $output = trim($result['output']);
            // Parse the output which may be in format: These IP addresses/networks are ignored: ip1 ip2
            if (preg_match('/ignored:\s*(.+)$/i', $output, $matches)) {
                $ips = preg_split('/[\s,]+/', trim($matches[1]));
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (!empty($ip) && !in_array($ip, $whitelist)) {
                        $whitelist[] = $ip;
                    }
                }
            } else {
                // Direct IPs in output
                $ips = preg_split('/[\s,]+/', $output);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (!empty($ip) && !in_array($ip, $whitelist) && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $whitelist[] = $ip;
                    }
                }
            }
        }

        return array_unique($whitelist);
    }

    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(string $ip): array
    {
        $currentList = $this->getWhitelist();

        // Check if already exists
        if (in_array($ip, $currentList)) {
            return [
                'success' => false,
                'error' => 'IP is already whitelisted',
            ];
        }

        $currentList[] = $ip;
        return $this->saveWhitelist($currentList);
    }

    /**
     * Remove IP from whitelist
     */
    public function removeFromWhitelist(string $ip): array
    {
        $currentList = $this->getWhitelist();
        $newList = array_filter($currentList, fn($item) => $item !== $ip);

        return $this->saveWhitelist($newList);
    }

    /**
     * Save whitelist to jail.local
     */
    protected function saveWhitelist(array $ips): array
    {
        $jailLocal = "{$this->configDir}/jail.local";
        $ignoreipLine = 'ignoreip = 127.0.0.1/8 ::1 ' . implode(' ', $ips);

        if (file_exists($jailLocal)) {
            $content = file_get_contents($jailLocal);

            // Replace or add ignoreip line
            if (preg_match('/^ignoreip\s*=.+$/m', $content)) {
                $content = preg_replace('/^ignoreip\s*=.+$/m', $ignoreipLine, $content);
            } else {
                // Add after [DEFAULT] section
                if (preg_match('/^\[DEFAULT\]$/m', $content)) {
                    $content = preg_replace('/^\[DEFAULT\]$/m', "[DEFAULT]\n{$ignoreipLine}", $content);
                } else {
                    $content = "[DEFAULT]\n{$ignoreipLine}\n\n" . $content;
                }
            }
        } else {
            $content = "[DEFAULT]\n{$ignoreipLine}\n";
        }

        if (!$this->writeSystemFile($jailLocal, $content)) {
            return [
                'success' => false,
                'error' => 'Failed to write whitelist configuration',
            ];
        }

        // Reload fail2ban to apply changes
        $reloadResult = $this->reload();

        Log::info('Fail2Ban whitelist updated', ['ips' => $ips]);

        return $reloadResult;
    }

    /**
     * Get paginated banned IPs
     */
    public function getBannedIpsPaginated(int $page = 1, int $perPage = 20): array
    {
        $allBannedIps = $this->getBannedIps();
        $total = count($allBannedIps);

        $offset = ($page - 1) * $perPage;
        $items = array_slice($allBannedIps, $offset, $perPage);

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Write content to a system file using sudo
     */
    protected function writeSystemFile(string $path, string $content): bool
    {
        $dir = dirname($path);
        Process::timeout(5)->run("sudo mkdir -p " . escapeshellarg($dir));

        $result = Process::timeout(10)->input($content)->run(
            'sudo tee ' . escapeshellarg($path)
        );

        return $result->successful();
    }

    /**
     * Delete a system file using sudo
     */
    protected function deleteSystemFile(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        $result = Process::timeout(5)->run('sudo rm -f ' . escapeshellarg($path));
        return $result->successful();
    }

    /**
     * Run a shell command (with sudo for privileged commands)
     */
    protected function runCommand(string $command): array
    {
        if (!str_starts_with($command, 'sudo ') && !str_starts_with($command, 'which ')) {
            $command = "sudo {$command}";
        }

        Log::debug('Running Fail2Ban command', ['command' => $command]);

        try {
            $result = Process::timeout(30)->run($command);

            return [
                'success' => $result->successful(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Fail2Ban command failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }
}
