<?php

declare(strict_types=1);

use App\Modules\Mail\Services\DovecotService;
use App\Services\SystemCommandExecutor;
use App\Services\CommandResult;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => [
        'systemctl', 'doveadm', 'mkdir', 'chown', 'chmod', 'rm', 'du'
    ]]);
    config(['vsispanel.log_commands' => false]);
    config(['vsispanel.mail.dovecot_passwd_file' => '/tmp/test_dovecot_passwd']);
    config(['vsispanel.mail.mail_base_dir' => '/tmp/test_vhosts']);
    config(['vsispanel.mail.vmail_uid' => 5000]);
    config(['vsispanel.mail.vmail_gid' => 5000]);

    // Clean up test files
    @unlink('/tmp/test_dovecot_passwd');
});

afterEach(function () {
    @unlink('/tmp/test_dovecot_passwd');
});

describe('DovecotService', function () {

    it('can be instantiated', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $service = new DovecotService($executor);

        expect($service)->toBeInstanceOf(DovecotService::class);
    });

    it('hashes password using doveadm', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('execute')
            ->with('doveadm', ['pw', '-s', 'SSHA512', '-p', 'testpassword'])
            ->andReturn(new CommandResult(
                success: true,
                exitCode: 0,
                stdout: '{SSHA512}hashedpassword123',
                stderr: '',
                executionTime: 0.1
            ));

        $service = new DovecotService($executor);
        $hash = $service->hashPassword('testpassword');

        expect($hash)->toBe('{SSHA512}hashedpassword123');
    });

    it('creates mailbox with quota', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('execute')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '{SSHA512}hash', stderr: '', executionTime: 0.1));
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        $service = new DovecotService($executor);
        $service->createMailbox('user@example.com', 'password', 1024);

        $content = File::get('/tmp/test_dovecot_passwd');
        expect($content)->toContain('user@example.com');
        expect($content)->toContain('userdb_quota_rule=*:bytes=1073741824'); // 1024 MB in bytes
    });

    it('deletes mailbox entry from passwd file', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        File::put('/tmp/test_dovecot_passwd', "user@example.com:hash:5000:5000::/var/mail/vhosts/example.com/user:extra\nother@example.com:hash:5000:5000::/var/mail/vhosts/example.com/other:extra\n");

        $service = new DovecotService($executor);
        $service->deleteMailbox('user@example.com');

        $content = File::get('/tmp/test_dovecot_passwd');
        expect($content)->not->toContain('user@example.com');
        expect($content)->toContain('other@example.com');
    });

    it('changes password in passwd file', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('execute')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '{SSHA512}newhash', stderr: '', executionTime: 0.1));
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        File::put('/tmp/test_dovecot_passwd', "user@example.com:{SSHA512}oldhash:5000:5000::/var/mail/vhosts/example.com/user:extra\n");

        $service = new DovecotService($executor);
        $service->changePassword('user@example.com', 'newpassword');

        $content = File::get('/tmp/test_dovecot_passwd');
        expect($content)->toContain('{SSHA512}newhash');
        expect($content)->not->toContain('{SSHA512}oldhash');
    });

    it('sets quota for existing mailbox', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

        File::put('/tmp/test_dovecot_passwd', "user@example.com:hash:5000:5000::/var/mail/vhosts/example.com/user:userdb_mail=maildir:/var/mail/vhosts/example.com/user/Maildir userdb_quota_rule=*:bytes=1073741824\n");

        $service = new DovecotService($executor);
        $service->setQuota('user@example.com', 2048);

        $content = File::get('/tmp/test_dovecot_passwd');
        expect($content)->toContain('userdb_quota_rule=*:bytes=2147483648'); // 2048 MB in bytes
    });

    it('gets mailbox info', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(success: false, exitCode: 1, stdout: '', stderr: '', executionTime: 0.1));

        File::put('/tmp/test_dovecot_passwd', "user@example.com:hash:5000:5000::/var/mail/vhosts/example.com/user:userdb_mail=maildir:/var/mail/vhosts/example.com/user/Maildir userdb_quota_rule=*:bytes=1073741824\n");

        $service = new DovecotService($executor);
        $info = $service->getMailboxInfo('user@example.com');

        expect($info)->toBeArray();
        expect($info)->toHaveKeys(['email', 'message_count', 'new_count', 'quota_limit']);
        expect($info['email'])->toBe('user@example.com');
        expect($info['quota_limit'])->toBe(1073741824);
    });

    it('gets dovecot status', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $executor->shouldReceive('executeAsRoot')
            ->with('systemctl', ['is-active', 'dovecot'])
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: 'active', stderr: '', executionTime: 0.1));
        $executor->shouldReceive('execute')
            ->with('dovecot', ['--version'])
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '2.3.21', stderr: '', executionTime: 0.1));
        $executor->shouldReceive('executeAsRoot')
            ->with('doveadm', ['who'])
            ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: "username proto (ip)\nuser@example.com imap (192.168.1.1)", stderr: '', executionTime: 0.1));

        $service = new DovecotService($executor);
        $status = $service->getStatus();

        expect($status)->toBeArray();
        expect($status)->toHaveKeys(['running', 'version', 'connected_users']);
        expect($status['running'])->toBeTrue();
        expect($status['version'])->toBe('2.3.21');
    });

    it('generates dovecot config from template', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $service = new DovecotService($executor);

        $config = $service->generateConfig([
            'protocols' => ['imap', 'pop3'],
            'ssl_cert' => '/etc/ssl/certs/dovecot.pem',
            'ssl_key' => '/etc/ssl/private/dovecot.key',
        ]);

        expect($config)->toContain('protocols = imap pop3');
        expect($config)->toContain('ssl_cert');
        expect($config)->toContain('ssl_key');
    });

});
