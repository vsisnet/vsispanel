<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\LogActivity;

class SystemCommandExecutor
{
    /**
     * Default timeout in seconds
     */
    protected int $timeout;

    /**
     * List of allowed commands
     */
    protected array $allowedCommands;

    /**
     * Whether to log commands
     */
    protected bool $logCommands;

    public function __construct()
    {
        $this->timeout = config('vsispanel.command_timeout', 30);
        $this->allowedCommands = config('vsispanel.allowed_commands', []);
        $this->logCommands = config('vsispanel.log_commands', true);
    }

    /**
     * Execute a command
     */
    public function execute(string $command, array $args = [], ?int $timeout = null): CommandResult
    {
        $startTime = microtime(true);
        $timeout = $timeout ?? $this->timeout;

        // Validate command is in whitelist
        if (!$this->isAllowedCommand($command)) {
            $this->logCommand($command, $args, 1, 'Command not in whitelist');
            return CommandResult::failed(
                "Command '{$command}' is not allowed. Only whitelisted commands can be executed.",
                microtime(true) - $startTime
            );
        }

        // Build full command with escaped arguments
        $fullCommand = $this->buildCommand($command, $args);

        // Execute command
        $result = $this->runCommand($fullCommand, $timeout, $startTime);

        // Log command execution
        $this->logCommand($command, $args, $result->exitCode, $result->stderr ?: $result->stdout);

        return $result;
    }

    /**
     * Execute a command as root (with sudo)
     */
    public function executeAsRoot(string $command, array $args = [], ?int $timeout = null): CommandResult
    {
        $startTime = microtime(true);
        $timeout = $timeout ?? $this->timeout;

        // Validate command is in whitelist
        if (!$this->isAllowedCommand($command)) {
            $this->logCommand("sudo {$command}", $args, 1, 'Command not in whitelist');
            return CommandResult::failed(
                "Command '{$command}' is not allowed. Only whitelisted commands can be executed.",
                microtime(true) - $startTime
            );
        }

        // Build full command with escaped arguments
        $fullCommand = 'sudo ' . $this->buildCommand($command, $args);

        // Execute command
        $result = $this->runCommand($fullCommand, $timeout, $startTime);

        // Log command execution
        $this->logCommand("sudo {$command}", $args, $result->exitCode, $result->stderr ?: $result->stdout);

        return $result;
    }

    /**
     * Check if a command is in the whitelist
     */
    public function isAllowedCommand(string $command): bool
    {
        // Extract base command (without path)
        $baseCommand = basename($command);

        // Check against whitelist with wildcard support
        foreach ($this->allowedCommands as $allowed) {
            // Exact match
            if ($baseCommand === $allowed) {
                return true;
            }

            // Wildcard match (e.g., php* matches php8.3-fpm)
            if (str_contains($allowed, '*')) {
                // Replace * with placeholder, escape regex, then replace placeholder with .*
                $placeholder = '___WILDCARD___';
                $escaped = str_replace('*', $placeholder, $allowed);
                $escaped = preg_quote($escaped, '/');
                $pattern = '/^' . str_replace($placeholder, '.*', $escaped) . '$/';
                if (preg_match($pattern, $baseCommand)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build command string with escaped arguments
     */
    protected function buildCommand(string $command, array $args): string
    {
        if (empty($args)) {
            return $command;
        }

        $escapedArgs = array_map(fn($arg) => escapeshellarg((string) $arg), $args);
        return $command . ' ' . implode(' ', $escapedArgs);
    }

    /**
     * Run the command and return result
     */
    protected function runCommand(string $fullCommand, int $timeout, float $startTime): CommandResult
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($fullCommand, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            return CommandResult::failed(
                'Failed to start process',
                microtime(true) - $startTime
            );
        }

        // Close stdin
        fclose($pipes[0]);

        // Set non-blocking mode
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;

        // Read output with timeout
        $endTime = time() + $timeout;
        while (time() < $endTime) {
            $status = proc_get_status($process);

            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }

            usleep(10000); // 10ms
        }

        // Check if timed out
        $status = proc_get_status($process);
        if ($status['running']) {
            $timedOut = true;
            proc_terminate($process, 9); // SIGKILL
        }

        // Get remaining output
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $executionTime = microtime(true) - $startTime;

        if ($timedOut) {
            return new CommandResult(
                success: false,
                exitCode: 124, // timeout exit code
                stdout: $stdout,
                stderr: "Command timed out after {$timeout} seconds",
                executionTime: $executionTime
            );
        }

        // Use status exit code if available (more reliable)
        $exitCode = $status['exitcode'] ?? $exitCode;

        return new CommandResult(
            success: $exitCode === 0,
            exitCode: $exitCode,
            stdout: trim($stdout),
            stderr: trim($stderr),
            executionTime: $executionTime
        );
    }

    /**
     * Log command execution
     */
    protected function logCommand(string $command, array $args, int $exitCode, string $output): void
    {
        if (!$this->logCommands) {
            return;
        }

        $user = auth()->user();
        $logData = [
            'command' => $command,
            'args' => $args,
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
            'output' => mb_substr($output, 0, 1000), // Limit output size
            'user_id' => $user?->id,
            'ip' => request()->ip(),
        ];

        // Log to Laravel log
        Log::channel('commands')->info('Command executed', $logData);

        // Log to activity log if user is authenticated
        if ($user) {
            activity('system_command')
                ->performedOn($user)
                ->withProperties($logData)
                ->log("Executed command: {$command}");
        }
    }

    /**
     * Set custom timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Get current allowed commands
     */
    public function getAllowedCommands(): array
    {
        return $this->allowedCommands;
    }

    /**
     * Check if command exists in system
     */
    public function commandExists(string $command): bool
    {
        $result = $this->runCommand("which {$command} 2>/dev/null", 5, microtime(true));
        return $result->isSuccess() && !empty($result->stdout);
    }
}
