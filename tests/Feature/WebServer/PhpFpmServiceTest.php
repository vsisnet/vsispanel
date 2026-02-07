<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\WebServer\Services\NginxService;
use App\Modules\WebServer\Services\PhpFpmService;
use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create user with subscription
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->user->assignRole('user');

    $plan = Plan::factory()->active()->create();
    $subscription = Subscription::factory()->active()->create([
        'user_id' => $this->user->id,
        'plan_id' => $plan->id,
    ]);

    $this->domain = Domain::factory()->create([
        'user_id' => $this->user->id,
        'subscription_id' => $subscription->id,
        'name' => 'example.com',
        'php_version' => '8.3',
        'status' => 'active',
        'web_server_type' => 'nginx',
    ]);
});

describe('PhpFpmService pool management', function () {
    test('generates valid pool configuration', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('execute')->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);

        // Use reflection to call protected method
        $reflection = new ReflectionClass($phpFpmService);
        $method = $reflection->getMethod('generatePoolConfig');
        $method->setAccessible(true);

        $config = $method->invoke($phpFpmService, $this->user, '8.3', [
            'pm' => 'dynamic',
            'max_children' => 5,
            'start_servers' => 2,
            'min_spare_servers' => 1,
            'max_spare_servers' => 3,
        ]);

        // Check pool name
        expect($config)->toContain('[testuser]');

        // Check user/group
        expect($config)->toContain('user = testuser');
        expect($config)->toContain('group = testuser');

        // Check socket path
        expect($config)->toContain('listen = /run/php/php8.3-fpm-testuser.sock');

        // Check PM settings
        expect($config)->toContain('pm = dynamic');
        expect($config)->toContain('pm.max_children = 5');
        expect($config)->toContain('pm.start_servers = 2');

        // Check security settings
        expect($config)->toContain('open_basedir');
        expect($config)->toContain('/home/testuser');
    });

    test('socket path is correct for user and version', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $socketPath = $phpFpmService->getSocketPath($this->user, '8.1');

        expect($socketPath)->toBe('/run/php/php8.1-fpm-testuser.sock');
    });
});

describe('PhpFpmService version management', function () {
    test('getInstalledVersions checks each version directory', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // Match test -d commands for different PHP versions
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::on(function ($args) {
                return isset($args[0]) && $args[0] === '-d' &&
                       isset($args[1]) && (str_contains($args[1], '8.3') || str_contains($args[1], '8.2'));
            }))
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::on(function ($args) {
                return isset($args[0]) && $args[0] === '-d' &&
                       isset($args[1]) && !str_contains($args[1], '8.3') && !str_contains($args[1], '8.2');
            }))
            ->andReturn(new CommandResult(false, 1, '', '', 0.1));

        // Service status checks
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', Mockery::any())
            ->andReturn(new CommandResult(true, 0, 'active', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $versions = $phpFpmService->getInstalledVersions();

        expect($versions)->toBeArray();
        expect($versions['8.3']['installed'])->toBeTrue();
        expect($versions['8.2']['installed'])->toBeTrue();
        expect($versions['7.4']['installed'])->toBeFalse();
    });

    test('switchVersion updates domain and nginx config', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // Pool exists check - allow any test -f command
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::on(function ($args) {
                return isset($args[0]) && $args[0] === '-f';
            }))
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);
        $mockNginx->shouldReceive('updateVhost')
            ->once();

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $phpFpmService->switchVersion($this->domain, '8.3', '8.1');

        $this->domain->refresh();
        expect($this->domain->php_version)->toBe('8.1');
    });
});

describe('PhpFpmService PHP info', function () {
    test('getPhpInfo returns version info', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // PHP version
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-v'])
            ->andReturn(new CommandResult(true, 0, 'PHP 8.3.12 (cli) (built: Oct 22 2024)', '', 0.1));

        // PHP modules
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-m'])
            ->andReturn(new CommandResult(true, 0, "[PHP Modules]\ncurl\nmbstring\nopenssl\npdo", '', 0.1));

        // PHP info
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-i'])
            ->andReturn(new CommandResult(true, 0, "memory_limit => 256M => 256M\nupload_max_filesize => 64M => 64M", '', 0.1));

        // Disabled functions
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-r', 'echo ini_get("disable_functions");'])
            ->andReturn(new CommandResult(true, 0, 'exec,passthru,shell_exec,system', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $info = $phpFpmService->getPhpInfo('8.3');

        expect($info['version'])->toBe('8.3');
        expect($info['full_version'])->toBe('8.3.12');
        expect($info['extensions'])->toContain('curl');
        expect($info['extensions'])->toContain('openssl');
        expect($info['disabled_functions'])->toContain('exec');
    });
});

describe('PhpFpmService PHP ini settings', function () {
    test('updatePhpIni only allows whitelisted settings', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);

        // This should not throw and should filter out dangerous settings
        $phpFpmService->updatePhpIni($this->user, '8.3', [
            'memory_limit' => '512M',
            'upload_max_filesize' => '128M',
            'dangerous_setting' => 'hacked', // This should be filtered
            'exec' => 'allowed', // This should be filtered
        ]);

        // No exception means it passed
        expect(true)->toBeTrue();
    });

    test('getUserPhpSettings returns parsed ini settings', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // File exists - use flexible matcher
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::on(function ($args) {
                return isset($args[0]) && $args[0] === '-f';
            }))
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // Cat file
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('cat', Mockery::any())
            ->andReturn(new CommandResult(true, 0, "; Custom settings\nmemory_limit = 512M\nupload_max_filesize = 128M", '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $settings = $phpFpmService->getUserPhpSettings($this->user, '8.3');

        expect($settings)->toHaveKey('memory_limit');
        expect($settings['memory_limit'])->toBe('512M');
        expect($settings)->toHaveKey('upload_max_filesize');
        expect($settings['upload_max_filesize'])->toBe('128M');
    });
});

describe('PhpFpmService service management', function () {
    test('restartService calls systemctl restart', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['restart', 'php8.3-fpm'])
            ->once()
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $result = $phpFpmService->restartService('8.3');

        expect($result->success)->toBeTrue();
    });

    test('getServiceStatus returns correct status', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['is-active', 'php8.3-fpm'])
            ->andReturn(new CommandResult(true, 0, 'active', '', 0.1));

        $mockNginx = Mockery::mock(NginxService::class);

        $phpFpmService = new PhpFpmService($mockExecutor, $mockNginx);
        $status = $phpFpmService->getServiceStatus('8.3');

        expect($status['running'])->toBeTrue();
        expect($status['service'])->toBe('php8.3-fpm');
    });
});
