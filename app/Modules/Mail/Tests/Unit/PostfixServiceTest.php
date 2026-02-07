<?php

declare(strict_types=1);

use App\Modules\Mail\Services\PostfixService;
use App\Services\SystemCommandExecutor;
use App\Services\CommandResult;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => [
        'systemctl', 'postmap', 'postqueue', 'postsuper', 'postconf',
        'doveadm', 'mkdir', 'chown', 'chmod', 'rm'
    ]]);
    config(['vsispanel.log_commands' => false]);
    config(['vsispanel.mail.virtual_domains_file' => '/tmp/test_virtual_domains']);
    config(['vsispanel.mail.virtual_mailboxes_file' => '/tmp/test_virtual_mailboxes']);
    config(['vsispanel.mail.virtual_users_file' => '/tmp/test_virtual_users']);
    config(['vsispanel.mail.virtual_aliases_file' => '/tmp/test_virtual_aliases']);
    config(['vsispanel.mail.mail_base_dir' => '/tmp/test_vhosts']);

    // Clean up test files
    @unlink('/tmp/test_virtual_domains');
    @unlink('/tmp/test_virtual_mailboxes');
    @unlink('/tmp/test_virtual_users');
    @unlink('/tmp/test_virtual_aliases');
});

afterEach(function () {
    // Clean up test files
    @unlink('/tmp/test_virtual_domains');
    @unlink('/tmp/test_virtual_mailboxes');
    @unlink('/tmp/test_virtual_users');
    @unlink('/tmp/test_virtual_aliases');
});

describe('PostfixService', function () {

    it('can be instantiated', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $service = new PostfixService($executor);

        expect($service)->toBeInstanceOf(PostfixService::class);
    });

    it('adds domain to virtual domains file', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        $service = new PostfixService($executor);
        $service->addDomain('example.com');

        $content = File::get('/tmp/test_virtual_domains');
        expect($content)->toContain('example.com');
        expect($content)->toContain('OK');
    });

    it('does not add duplicate domain', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        $service = new PostfixService($executor);
        $service->addDomain('example.com');
        $service->addDomain('example.com');

        $content = File::get('/tmp/test_virtual_domains');
        $occurrences = substr_count($content, 'example.com');
        expect($occurrences)->toBe(1);
    });

    it('removes domain from virtual domains file', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        File::put('/tmp/test_virtual_domains', "example.com\tOK\nother.com\tOK\n");

        $service = new PostfixService($executor);
        $service->removeDomain('example.com');

        $content = File::get('/tmp/test_virtual_domains');
        expect($content)->not->toContain('example.com');
        expect($content)->toContain('other.com');
    });

    it('adds alias to virtual aliases file', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        $service = new PostfixService($executor);
        $service->addAlias('info@example.com', 'admin@example.com');

        $content = File::get('/tmp/test_virtual_aliases');
        expect($content)->toContain('info@example.com');
        expect($content)->toContain('admin@example.com');
    });

    it('removes alias from virtual aliases file', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        File::put('/tmp/test_virtual_aliases', "info@example.com\tadmin@example.com\n");

        $service = new PostfixService($executor);
        $service->removeAlias('info@example.com');

        $content = File::get('/tmp/test_virtual_aliases');
        expect($content)->not->toContain('info@example.com');
    });

    it('adds forwarding with keep copy option', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        $service = new PostfixService($executor);
        $service->addForwarding('user@example.com', 'forward@other.com', true);

        $content = File::get('/tmp/test_virtual_aliases');
        expect($content)->toContain('user@example.com');
        expect($content)->toContain('user@example.com,forward@other.com');
    });

    it('adds forwarding without keep copy option', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        $service = new PostfixService($executor);
        $service->addForwarding('user@example.com', 'forward@other.com', false);

        $content = File::get('/tmp/test_virtual_aliases');
        expect($content)->toContain('user@example.com');
        expect($content)->toContain('forward@other.com');
        expect($content)->not->toContain('user@example.com,forward@other.com');
    });

    it('parses mail queue correctly', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $queueOutput = <<<OUTPUT
-Queue ID-  --Size-- ----Arrival Time---- -Sender/Recipient-------
ABC123DEF       1234 Mon Jan 15 10:30:00  sender@example.com
                                         recipient@other.com
-- 1 Kbytes in 1 Request.
OUTPUT;

        $executor->shouldReceive('executeAsRoot')
            ->with('postqueue', ['-p'])
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: $queueOutput, stderr: '', executionTime: 0.1));

        $service = new PostfixService($executor);
        $status = $service->getQueueStatus();

        expect($status)->toBeArray();
        expect($status)->toHaveKey('count');
        expect($status)->toHaveKey('messages');
    });

    it('generates main config from template', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $service = new PostfixService($executor);

        $config = $service->generateMainConfig([
            'hostname' => 'mail.example.com',
            'domain' => 'example.com',
        ]);

        expect($config)->toContain('myhostname = mail.example.com');
        expect($config)->toContain('mydomain = example.com');
        expect($config)->toContain('virtual_mailbox_domains');
    });

});
