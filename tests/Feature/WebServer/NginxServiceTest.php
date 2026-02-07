<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\WebServer\Services\NginxService;
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

describe('NginxService vhost generation', function () {
    test('generates valid HTTP vhost config', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $config = $nginxService->generateVhostConfig($this->domain);

        // Check server_name
        expect($config)->toContain('server_name example.com www.example.com');

        // Check document root
        expect($config)->toContain('root /home/testuser/domains/example.com/public_html');

        // Check PHP socket
        expect($config)->toContain('fastcgi_pass unix:/run/php/php8.3-fpm-testuser.sock');

        // Check logs
        expect($config)->toContain('access_log /home/testuser/domains/example.com/logs/access.log');
        expect($config)->toContain('error_log /home/testuser/domains/example.com/logs/error.log');

        // Check security headers
        expect($config)->toContain('X-Frame-Options');
        expect($config)->toContain('X-Content-Type-Options');

        // Check PHP location block
        expect($config)->toContain('location ~ \.php$');
        expect($config)->toContain('fastcgi_param SCRIPT_FILENAME');

        // Check Let's Encrypt ACME challenge location
        expect($config)->toContain('/.well-known/acme-challenge/');
    });

    test('generates valid SSL vhost config', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $config = $nginxService->generateSslVhostConfig(
            $this->domain,
            '/etc/letsencrypt/live/example.com/fullchain.pem',
            '/etc/letsencrypt/live/example.com/privkey.pem'
        );

        // Check SSL listen directive
        expect($config)->toContain('listen 443 ssl http2');

        // Check SSL certificate paths
        expect($config)->toContain('ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem');
        expect($config)->toContain('ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem');

        // Check SSL protocols
        expect($config)->toContain('ssl_protocols TLSv1.2 TLSv1.3');

        // Check HSTS header
        expect($config)->toContain('Strict-Transport-Security');

        // Check OCSP stapling
        expect($config)->toContain('ssl_stapling on');

        // Check HTTPS param for PHP
        expect($config)->toContain('fastcgi_param HTTPS on');
    });

    test('generates HTTP to HTTPS redirect config', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $config = $nginxService->generateRedirectConfig($this->domain);

        // Check listen directive
        expect($config)->toContain('listen 80');

        // Check server_name
        expect($config)->toContain('server_name example.com www.example.com');

        // Check redirect
        expect($config)->toContain('return 301 https://$host$request_uri');

        // Check ACME challenge exception
        expect($config)->toContain('/.well-known/acme-challenge/');
    });

    test('config contains correct PHP version socket', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // Test with PHP 8.1
        $this->domain->update(['php_version' => '8.1']);
        $this->domain->refresh();

        $nginxService = new NginxService($mockExecutor);
        $config = $nginxService->generateVhostConfig($this->domain);

        expect($config)->toContain('fastcgi_pass unix:/run/php/php8.1-fpm-testuser.sock');
    });
});

describe('NginxService operations', function () {
    test('testConfig calls nginx -t', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('nginx', ['-t'])
            ->once()
            ->andReturn(new CommandResult(true, 0, '', 'nginx: configuration file /etc/nginx/nginx.conf test is successful', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $result = $nginxService->testConfig();

        expect($result)->toBeTrue();
    });

    test('testConfig returns false on failure', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('nginx', ['-t'])
            ->once()
            ->andReturn(new CommandResult(false, 1, '', 'nginx: configuration file has errors', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $result = $nginxService->testConfig();

        expect($result)->toBeFalse();
    });

    test('reload calls systemctl reload nginx', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['reload', 'nginx'])
            ->once()
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $result = $nginxService->reload();

        expect($result->success)->toBeTrue();
    });

    test('getAccessLog returns log content', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('execute')
            ->with('test', ['-e', $this->domain->access_log_path])
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('tail', ['-n', '100', $this->domain->access_log_path])
            ->andReturn(new CommandResult(true, 0, '192.168.1.1 - - [04/Feb/2026:10:00:00 +0700] "GET / HTTP/1.1" 200 1234', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $log = $nginxService->getAccessLog($this->domain, 100);

        expect($log)->toContain('192.168.1.1');
        expect($log)->toContain('GET / HTTP/1.1');
    });

    test('getErrorLog returns error log content', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('execute')
            ->with('test', ['-e', $this->domain->error_log_path])
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('tail', ['-n', '50', $this->domain->error_log_path])
            ->andReturn(new CommandResult(true, 0, '[error] FastCGI sent in stderr', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $log = $nginxService->getErrorLog($this->domain, 50);

        expect($log)->toContain('[error]');
    });
});

describe('NginxService backup and rollback', function () {
    test('createVhost backs up and rolls back on test failure', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // Allow directory creation
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('mkdir', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // Allow file operations
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('cp', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('chmod', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('chown', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // Symlink creation
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('ln', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // File exists check
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::any())
            ->andReturn(new CommandResult(false, 1, '', '', 0.1));

        // nginx -t fails
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('nginx', ['-t'])
            ->andReturn(new CommandResult(false, 1, '', 'syntax error', 0.1));

        // Rollback - file removal
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('rm', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);

        expect(fn() => $nginxService->createVhost($this->domain))
            ->toThrow(RuntimeException::class, 'Nginx configuration test failed');
    });
});

describe('NginxService SSL operations', function () {
    test('enableSsl updates domain and config', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // File operations
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('mkdir', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('cp', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('chmod', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('chown', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // File exists check - no existing config
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::any())
            ->andReturn(new CommandResult(false, 1, '', '', 0.1));

        // nginx -t succeeds
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('nginx', ['-t'])
            ->andReturn(new CommandResult(true, 0, '', 'test is successful', 0.1));

        // nginx reload
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['reload', 'nginx'])
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);

        $nginxService->enableSsl(
            $this->domain,
            '/etc/letsencrypt/live/example.com/fullchain.pem',
            '/etc/letsencrypt/live/example.com/privkey.pem'
        );

        // Check domain was updated
        $this->domain->refresh();
        expect($this->domain->ssl_enabled)->toBeTrue();
    });

    test('disableSsl removes SSL from domain', function () {
        // First enable SSL
        $this->domain->update(['ssl_enabled' => true]);

        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        // File operations
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('mkdir', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('cp', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('chmod', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('chown', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        // File exists check
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::any())
            ->andReturn(new CommandResult(false, 1, '', '', 0.1));

        // nginx -t succeeds
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('nginx', ['-t'])
            ->andReturn(new CommandResult(true, 0, '', 'test is successful', 0.1));

        // nginx reload
        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['reload', 'nginx'])
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $nginxService->disableSsl($this->domain);

        // Check domain was updated
        $this->domain->refresh();
        expect($this->domain->ssl_enabled)->toBeFalse();
    });
});

describe('NginxService status', function () {
    test('getStatus returns nginx status and version', function () {
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);

        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['is-active', 'nginx'])
            ->andReturn(new CommandResult(true, 0, 'active', '', 0.1));

        $mockExecutor->shouldReceive('executeAsRoot')
            ->with('nginx', ['-v'])
            ->andReturn(new CommandResult(true, 0, '', 'nginx version: nginx/1.24.0', 0.1));

        $nginxService = new NginxService($mockExecutor);
        $status = $nginxService->getStatus();

        expect($status['running'])->toBeTrue();
        expect($status['version'])->toContain('nginx/1.24.0');
    });
});
