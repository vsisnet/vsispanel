<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Services;

use App\Modules\Monitoring\Models\ServerMetric;

class MetricsCollector
{
    /**
     * Collect all metrics and return as array.
     */
    public function collectAll(): array
    {
        return [
            'cpu' => $this->collectCpuUsage(),
            'memory' => $this->collectMemoryUsage(),
            'disk' => $this->collectDiskUsage(),
            'network' => $this->collectNetworkUsage(),
            'load' => $this->collectLoadAverage(),
            'processes' => $this->collectProcesses(),
            'services' => $this->collectServiceStatuses(),
            'uptime' => $this->collectUptime(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Collect and save metrics to database.
     */
    public function collectAndSave(): ServerMetric
    {
        $cpu = $this->collectCpuUsage();
        $memory = $this->collectMemoryUsage();
        $disk = $this->collectDiskUsage();
        $network = $this->collectNetworkUsage();
        $load = $this->collectLoadAverage();
        $processes = $this->collectProcesses();

        return ServerMetric::create([
            'cpu_usage' => $cpu['percentage'],
            'memory_used' => $memory['used_bytes'],
            'memory_total' => $memory['total_bytes'],
            'disk_usage' => $disk,
            'network_in' => $network['total_in'],
            'network_out' => $network['total_out'],
            'load_1m' => $load[0],
            'load_5m' => $load[1],
            'load_15m' => $load[2],
            'processes_total' => $processes['total'],
            'processes_running' => $processes['running'],
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get CPU usage percentage by parsing /proc/stat.
     */
    public function collectCpuUsage(): array
    {
        $cores = (int) trim(shell_exec("nproc") ?? '1');
        $loadAvg = sys_getloadavg();
        $percentage = min(100, round(($loadAvg[0] / max(1, $cores)) * 100, 1));

        // Try to get more accurate CPU usage from /proc/stat
        if (file_exists('/proc/stat')) {
            $stat1 = $this->parseProcStat();
            usleep(250000); // 250ms
            $stat2 = $this->parseProcStat();

            $idle1 = $stat1['idle'] + $stat1['iowait'];
            $idle2 = $stat2['idle'] + $stat2['iowait'];
            $total1 = array_sum($stat1);
            $total2 = array_sum($stat2);

            $totalDiff = $total2 - $total1;
            $idleDiff = $idle2 - $idle1;

            if ($totalDiff > 0) {
                $percentage = round((1 - ($idleDiff / $totalDiff)) * 100, 1);
            }
        }

        return [
            'percentage' => max(0, $percentage),
            'cores' => $cores,
            'load_1m' => round($loadAvg[0], 2),
            'load_5m' => round($loadAvg[1], 2),
            'load_15m' => round($loadAvg[2], 2),
        ];
    }

    /**
     * Get memory usage from /proc/meminfo.
     */
    public function collectMemoryUsage(): array
    {
        $result = [
            'total_bytes' => 0,
            'used_bytes' => 0,
            'free_bytes' => 0,
            'cached_bytes' => 0,
            'buffers_bytes' => 0,
            'percentage' => 0,
            'swap_total_bytes' => 0,
            'swap_used_bytes' => 0,
            'swap_percentage' => 0,
        ];

        if (!file_exists('/proc/meminfo')) {
            return $result;
        }

        $data = file_get_contents('/proc/meminfo');
        $values = [];
        foreach (['MemTotal', 'MemAvailable', 'Buffers', 'Cached', 'SwapTotal', 'SwapFree'] as $key) {
            preg_match("/{$key}:\s+(\d+)/", $data, $match);
            $values[$key] = ((int) ($match[1] ?? 0)) * 1024; // Convert KB to bytes
        }

        $result['total_bytes'] = $values['MemTotal'];
        $result['free_bytes'] = $values['MemAvailable'];
        $result['used_bytes'] = $values['MemTotal'] - $values['MemAvailable'];
        $result['cached_bytes'] = $values['Cached'];
        $result['buffers_bytes'] = $values['Buffers'];
        $result['percentage'] = $values['MemTotal'] > 0
            ? round(($result['used_bytes'] / $values['MemTotal']) * 100, 1)
            : 0;

        $result['swap_total_bytes'] = $values['SwapTotal'];
        $result['swap_used_bytes'] = $values['SwapTotal'] - $values['SwapFree'];
        $result['swap_percentage'] = $values['SwapTotal'] > 0
            ? round(($result['swap_used_bytes'] / $values['SwapTotal']) * 100, 1)
            : 0;

        return $result;
    }

    /**
     * Get disk usage for all mount points.
     */
    public function collectDiskUsage(): array
    {
        $disks = [];
        $output = shell_exec("df -B1 --output=target,size,used,avail,pcent -x tmpfs -x devtmpfs -x overlay 2>/dev/null");

        if (!$output) {
            return $disks;
        }

        $lines = explode("\n", trim($output));
        array_shift($lines); // Remove header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 5) {
                $disks[] = [
                    'mount' => $parts[0],
                    'total_bytes' => (int) $parts[1],
                    'used_bytes' => (int) $parts[2],
                    'free_bytes' => (int) $parts[3],
                    'percentage' => (float) rtrim($parts[4], '%'),
                ];
            }
        }

        return $disks;
    }

    /**
     * Get network usage from /proc/net/dev.
     */
    public function collectNetworkUsage(): array
    {
        $interfaces = [];
        $totalIn = 0;
        $totalOut = 0;

        if (!file_exists('/proc/net/dev')) {
            return ['interfaces' => [], 'total_in' => 0, 'total_out' => 0];
        }

        $lines = file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_shift($lines); // Header 1
        array_shift($lines); // Header 2

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $data] = explode(':', $line, 2);
            $name = trim($name);

            if ($name === 'lo') {
                continue;
            }

            $values = preg_split('/\s+/', trim($data));
            $bytesIn = (int) ($values[0] ?? 0);
            $bytesOut = (int) ($values[8] ?? 0);

            $interfaces[] = [
                'name' => $name,
                'bytes_in' => $bytesIn,
                'bytes_out' => $bytesOut,
            ];

            $totalIn += $bytesIn;
            $totalOut += $bytesOut;
        }

        return [
            'interfaces' => $interfaces,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
        ];
    }

    /**
     * Get load averages.
     */
    public function collectLoadAverage(): array
    {
        $load = sys_getloadavg();

        return [
            round($load[0], 2),
            round($load[1], 2),
            round($load[2], 2),
        ];
    }

    /**
     * Get process statistics.
     */
    public function collectProcesses(): array
    {
        $total = (int) trim(shell_exec("ps aux --no-heading 2>/dev/null | wc -l") ?? '0');
        $running = (int) trim(shell_exec("ps aux --no-heading 2>/dev/null | awk '{if(\$8 ~ /R/) count++} END {print count+0}'") ?? '0');
        $sleeping = (int) trim(shell_exec("ps aux --no-heading 2>/dev/null | awk '{if(\$8 ~ /S/) count++} END {print count+0}'") ?? '0');
        $zombie = (int) trim(shell_exec("ps aux --no-heading 2>/dev/null | awk '{if(\$8 ~ /Z/) count++} END {print count+0}'") ?? '0');

        return [
            'total' => $total,
            'running' => $running,
            'sleeping' => $sleeping,
            'zombie' => $zombie,
        ];
    }

    /**
     * Get top processes by CPU/Memory.
     */
    public function collectTopProcesses(string $sortBy = 'cpu', int $limit = 20): array
    {
        $sortField = $sortBy === 'memory' ? '--sort=-%mem' : '--sort=-%cpu';
        $output = shell_exec("ps aux {$sortField} --no-heading 2>/dev/null | head -{$limit}");

        if (!$output) {
            return [];
        }

        $processes = [];
        foreach (explode("\n", trim($output)) as $line) {
            $parts = preg_split('/\s+/', trim($line), 11);
            if (count($parts) >= 11) {
                $processes[] = [
                    'pid' => (int) $parts[1],
                    'user' => $parts[0],
                    'cpu' => (float) $parts[2],
                    'memory' => (float) $parts[3],
                    'vsz' => (int) $parts[4],
                    'rss' => (int) $parts[5],
                    'stat' => $parts[7],
                    'start' => $parts[8],
                    'time' => $parts[9],
                    'command' => $parts[10],
                ];
            }
        }

        return $processes;
    }

    /**
     * Get managed services statuses.
     */
    public function collectServiceStatuses(): array
    {
        $managed = [
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
            'ufw' => 'ufw',
        ];

        $services = [];
        foreach ($managed as $label => $name) {
            $isActive = trim(shell_exec("systemctl is-active {$name} 2>/dev/null") ?? '') === 'active';
            $isEnabled = trim(shell_exec("systemctl is-enabled {$name} 2>/dev/null") ?? '') === 'enabled';

            $uptime = null;
            if ($isActive) {
                $since = trim(shell_exec("systemctl show {$name} --property=ActiveEnterTimestamp --value 2>/dev/null") ?? '');
                if ($since) {
                    try {
                        $uptime = now()->diffForHumans(new \Carbon\Carbon($since), true);
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }

            $services[] = [
                'label' => $label,
                'name' => $name,
                'status' => $isActive ? 'running' : 'stopped',
                'enabled' => $isEnabled,
                'uptime' => $uptime,
            ];
        }

        return $services;
    }

    /**
     * Get system uptime.
     */
    public function collectUptime(): array
    {
        $seconds = 0;
        if (file_exists('/proc/uptime')) {
            $seconds = (int) ((float) file_get_contents('/proc/uptime'));
        }

        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        return [
            'seconds' => $seconds,
            'formatted' => trim("{$days}d {$hours}h {$minutes}m"),
        ];
    }

    /**
     * Parse /proc/stat for CPU times.
     */
    private function parseProcStat(): array
    {
        $line = explode("\n", file_get_contents('/proc/stat'))[0];
        $values = preg_split('/\s+/', $line);

        return [
            'user' => (int) ($values[1] ?? 0),
            'nice' => (int) ($values[2] ?? 0),
            'system' => (int) ($values[3] ?? 0),
            'idle' => (int) ($values[4] ?? 0),
            'iowait' => (int) ($values[5] ?? 0),
            'irq' => (int) ($values[6] ?? 0),
            'softirq' => (int) ($values[7] ?? 0),
        ];
    }
}
