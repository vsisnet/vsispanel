<?php

declare(strict_types=1);

use App\Modules\FTP\Services\FtpService;

beforeEach(function () {
    config(['vsispanel.ftp.server' => 'proftpd']);
    config(['vsispanel.ftp.config_path' => '/etc/proftpd/proftpd.conf']);
    config(['vsispanel.ftp.users_db_path' => '/etc/proftpd/ftpd.passwd']);
    config(['vsispanel.ftp.default_uid' => 33]);
    config(['vsispanel.ftp.default_gid' => 33]);
    config(['vsispanel.hosting.web_root' => '/var/www']);
});

describe('FtpService', function () {

    it('can be instantiated', function () {
        $service = new FtpService();
        expect($service)->toBeInstanceOf(FtpService::class);
    });

    describe('Username Validation', function () {

        it('validates valid username starting with letter', function () {
            $service = new FtpService();

            // Valid usernames
            expect(fn() => $service->validateUsername('johndoe'))->not->toThrow(RuntimeException::class);
            expect(fn() => $service->validateUsername('user123'))->not->toThrow(RuntimeException::class);
            expect(fn() => $service->validateUsername('john_doe'))->not->toThrow(RuntimeException::class);
            expect(fn() => $service->validateUsername('Admin_User_1'))->not->toThrow(RuntimeException::class);
        });

        it('rejects username starting with number', function () {
            $service = new FtpService();

            expect(fn() => $service->validateUsername('123user'))
                ->toThrow(RuntimeException::class, 'Must start with a letter');
        });

        it('rejects username starting with underscore', function () {
            $service = new FtpService();

            expect(fn() => $service->validateUsername('_username'))
                ->toThrow(RuntimeException::class, 'Must start with a letter');
        });

        it('rejects username that is too short', function () {
            $service = new FtpService();

            expect(fn() => $service->validateUsername('ab'))
                ->toThrow(RuntimeException::class, '3-32 characters');
        });

        it('rejects username that is too long', function () {
            $service = new FtpService();

            $longUsername = 'a' . str_repeat('b', 32); // 33 chars
            expect(fn() => $service->validateUsername($longUsername))
                ->toThrow(RuntimeException::class, '3-32 characters');
        });

        it('rejects username with special characters', function () {
            $service = new FtpService();

            expect(fn() => $service->validateUsername('user@name'))
                ->toThrow(RuntimeException::class, 'Must start with a letter');

            expect(fn() => $service->validateUsername('user-name'))
                ->toThrow(RuntimeException::class, 'Must start with a letter');

            expect(fn() => $service->validateUsername('user.name'))
                ->toThrow(RuntimeException::class, 'Must start with a letter');
        });

        it('rejects reserved usernames', function () {
            $service = new FtpService();

            // Reserved usernames that pass the regex validation
            $reserved = ['root', 'admin', 'administrator', 'ftp', 'anonymous', 'nobody'];

            foreach ($reserved as $username) {
                expect(fn() => $service->validateUsername($username))
                    ->toThrow(RuntimeException::class, 'reserved');
            }
        });

        it('rejects reserved usernames case insensitively', function () {
            $service = new FtpService();

            expect(fn() => $service->validateUsername('ROOT'))
                ->toThrow(RuntimeException::class, 'reserved');

            expect(fn() => $service->validateUsername('Admin'))
                ->toThrow(RuntimeException::class, 'reserved');
        });

    });

    describe('Path Validation', function () {

        it('accepts valid username formats', function () {
            $service = new FtpService();

            // These should not throw
            $valid = ['abc', 'user1', 'user_name', 'UserName123'];
            foreach ($valid as $username) {
                expect(fn() => $service->validateUsername($username))->not->toThrow(RuntimeException::class);
            }
        });

    });

    describe('Service Configuration', function () {

        it('uses proftpd as default server', function () {
            config(['vsispanel.ftp.server' => 'proftpd']);
            $service = new FtpService();

            // Service should be configured for proftpd
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('ftpServer');
            $property->setAccessible(true);

            expect($property->getValue($service))->toBe('proftpd');
        });

        it('can be configured for pure-ftpd', function () {
            config(['vsispanel.ftp.server' => 'pure-ftpd']);
            $service = new FtpService();

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('ftpServer');
            $property->setAccessible(true);

            expect($property->getValue($service))->toBe('pure-ftpd');
        });

        it('uses correct config path', function () {
            config(['vsispanel.ftp.config_path' => '/custom/path/proftpd.conf']);
            $service = new FtpService();

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('configPath');
            $property->setAccessible(true);

            expect($property->getValue($service))->toBe('/custom/path/proftpd.conf');
        });

    });

});

describe('FtpAccount Model', function () {

    it('has correct status constants', function () {
        expect(\App\Modules\FTP\Models\FtpAccount::STATUS_ACTIVE)->toBe('active');
        expect(\App\Modules\FTP\Models\FtpAccount::STATUS_SUSPENDED)->toBe('suspended');
        expect(\App\Modules\FTP\Models\FtpAccount::STATUS_DISABLED)->toBe('disabled');
    });

    it('returns all statuses', function () {
        $statuses = \App\Modules\FTP\Models\FtpAccount::getStatuses();

        expect($statuses)->toBeArray();
        expect($statuses)->toContain('active');
        expect($statuses)->toContain('suspended');
        expect($statuses)->toContain('disabled');
        expect(count($statuses))->toBe(3);
    });

});
