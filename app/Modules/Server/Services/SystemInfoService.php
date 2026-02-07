<?php

declare(strict_types=1);

namespace App\Modules\Server\Services;

class SystemInfoService
{
    /**
     * Get CPU usage metrics
     */
    public function getCpuUsage(): array
    {
        $cpuInfo = [];

        // Get CPU load averages
        $loadAvg = sys_getloadavg();
        $cpuInfo['load_1min'] = round($loadAvg[0], 2);
        $cpuInfo['load_5min'] = round($loadAvg[1], 2);
        $cpuInfo['load_15min'] = round($loadAvg[2], 2);

        // Get CPU cores count
        $cpuCores = (int) trim(shell_exec("nproc") ?? '1');
        $cpuInfo['cores'] = $cpuCores;

        // Calculate percentage (load / cores * 100, capped at 100)
        $cpuInfo['percentage'] = min(100, round(($loadAvg[0] / $cpuCores) * 100, 1));

        // Get CPU usage history from /proc/stat (simplified)
        $cpuInfo['history'] = $this->getCpuHistory();

        return $cpuInfo;
    }

    /**
     * Get memory usage metrics
     */
    public function getMemoryUsage(): array
    {
        $memInfo = [];

        if (file_exists('/proc/meminfo')) {
            $data = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $data, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $data, $available);
            preg_match('/Buffers:\s+(\d+)/', $data, $buffers);
            preg_match('/Cached:\s+(\d+)/', $data, $cached);
            preg_match('/SwapTotal:\s+(\d+)/', $data, $swapTotal);
            preg_match('/SwapFree:\s+(\d+)/', $data, $swapFree);

            $totalKb = (int) ($total[1] ?? 0);
            $availableKb = (int) ($available[1] ?? 0);
            $buffersKb = (int) ($buffers[1] ?? 0);
            $cachedKb = (int) ($cached[1] ?? 0);
            $swapTotalKb = (int) ($swapTotal[1] ?? 0);
            $swapFreeKb = (int) ($swapFree[1] ?? 0);

            $usedKb = $totalKb - $availableKb;

            $memInfo['total'] = $this->formatBytes($totalKb * 1024);
            $memInfo['used'] = $this->formatBytes($usedKb * 1024);
            $memInfo['free'] = $this->formatBytes($availableKb * 1024);
            $memInfo['buffers'] = $this->formatBytes($buffersKb * 1024);
            $memInfo['cached'] = $this->formatBytes($cachedKb * 1024);
            $memInfo['percentage'] = $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0;

            // Swap info
            $swapUsedKb = $swapTotalKb - $swapFreeKb;
            $memInfo['swap_total'] = $this->formatBytes($swapTotalKb * 1024);
            $memInfo['swap_used'] = $this->formatBytes($swapUsedKb * 1024);
            $memInfo['swap_percentage'] = $swapTotalKb > 0 ? round(($swapUsedKb / $swapTotalKb) * 100, 1) : 0;

            // Raw values for charts
            $memInfo['total_bytes'] = $totalKb * 1024;
            $memInfo['used_bytes'] = $usedKb * 1024;
        }

        // Memory history
        $memInfo['history'] = $this->getMemoryHistory();

        return $memInfo;
    }

    /**
     * Get disk usage metrics
     */
    public function getDiskUsage(): array
    {
        $disks = [];

        // Get main disk
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;

        $disks[] = [
            'mount' => '/',
            'total' => $this->formatBytes($totalSpace),
            'used' => $this->formatBytes($usedSpace),
            'free' => $this->formatBytes($freeSpace),
            'percentage' => round(($usedSpace / $totalSpace) * 100, 1),
            'total_bytes' => $totalSpace,
            'used_bytes' => $usedSpace,
        ];

        return $disks;
    }

    /**
     * Get system information
     */
    public function getSystemInfo(): array
    {
        $info = [];

        // OS Info
        $info['os'] = php_uname('s') . ' ' . php_uname('r');
        $info['hostname'] = gethostname() ?: 'unknown';
        $info['kernel'] = php_uname('r');
        $info['arch'] = php_uname('m');

        // Uptime
        if (file_exists('/proc/uptime')) {
            $uptime = (int) file_get_contents('/proc/uptime');
            $info['uptime'] = $this->formatUptime($uptime);
            $info['uptime_seconds'] = $uptime;
        }

        // PHP Version
        $info['php_version'] = PHP_VERSION;

        // MySQL Version
        try {
            $mysqlVersion = \DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
            $info['mysql_version'] = explode('-', $mysqlVersion)[0];
        } catch (\Exception $e) {
            $info['mysql_version'] = 'Unknown';
        }

        // Nginx Version
        $nginxVersion = trim(shell_exec("nginx -v 2>&1 | grep -oP 'nginx/\\K[0-9.]+'") ?? '');
        $info['nginx_version'] = $nginxVersion ?: 'Not installed';

        // Redis Version
        $redisVersion = trim(shell_exec("redis-server --version 2>/dev/null | grep -oP 'v=\\K[0-9.]+'") ?? '');
        $info['redis_version'] = $redisVersion ?: 'Not installed';

        // Server Time
        $info['server_time'] = now()->format('Y-m-d H:i:s');
        $info['timezone'] = config('app.timezone');

        return $info;
    }

    /**
     * Get services status
     */
    public function getServicesStatus(): array
    {
        $services = [
            'nginx' => $this->checkService('nginx'),
            'mysql' => $this->checkService('mysql'),
            'redis' => $this->checkService('redis-server'),
            'php-fpm' => $this->checkService('php8.3-fpm'),
        ];

        return $services;
    }

    /**
     * Check if a service is running
     */
    protected function checkService(string $name): array
    {
        $status = trim(shell_exec("systemctl is-active {$name} 2>/dev/null") ?? '');
        $isActive = $status === 'active';

        return [
            'name' => $name,
            'status' => $isActive ? 'running' : 'stopped',
            'active' => $isActive,
        ];
    }

    /**
     * Get CPU usage history (last 60 data points)
     */
    protected function getCpuHistory(): array
    {
        // Simulate CPU history for now (in production, use Redis/DB to store)
        $history = [];
        $baseLoad = sys_getloadavg()[0] ?? 0.5;
        $cores = (int) trim(shell_exec("nproc") ?? '1');

        for ($i = 59; $i >= 0; $i--) {
            $variation = (rand(-20, 20) / 100) * $baseLoad;
            $load = max(0, $baseLoad + $variation);
            $percentage = min(100, round(($load / $cores) * 100, 1));
            $history[] = [
                'time' => now()->subMinutes($i)->format('H:i'),
                'value' => $percentage,
            ];
        }

        return $history;
    }

    /**
     * Get memory usage history (last 60 data points)
     */
    protected function getMemoryHistory(): array
    {
        // Simulate memory history for now
        $history = [];
        $memInfo = [];

        if (file_exists('/proc/meminfo')) {
            $data = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $data, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $data, $available);

            $totalKb = (int) ($total[1] ?? 0);
            $availableKb = (int) ($available[1] ?? 0);
            $basePercentage = $totalKb > 0 ? (($totalKb - $availableKb) / $totalKb) * 100 : 50;
        } else {
            $basePercentage = 50;
        }

        for ($i = 59; $i >= 0; $i--) {
            $variation = rand(-5, 5);
            $percentage = max(0, min(100, round($basePercentage + $variation, 1)));
            $history[] = [
                'time' => now()->subMinutes($i)->format('H:i'),
                'value' => $percentage,
            ];
        }

        return $history;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format uptime seconds to readable string
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

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
