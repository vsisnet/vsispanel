<?php

declare(strict_types=1);

use App\Modules\Mail\Services\RspamdService;
use App\Services\SystemCommandExecutor;
use App\Services\CommandResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => [
        'systemctl', 'rspamd', 'chown', 'chmod', 'mkdir'
    ]]);
    config(['vsispanel.log_commands' => false]);
    config(['vsispanel.mail.rspamd_api_url' => 'http://127.0.0.1:11334']);
    config(['vsispanel.mail.rspamd_api_password' => 'testpassword']);
    config(['vsispanel.mail.rspamd_config_path' => '/tmp/test_rspamd']);
});

afterEach(function () {
    // Clean up test files
    File::deleteDirectory('/tmp/test_rspamd');
});

describe('RspamdService', function () {

    it('can be instantiated', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $service = new RspamdService($executor);

        expect($service)->toBeInstanceOf(RspamdService::class);
    });

    describe('Service Status', function () {

        it('returns running status when service is active', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['is-active', 'rspamd'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: 'active',
                    stderr: '',
                    executionTime: 0.1
                ));
            $executor->shouldReceive('execute')
                ->with('rspamd', ['--version'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: 'Rspamd 3.7.1',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new RspamdService($executor);
            $status = $service->getStatus();

            expect($status['running'])->toBeTrue();
            expect($status['version'])->toBe('3.7.1');
            expect($status['api_url'])->toBe('http://127.0.0.1:11334');
        });

        it('returns stopped status when service is inactive', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['is-active', 'rspamd'])
                ->andReturn(new CommandResult(
                    success: false,
                    exitCode: 3,
                    stdout: 'inactive',
                    stderr: '',
                    executionTime: 0.1
                ));
            $executor->shouldReceive('execute')
                ->with('rspamd', ['--version'])
                ->andReturn(new CommandResult(
                    success: false,
                    exitCode: 1,
                    stdout: '',
                    stderr: 'command not found',
                    executionTime: 0.1
                ));

            $service = new RspamdService($executor);
            $status = $service->getStatus();

            expect($status['running'])->toBeFalse();
            expect($status['version'])->toBe('Unknown');
        });

    });

    describe('Statistics', function () {

        it('returns statistics from API', function () {
            Http::fake([
                'http://127.0.0.1:11334/stat' => Http::response([
                    'scanned' => 1000,
                    'learned' => 500,
                    'spam_count' => 100,
                    'ham_count' => 900,
                    'connections' => 50,
                    'uptime' => 86400,
                ], 200),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);
            $stats = $service->getStatistics();

            expect($stats['scanned'])->toBe(1000);
            expect($stats['spam_count'])->toBe(100);
            expect($stats['ham_count'])->toBe(900);
            expect($stats['learned'])->toBe(500);
        });

        it('returns default statistics on API failure', function () {
            Http::fake([
                'http://127.0.0.1:11334/stat' => Http::response(null, 500),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);
            $stats = $service->getStatistics();

            expect($stats['scanned'])->toBe(0);
            expect($stats['spam_count'])->toBe(0);
            expect($stats['ham_count'])->toBe(0);
        });

    });

    describe('Spam Score Configuration', function () {

        it('returns default scores when config file does not exist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $scores = $service->getSpamScore();

            expect($scores['reject'])->toBe(15.0);
            expect($scores['add_header'])->toBe(6.0);
            expect($scores['greylist'])->toBe(4.0);
        });

        it('parses existing config file', function () {
            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);
            File::put('/tmp/test_rspamd/local.d/actions.conf', <<<CONF
# Rspamd actions configuration
reject = 20;
add_header = 8;
greylist = 5;
CONF);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $scores = $service->getSpamScore();

            expect($scores['reject'])->toBe(20.0);
            expect($scores['add_header'])->toBe(8.0);
            expect($scores['greylist'])->toBe(5.0);
        });

        it('writes new spam score configuration', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('mkdir', ['-p', '/tmp/test_rspamd/local.d'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);

            $service = new RspamdService($executor);
            $service->setSpamScore([
                'reject' => 18,
                'add_header' => 7,
                'greylist' => 3,
            ]);

            $content = File::get('/tmp/test_rspamd/local.d/actions.conf');
            expect($content)->toContain('reject = 18;');
            expect($content)->toContain('add_header = 7;');
            expect($content)->toContain('greylist = 3;');
        });

    });

    describe('Whitelist Management', function () {

        it('returns empty whitelist when file does not exist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $whitelist = $service->getWhitelist();

            expect($whitelist)->toBeEmpty();
        });

        it('parses existing whitelist file', function () {
            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);
            File::put('/tmp/test_rspamd/local.d/whitelist.inc', <<<CONF
# Generated by VSISPanel
"trusted@example.com"
"safedomain.com"
CONF);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $whitelist = $service->getWhitelist();

            expect($whitelist)->toHaveCount(2);
            expect($whitelist[0]['entry'])->toBe('trusted@example.com');
            expect($whitelist[0]['type'])->toBe('email');
            expect($whitelist[1]['entry'])->toBe('safedomain.com');
            expect($whitelist[1]['type'])->toBe('domain');
        });

        it('adds entry to whitelist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('mkdir', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);

            $service = new RspamdService($executor);
            $service->addToWhitelist('trusted@example.com', 'email');

            $content = File::get('/tmp/test_rspamd/local.d/whitelist.inc');
            expect($content)->toContain('"trusted@example.com"');
        });

        it('removes entry from whitelist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);
            File::put('/tmp/test_rspamd/local.d/whitelist.inc', <<<CONF
# Generated by VSISPanel
"remove@example.com"
"keep@example.com"
CONF);

            $service = new RspamdService($executor);
            $service->removeFromWhitelist('remove@example.com');

            $content = File::get('/tmp/test_rspamd/local.d/whitelist.inc');
            expect($content)->not->toContain('"remove@example.com"');
            expect($content)->toContain('"keep@example.com"');
        });

        it('does not add duplicate entries', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('mkdir', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);
            File::put('/tmp/test_rspamd/local.d/whitelist.inc', <<<CONF
# Generated by VSISPanel
"existing@example.com"
CONF);

            $service = new RspamdService($executor);
            $service->addToWhitelist('existing@example.com', 'email');

            $whitelist = $service->getWhitelist();
            expect($whitelist)->toHaveCount(1);
        });

    });

    describe('Blacklist Management', function () {

        it('returns empty blacklist when file does not exist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $blacklist = $service->getBlacklist();

            expect($blacklist)->toBeEmpty();
        });

        it('adds entry to blacklist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('mkdir', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);

            $service = new RspamdService($executor);
            $service->addToBlacklist('spam@malicious.com', 'email');

            $content = File::get('/tmp/test_rspamd/local.d/blacklist.inc');
            expect($content)->toContain('"spam@malicious.com"');
        });

        it('removes entry from blacklist', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);
            File::put('/tmp/test_rspamd/local.d/blacklist.inc', <<<CONF
# Generated by VSISPanel
"remove@spam.com"
"keep@spam.com"
CONF);

            $service = new RspamdService($executor);
            $service->removeFromBlacklist('remove@spam.com');

            $content = File::get('/tmp/test_rspamd/local.d/blacklist.inc');
            expect($content)->not->toContain('"remove@spam.com"');
            expect($content)->toContain('"keep@spam.com"');
        });

    });

    describe('Training', function () {

        it('trains message as ham', function () {
            Http::fake([
                'http://127.0.0.1:11334/learnham' => Http::response(['success' => true], 200),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $result = $service->trainHam('From: test@example.com\r\nSubject: Test\r\n\r\nThis is a test message.');

            expect($result)->toBeTrue();
        });

        it('trains message as spam', function () {
            Http::fake([
                'http://127.0.0.1:11334/learnspam' => Http::response(['success' => true], 200),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $result = $service->trainSpam('From: spam@example.com\r\nSubject: BUY NOW\r\n\r\nSpam content here.');

            expect($result)->toBeTrue();
        });

        it('returns false on training failure', function () {
            Http::fake([
                'http://127.0.0.1:11334/learnham' => Http::response(null, 500),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $result = $service->trainHam('message content');

            expect($result)->toBeFalse();
        });

    });

    describe('Message Scanning', function () {

        it('scans message and returns spam score', function () {
            Http::fake([
                'http://127.0.0.1:11334/checkv2' => Http::response([
                    'score' => 8.5,
                    'required_score' => 6.0,
                    'action' => 'add header',
                    'symbols' => [
                        'SPAM_RULE' => ['score' => 5.0],
                        'SUSPICIOUS' => ['score' => 3.5],
                    ],
                ], 200),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $result = $service->scanMessage('From: test@example.com\r\nSubject: Test\r\n\r\nMessage content.');

            expect($result['score'])->toBe(8.5);
            expect((float) $result['required_score'])->toBe(6.0);
            expect($result['action'])->toBe('add header');
            expect($result['is_spam'])->toBeTrue();
            expect($result['symbols'])->toHaveCount(2);
        });

        it('returns error on scan failure', function () {
            Http::fake([
                'http://127.0.0.1:11334/checkv2' => Http::response(null, 500),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $result = $service->scanMessage('message content');

            expect($result)->toHaveKey('error');
        });

    });

    describe('History', function () {

        it('retrieves scan history', function () {
            Http::fake([
                'http://127.0.0.1:11334/history?limit=50' => Http::response([
                    'rows' => [
                        ['time' => '2024-01-20', 'action' => 'reject', 'score' => 15.0],
                        ['time' => '2024-01-20', 'action' => 'no action', 'score' => 2.0],
                    ],
                ], 200),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $history = $service->getHistory(50);

            expect($history)->toHaveCount(2);
            expect($history[0]['action'])->toBe('reject');
        });

        it('returns empty array on failure', function () {
            Http::fake([
                'http://127.0.0.1:11334/history?limit=100' => Http::response(null, 500),
            ]);

            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new RspamdService($executor);

            $history = $service->getHistory();

            expect($history)->toBeEmpty();
        });

    });

    describe('Service Control', function () {

        it('reloads rspamd service', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->once()
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new RspamdService($executor);
            $result = $service->reload();

            expect($result->success)->toBeTrue();
        });

        it('restarts rspamd service', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['restart', 'rspamd'])
                ->once()
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '',
                    stderr: '',
                    executionTime: 0.2
                ));

            $service = new RspamdService($executor);
            $result = $service->restart();

            expect($result->success)->toBeTrue();
        });

    });

    describe('Default Configuration', function () {

        it('generates default configuration', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->with('mkdir', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chown', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('chmod', Mockery::any())
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('executeAsRoot')
                ->with('systemctl', ['reload', 'rspamd'])
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            File::makeDirectory('/tmp/test_rspamd/local.d', 0755, true);

            $service = new RspamdService($executor);
            $service->generateDefaultConfig();

            expect(File::exists('/tmp/test_rspamd/local.d/actions.conf'))->toBeTrue();
            expect(File::exists('/tmp/test_rspamd/local.d/worker-normal.inc'))->toBeTrue();
            expect(File::exists('/tmp/test_rspamd/local.d/logging.inc'))->toBeTrue();

            $actionsContent = File::get('/tmp/test_rspamd/local.d/actions.conf');
            expect($actionsContent)->toContain('reject = 15;');
            expect($actionsContent)->toContain('add_header = 6;');
            expect($actionsContent)->toContain('greylist = 4;');
        });

    });

});
