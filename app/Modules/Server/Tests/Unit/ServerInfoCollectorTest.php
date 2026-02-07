<?php

declare(strict_types=1);

use App\Services\ServerInfoCollector;
use App\Services\SystemCommandExecutor;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => [
        'df', 'free', 'uptime', 'ps', 'ip', 'hostname', 'uname', 'cat', 'which',
    ]]);
    config(['vsispanel.log_commands' => false]);

    $this->executor = new SystemCommandExecutor();
    $this->collector = new ServerInfoCollector($this->executor);
});

describe('ServerInfoCollector', function () {

    it('gets OS information', function () {
        $osInfo = $this->collector->getOsInfo();

        expect($osInfo)->toHaveKeys(['distro', 'version', 'kernel', 'arch']);
        expect($osInfo['kernel'])->not->toBeEmpty();
        expect($osInfo['arch'])->not->toBeEmpty();
    });

    it('gets CPU information', function () {
        $cpuInfo = $this->collector->getCpuInfo();

        expect($cpuInfo)->toHaveKeys(['model', 'cores', 'threads', 'usage', 'load']);
        expect($cpuInfo['cores'])->toBeGreaterThanOrEqual(1);
        expect($cpuInfo['threads'])->toBeGreaterThanOrEqual(1);
        expect($cpuInfo['load'])->toBeArray();
        expect($cpuInfo['load'])->toHaveCount(3);
    });

    it('gets memory information', function () {
        $memInfo = $this->collector->getMemoryInfo();

        expect($memInfo)->toHaveKeys([
            'total', 'used', 'free', 'percentage',
            'total_formatted', 'used_formatted', 'free_formatted',
        ]);
        expect($memInfo['total'])->toBeGreaterThan(0);
        expect($memInfo['percentage'])->toBeGreaterThanOrEqual(0);
        expect($memInfo['percentage'])->toBeLessThanOrEqual(100);
    });

    it('gets disk information', function () {
        $diskInfo = $this->collector->getDiskInfo();

        expect($diskInfo)->toBeArray();
        expect($diskInfo)->not->toBeEmpty();

        $rootDisk = $diskInfo[0];
        expect($rootDisk)->toHaveKeys([
            'device', 'mount', 'total', 'used', 'free', 'percentage',
            'total_formatted', 'used_formatted', 'free_formatted',
        ]);
        expect($rootDisk['total'])->toBeGreaterThan(0);
    });

    it('gets uptime information', function () {
        $uptime = $this->collector->getUptime();

        expect($uptime)->toHaveKeys(['seconds', 'formatted', 'boot_time']);
        expect($uptime['seconds'])->toBeGreaterThanOrEqual(0);
        expect($uptime['formatted'])->toBeString();
    });

    it('gets load average', function () {
        $load = $this->collector->getLoadAverage();

        expect($load)->toHaveKeys(['1min', '5min', '15min']);
        expect($load['1min'])->toBeGreaterThanOrEqual(0);
        expect($load['5min'])->toBeGreaterThanOrEqual(0);
        expect($load['15min'])->toBeGreaterThanOrEqual(0);
    });

    it('gets all server info combined', function () {
        $allInfo = $this->collector->getAllInfo();

        expect($allInfo)->toHaveKeys([
            'os', 'cpu', 'memory', 'disk', 'network', 'uptime', 'load',
            'hostname', 'php_version', 'server_time', 'timezone',
        ]);
        expect($allInfo['php_version'])->toBe(PHP_VERSION);
        expect($allInfo['hostname'])->not->toBeEmpty();
    });

    it('gets process count', function () {
        $count = $this->collector->getProcessCount();

        expect($count)->toBeInt();
        expect($count)->toBeGreaterThan(0);
    });

    it('formats bytes correctly', function () {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->collector);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        expect($method->invoke($this->collector, 0))->toBe('0 B');
        expect($method->invoke($this->collector, 1024))->toBe('1 KB');
        expect($method->invoke($this->collector, 1024 * 1024))->toBe('1 MB');
        expect($method->invoke($this->collector, 1024 * 1024 * 1024))->toBe('1 GB');
        expect($method->invoke($this->collector, 1536))->toBe('1.5 KB');
    });

    it('formats uptime correctly', function () {
        $reflection = new ReflectionClass($this->collector);
        $method = $reflection->getMethod('formatUptime');
        $method->setAccessible(true);

        expect($method->invoke($this->collector, 60))->toBe('1 minute');
        expect($method->invoke($this->collector, 3600))->toBe('1 hour');
        expect($method->invoke($this->collector, 86400))->toBe('1 day');
        expect($method->invoke($this->collector, 90061))->toBe('1 day, 1 hour');
    });

});
