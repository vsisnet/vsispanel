<?php

declare(strict_types=1);

use App\Services\ServiceManager;
use App\Services\SystemCommandExecutor;
use App\Services\ServiceStatus;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => ['systemctl']]);
    config(['vsispanel.allowed_services' => [
        'nginx',
        'mysql',
        'redis-server',
        'php*-fpm',
        'apache2',
    ]]);
    config(['vsispanel.log_commands' => false]);

    $this->executor = new SystemCommandExecutor();
    $this->serviceManager = new ServiceManager($this->executor);
});

describe('ServiceManager', function () {

    it('checks if service is allowed', function () {
        expect($this->serviceManager->isAllowedService('nginx'))->toBeTrue();
        expect($this->serviceManager->isAllowedService('mysql'))->toBeTrue();
        expect($this->serviceManager->isAllowedService('not-allowed-service'))->toBeFalse();
    });

    it('supports wildcard matching for services', function () {
        expect($this->serviceManager->isAllowedService('php8.3-fpm'))->toBeTrue();
        expect($this->serviceManager->isAllowedService('php8.2-fpm'))->toBeTrue();
        expect($this->serviceManager->isAllowedService('php7.4-fpm'))->toBeTrue();
    });

    it('blocks operations on unauthorized services', function () {
        $result = $this->serviceManager->restart('dangerous-service');

        expect($result->isSuccess())->toBeFalse();
        expect($result->stderr)->toContain('not in the allowed services list');
    });

    it('returns allowed services list', function () {
        $services = $this->serviceManager->getAllowedServices();

        expect($services)->toBeArray();
        expect($services)->toContain('nginx');
        expect($services)->toContain('mysql');
    });

    it('can check if nginx is running', function () {
        // This may or may not be running depending on the system
        $isRunning = $this->serviceManager->isRunning('nginx');

        expect($isRunning)->toBeBool();
    });

    it('returns ServiceStatus for existing service', function () {
        $status = $this->serviceManager->status('nginx');

        expect($status)->toBeInstanceOf(ServiceStatus::class);
        expect($status->name)->toBe('nginx');
        expect($status->toArray())->toHaveKeys([
            'name',
            'is_running',
            'uptime',
            'pid',
            'memory_usage',
            'active_state',
            'sub_state',
            'load_state',
        ]);
    });

    it('returns not found status for non-existent service', function () {
        config(['vsispanel.allowed_services' => ['nonexistent-service-xyz']]);
        $manager = new ServiceManager($this->executor);

        $status = $manager->status('nonexistent-service-xyz');

        expect($status->loadState)->toBe('not-found');
        expect($status->isRunning)->toBeFalse();
    });

});

describe('ServiceStatus', function () {

    it('parses systemctl status output correctly', function () {
        $output = <<<'OUTPUT'
● nginx.service - A high performance web server
     Loaded: loaded (/lib/systemd/system/nginx.service; enabled; preset: enabled)
     Active: active (running) since Mon 2024-01-15 10:30:00 UTC; 2h 30min ago
    Main PID: 12345 (nginx)
      Memory: 5.2M
OUTPUT;

        $status = ServiceStatus::fromSystemctl('nginx', $output);

        expect($status->name)->toBe('nginx');
        expect($status->isRunning)->toBeTrue();
        expect($status->pid)->toBe(12345);
        expect($status->memoryUsage)->toBe('5.2M');
        expect($status->loadState)->toBe('loaded');
        expect($status->activeState)->toBe('active');
        expect($status->subState)->toBe('running');
    });

    it('handles inactive service status', function () {
        $output = <<<'OUTPUT'
● nginx.service - A high performance web server
     Loaded: loaded (/lib/systemd/system/nginx.service; disabled; preset: enabled)
     Active: inactive (dead)
OUTPUT;

        $status = ServiceStatus::fromSystemctl('nginx', $output);

        expect($status->isRunning)->toBeFalse();
        expect($status->activeState)->toBe('inactive');
        expect($status->subState)->toBe('dead');
    });

    it('creates not found status correctly', function () {
        $status = ServiceStatus::notFound('unknown-service');

        expect($status->name)->toBe('unknown-service');
        expect($status->isRunning)->toBeFalse();
        expect($status->loadState)->toBe('not-found');
        expect($status->activeState)->toBe('inactive');
    });

    it('converts to array correctly', function () {
        $status = new ServiceStatus(
            name: 'nginx',
            isRunning: true,
            uptime: '2h 30min',
            pid: 12345,
            memoryUsage: '5.2M',
            mainPid: '12345',
            activeState: 'active',
            subState: 'running',
            loadState: 'loaded'
        );

        $array = $status->toArray();

        expect($array['name'])->toBe('nginx');
        expect($array['is_running'])->toBeTrue();
        expect($array['pid'])->toBe(12345);
    });

});
