<?php

declare(strict_types=1);

namespace App\Services;

class ServerInfoCollector
{
    protected SystemCommandExecutor $executor;

    public function __construct(SystemCommandExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Get OS information
     */
    public function getOsInfo(): array
    {
        $info = [
            'distro' => 'Unknown',
            'version' => 'Unknown',
            'kernel' => php_uname('r'),
            'arch' => php_uname('m'),
        ];

        // Try to read /etc/os-release
        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');

            if (preg_match('/^NAME="?([^"\n]+)"?/m', $content, $matches)) {
                $info['distro'] = $matches[1];
            }

            if (preg_match('/^VERSION="?([^"\n]+)"?/m', $content, $matches)) {
                $info['version'] = $matches[1];
            } elseif (preg_match('/^VERSION_ID="?([^"\n]+)"?/m', $content, $matches)) {
                $info['version'] = $matches[1];
            }

            if (preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $content, $matches)) {
                $info['pretty_name'] = $matches[1];
            }
        }

        return $info;
    }

    /**
     * Get CPU information
     */
    public function getCpuInfo(): array
    {
        $info = [
            'model' => 'Unknown',
            'cores' => 1,
            'threads' => 1,
            'usage' => 0,
            'load' => sys_getloadavg(),
        ];

        // Read /proc/cpuinfo
        if (file_exists('/proc/cpuinfo')) {
            $content = file_get_contents('/proc/cpuinfo');

            if (preg_match('/model name\s*:\s*(.+)/i', $content, $matches)) {
                $info['model'] = trim($matches[1]);
            }

            // Count processors
            preg_match_all('/^processor\s*:/mi', $content, $matches);
            $info['threads'] = count($matches[0]);

            // Physical cores (via cpu cores field)
            if (preg_match('/cpu cores\s*:\s*(\d+)/i', $content, $matches)) {
                $info['cores'] = (int) $matches[1];
            }

            // Get MHz
            if (preg_match('/cpu MHz\s*:\s*([\d.]+)/i', $content, $matches)) {
                $info['mhz'] = round((float) $matches[1]);
            }
        }

        // Get CPU usage (from /proc/stat)
        $info['usage'] = $this->getCpuUsage();

        // Calculate usage percentage from load average
        $loadAvg = $info['load'][0] ?? 0;
        $info['usage_percentage'] = min(100, round(($loadAvg / $info['threads']) * 100, 1));

        return $info;
    }

    /**
     * Get CPU usage percentage
     */
    protected function getCpuUsage(): float
    {
        if (!file_exists('/proc/stat')) {
            return 0;
        }

        // Read first stat line
        $stat1 = $this->readCpuStat();
        usleep(100000); // 100ms
        $stat2 = $this->readCpuStat();

        if (!$stat1 || !$stat2) {
            return 0;
        }

        $total1 = array_sum($stat1);
        $total2 = array_sum($stat2);
        $idle1 = $stat1[3] ?? 0;
        $idle2 = $stat2[3] ?? 0;

        $totalDiff = $total2 - $total1;
        $idleDiff = $idle2 - $idle1;

        if ($totalDiff == 0) {
            return 0;
        }

        return round((($totalDiff - $idleDiff) / $totalDiff) * 100, 1);
    }

    /**
     * Read CPU stat values
     */
    protected function readCpuStat(): ?array
    {
        $line = file('/proc/stat')[0] ?? '';
        if (preg_match('/^cpu\s+(.+)$/', $line, $matches)) {
            return array_map('intval', preg_split('/\s+/', trim($matches[1])));
        }
        return null;
    }

    /**
     * Get memory information
     */
    public function getMemoryInfo(): array
    {
        $info = [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'available' => 0,
            'buffers' => 0,
            'cached' => 0,
            'swap_total' => 0,
            'swap_used' => 0,
            'swap_free' => 0,
            'percentage' => 0,
            'swap_percentage' => 0,
        ];

        if (!file_exists('/proc/meminfo')) {
            return $info;
        }

        $content = file_get_contents('/proc/meminfo');

        $patterns = [
            'total' => '/MemTotal:\s+(\d+)/',
            'free' => '/MemFree:\s+(\d+)/',
            'available' => '/MemAvailable:\s+(\d+)/',
            'buffers' => '/Buffers:\s+(\d+)/',
            'cached' => '/Cached:\s+(\d+)/',
            'swap_total' => '/SwapTotal:\s+(\d+)/',
            'swap_free' => '/SwapFree:\s+(\d+)/',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $info[$key] = (int) $matches[1] * 1024; // Convert to bytes
            }
        }

        // Calculate used memory
        $info['used'] = $info['total'] - $info['available'];
        $info['swap_used'] = $info['swap_total'] - $info['swap_free'];

        // Calculate percentages
        if ($info['total'] > 0) {
            $info['percentage'] = round(($info['used'] / $info['total']) * 100, 1);
        }

        if ($info['swap_total'] > 0) {
            $info['swap_percentage'] = round(($info['swap_used'] / $info['swap_total']) * 100, 1);
        }

        // Add formatted values
        $info['total_formatted'] = $this->formatBytes($info['total']);
        $info['used_formatted'] = $this->formatBytes($info['used']);
        $info['free_formatted'] = $this->formatBytes($info['available']);
        $info['swap_total_formatted'] = $this->formatBytes($info['swap_total']);
        $info['swap_used_formatted'] = $this->formatBytes($info['swap_used']);

        return $info;
    }

    /**
     * Get disk information
     */
    public function getDiskInfo(): array
    {
        $disks = [];

        // Get disk usage via df command
        $result = $this->executor->execute('df', ['-B1', '--output=source,fstype,size,used,avail,pcent,target']);

        if (!$result->isSuccess()) {
            // Fallback to PHP functions for root
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;

            return [[
                'device' => '/',
                'mount' => '/',
                'fstype' => 'ext4',
                'total' => $total,
                'used' => $used,
                'free' => $free,
                'percentage' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
                'total_formatted' => $this->formatBytes($total),
                'used_formatted' => $this->formatBytes($used),
                'free_formatted' => $this->formatBytes($free),
            ]];
        }

        $lines = $result->getOutputLines();

        foreach ($lines as $line) {
            // Skip header
            if (str_starts_with($line, 'Filesystem')) {
                continue;
            }

            // Skip pseudo filesystems
            if (preg_match('/^(tmpfs|devtmpfs|udev|overlay|none|shm)/i', $line)) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 7) {
                $device = $parts[0];
                $fstype = $parts[1];
                $total = (int) $parts[2];
                $used = (int) $parts[3];
                $free = (int) $parts[4];
                $percentage = (float) rtrim($parts[5], '%');
                $mount = $parts[6];

                $disks[] = [
                    'device' => $device,
                    'mount' => $mount,
                    'fstype' => $fstype,
                    'total' => $total,
                    'used' => $used,
                    'free' => $free,
                    'percentage' => $percentage,
                    'total_formatted' => $this->formatBytes($total),
                    'used_formatted' => $this->formatBytes($used),
                    'free_formatted' => $this->formatBytes($free),
                ];
            }
        }

        return $disks;
    }

    /**
     * Get network interfaces information
     */
    public function getNetworkInfo(): array
    {
        $interfaces = [];

        $result = $this->executor->execute('ip', ['-j', 'addr', 'show']);

        if ($result->isSuccess()) {
            $data = json_decode($result->stdout, true);

            if (is_array($data)) {
                foreach ($data as $iface) {
                    $name = $iface['ifname'] ?? '';

                    // Skip loopback
                    if ($name === 'lo') {
                        continue;
                    }

                    $info = [
                        'name' => $name,
                        'state' => $iface['operstate'] ?? 'unknown',
                        'mac' => $iface['address'] ?? '',
                        'ipv4' => [],
                        'ipv6' => [],
                    ];

                    foreach ($iface['addr_info'] ?? [] as $addr) {
                        if ($addr['family'] === 'inet') {
                            $info['ipv4'][] = $addr['local'] . '/' . ($addr['prefixlen'] ?? 24);
                        } elseif ($addr['family'] === 'inet6') {
                            $info['ipv6'][] = $addr['local'] . '/' . ($addr['prefixlen'] ?? 64);
                        }
                    }

                    $interfaces[] = $info;
                }
            }
        }

        return $interfaces;
    }

    /**
     * Get system uptime
     */
    public function getUptime(): array
    {
        $uptime = [
            'seconds' => 0,
            'formatted' => '0 seconds',
            'boot_time' => null,
        ];

        if (file_exists('/proc/uptime')) {
            $content = file_get_contents('/proc/uptime');
            $seconds = (int) explode(' ', $content)[0];
            $uptime['seconds'] = $seconds;
            $uptime['formatted'] = $this->formatUptime($seconds);
            $uptime['boot_time'] = date('Y-m-d H:i:s', time() - $seconds);
        }

        return $uptime;
    }

    /**
     * Get load average
     */
    public function getLoadAverage(): array
    {
        $load = sys_getloadavg();

        return [
            '1min' => round($load[0] ?? 0, 2),
            '5min' => round($load[1] ?? 0, 2),
            '15min' => round($load[2] ?? 0, 2),
        ];
    }

    /**
     * Get all server info combined
     */
    public function getAllInfo(): array
    {
        return [
            'os' => $this->getOsInfo(),
            'cpu' => $this->getCpuInfo(),
            'memory' => $this->getMemoryInfo(),
            'disk' => $this->getDiskInfo(),
            'network' => $this->getNetworkInfo(),
            'uptime' => $this->getUptime(),
            'load' => $this->getLoadAverage(),
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
        ];
    }

    /**
     * Get running processes count
     */
    public function getProcessCount(): int
    {
        $result = $this->executor->execute('ps', ['aux', '--no-headers']);

        if ($result->isSuccess()) {
            return count($result->getOutputLines());
        }

        return 0;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format uptime seconds to readable string
     */
    protected function formatUptime(int $seconds): string
    {
        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }

        if ($hours > 0) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        }

        if ($minutes > 0 && $days === 0) {
            $parts[] = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        }

        return implode(', ', $parts) ?: '0 minutes';
    }
}
