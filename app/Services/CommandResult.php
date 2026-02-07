<?php

declare(strict_types=1);

namespace App\Services;

class CommandResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly float $executionTime
    ) {}

    /**
     * Check if command was successful
     */
    public function isSuccess(): bool
    {
        return $this->success && $this->exitCode === 0;
    }

    /**
     * Get stdout output
     */
    public function getOutput(): string
    {
        return $this->stdout;
    }

    /**
     * Get stderr output
     */
    public function getError(): string
    {
        return $this->stderr;
    }

    /**
     * Get combined output (stdout + stderr)
     */
    public function getCombinedOutput(): string
    {
        return trim($this->stdout . "\n" . $this->stderr);
    }

    /**
     * Get output as array of lines
     */
    public function getOutputLines(): array
    {
        return array_filter(explode("\n", $this->stdout));
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'exit_code' => $this->exitCode,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'execution_time' => $this->executionTime,
        ];
    }

    /**
     * Create from failed command
     */
    public static function failed(string $error, float $executionTime = 0): self
    {
        return new self(
            success: false,
            exitCode: 1,
            stdout: '',
            stderr: $error,
            executionTime: $executionTime
        );
    }

    /**
     * Create from successful command
     */
    public static function success(string $output, float $executionTime = 0): self
    {
        return new self(
            success: true,
            exitCode: 0,
            stdout: $output,
            stderr: '',
            executionTime: $executionTime
        );
    }
}
