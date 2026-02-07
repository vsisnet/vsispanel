<?php

declare(strict_types=1);

use App\Modules\Mail\Services\MailSecurityService;
use App\Services\SystemCommandExecutor;
use App\Services\CommandResult;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => [
        'opendkim-genkey', 'dig', 'systemctl', 'chown', 'chmod', 'mkdir'
    ]]);
    config(['vsispanel.log_commands' => false]);
    config(['vsispanel.mail.dkim_key_dir' => '/tmp/test_dkim_keys']);
    config(['vsispanel.mail.dkim_selector' => 'mail']);
});

describe('MailSecurityService', function () {

    it('can be instantiated', function () {
        $executor = Mockery::mock(SystemCommandExecutor::class);
        $service = new MailSecurityService($executor);

        expect($service)->toBeInstanceOf(MailSecurityService::class);
    });

    describe('SPF Generation', function () {

        it('generates basic SPF record', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $spf = $service->generateSPF('example.com', '192.168.1.1');

            expect($spf)->toContain('v=spf1');
            expect($spf)->toContain('mx');
            expect($spf)->toContain('a');
            expect($spf)->toContain('ip4:192.168.1.1');
            expect($spf)->toContain('~all');
        });

        it('generates SPF record with IPv6', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $spf = $service->generateSPF('example.com', '192.168.1.1', [
                'ipv6' => '2001:db8::1',
            ]);

            expect($spf)->toContain('ip4:192.168.1.1');
            expect($spf)->toContain('ip6:2001:db8::1');
        });

        it('generates SPF record with includes', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $spf = $service->generateSPF('example.com', '192.168.1.1', [
                'include' => ['_spf.google.com', '_spf.protection.outlook.com'],
            ]);

            expect($spf)->toContain('include:_spf.google.com');
            expect($spf)->toContain('include:_spf.protection.outlook.com');
        });

        it('allows custom policy', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $spf = $service->generateSPF('example.com', '192.168.1.1', [
                'policy' => '-all', // Hard fail
            ]);

            expect($spf)->toContain('-all');
            expect($spf)->not->toContain('~all');
        });

    });

    describe('DMARC Generation', function () {

        it('generates basic DMARC record', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $dmarc = $service->generateDMARC('example.com', 'admin@example.com');

            expect($dmarc)->toContain('v=DMARC1');
            expect($dmarc)->toContain('p=none');
            expect($dmarc)->toContain('rua=mailto:admin@example.com');
        });

        it('generates DMARC record with quarantine policy', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $dmarc = $service->generateDMARC('example.com', 'admin@example.com', [
                'policy' => 'quarantine',
            ]);

            expect($dmarc)->toContain('p=quarantine');
        });

        it('generates DMARC record with reject policy', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $dmarc = $service->generateDMARC('example.com', 'admin@example.com', [
                'policy' => 'reject',
            ]);

            expect($dmarc)->toContain('p=reject');
        });

        it('generates DMARC record with subdomain policy', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $dmarc = $service->generateDMARC('example.com', 'admin@example.com', [
                'policy' => 'quarantine',
                'subdomain_policy' => 'reject',
            ]);

            expect($dmarc)->toContain('p=quarantine');
            expect($dmarc)->toContain('sp=reject');
        });

        it('generates DMARC record with percentage', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $dmarc = $service->generateDMARC('example.com', 'admin@example.com', [
                'percentage' => 50,
            ]);

            expect($dmarc)->toContain('pct=50');
        });

        it('generates DMARC record with forensic email', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $service = new MailSecurityService($executor);

            $dmarc = $service->generateDMARC('example.com', 'admin@example.com', [
                'forensic_email' => 'forensic@example.com',
            ]);

            expect($dmarc)->toContain('rua=mailto:admin@example.com');
            expect($dmarc)->toContain('ruf=mailto:forensic@example.com');
        });

    });

    describe('SPF Verification', function () {

        it('verifies SPF record exists', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('execute')
                ->with('dig', ['+short', 'TXT', 'example.com'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '"v=spf1 mx a ip4:192.168.1.1 ~all"',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new MailSecurityService($executor);
            $result = $service->verifySPF('example.com');

            expect($result)->toBeArray();
            expect($result['valid'])->toBeTrue();
            expect($result['records'])->toHaveCount(1);
        });

        it('detects missing SPF record', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('execute')
                ->with('dig', ['+short', 'TXT', 'example.com'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new MailSecurityService($executor);
            $result = $service->verifySPF('example.com');

            expect($result['valid'])->toBeFalse();
            expect($result['records'])->toBeEmpty();
        });

    });

    describe('DKIM Verification', function () {

        it('verifies DKIM record exists', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('execute')
                ->with('dig', ['+short', 'TXT', 'mail._domainkey.example.com'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '"v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4..."',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new MailSecurityService($executor);
            $result = $service->verifyDKIM('example.com');

            expect($result)->toBeArray();
            expect($result['valid'])->toBeTrue();
            expect($result['selector'])->toBe('mail');
        });

        it('detects missing DKIM record', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('execute')
                ->with('dig', ['+short', 'TXT', 'mail._domainkey.example.com'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new MailSecurityService($executor);
            $result = $service->verifyDKIM('example.com');

            expect($result['valid'])->toBeFalse();
        });

    });

    describe('DMARC Verification', function () {

        it('verifies DMARC record exists', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('execute')
                ->with('dig', ['+short', 'TXT', '_dmarc.example.com'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '"v=DMARC1; p=quarantine; rua=mailto:admin@example.com"',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new MailSecurityService($executor);
            $result = $service->verifyDMARC('example.com');

            expect($result)->toBeArray();
            expect($result['valid'])->toBeTrue();
            expect($result['policy'])->toBe('quarantine');
        });

        it('detects missing DMARC record', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('execute')
                ->with('dig', ['+short', 'TXT', '_dmarc.example.com'])
                ->andReturn(new CommandResult(
                    success: true,
                    exitCode: 0,
                    stdout: '',
                    stderr: '',
                    executionTime: 0.1
                ));

            $service = new MailSecurityService($executor);
            $result = $service->verifyDMARC('example.com');

            expect($result['valid'])->toBeFalse();
            expect($result['policy'])->toBeNull();
        });

    });

    describe('Mail Security Setup', function () {

        it('creates all DNS records for mail security', function () {
            $executor = Mockery::mock(SystemCommandExecutor::class);
            $executor->shouldReceive('executeAsRoot')
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));
            $executor->shouldReceive('execute')
                ->andReturn(new CommandResult(success: true, exitCode: 0, stdout: '', stderr: '', executionTime: 0.1));

            // Skip actual DKIM generation for test
            $service = Mockery::mock(MailSecurityService::class, [$executor])
                ->makePartial()
                ->shouldAllowMockingProtectedMethods();

            $service->shouldReceive('generateDKIM')
                ->andReturn([
                    'selector' => 'mail',
                    'dns_record_name' => 'mail._domainkey.example.com',
                    'dns_record_type' => 'TXT',
                    'dns_record_value' => 'v=DKIM1; k=rsa; p=publickey',
                ]);

            $records = $service->setupMailSecurity('example.com', '192.168.1.1', 'admin@example.com');

            expect($records)->toBeArray();
            expect($records)->toHaveKeys(['spf', 'dkim', 'dmarc']);

            expect($records['spf']['type'])->toBe('TXT');
            expect($records['spf']['content'])->toContain('v=spf1');

            expect($records['dkim']['type'])->toBe('TXT');
            expect($records['dkim']['name'])->toContain('_domainkey');

            expect($records['dmarc']['type'])->toBe('TXT');
            expect($records['dmarc']['name'])->toBe('_dmarc.example.com');
        });

    });

});
