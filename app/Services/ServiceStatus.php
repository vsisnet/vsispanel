<?php

declare(strict_types=1);

namespace App\Services;

class ServiceStatus
{
    public function __construct(
        public readonly string $name,
        public readonly bool $isRunning,
        public readonly ?string $uptime,
        public readonly ?int $pid,
        public readonly ?string $memoryUsage,
        public readonly ?string $mainPid,
        public readonly ?string $activeState,
        public readonly ?string $subState,
        public readonly ?string $loadState
    ) {}

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'is_running' => $this->isRunning,
            'uptime' => $this->uptime,
            'pid' => $this->pid,
            'memory_usage' => $this->memoryUsage,
            'main_pid' => $this->mainPid,
            'active_state' => $this->activeState,
            'sub_state' => $this->subState,
            'load_state' => $this->loadState,
        ];
    }

    /**
     * Create from systemctl status output
     */
    public static function fromSystemctl(string $name, string $output): self
    {
        $isRunning = str_contains($output, 'Active: active (running)');
        $uptime = null;
        $pid = null;
        $memoryUsage = null;
        $mainPid = null;
        $activeState = null;
        $subState = null;
        $loadState = null;

        // Parse Active line for state and uptime
        if (preg_match('/Active:\s+(\w+)\s+\((\w+)\)(?:\s+since\s+.+;\s+(.+)\s+ago)?/', $output, $matches)) {
            $activeState = $matches[1] ?? null;
            $subState = $matches[2] ?? null;
            $uptime = $matches[3] ?? null;
        }

        // Parse Main PID
        if (preg_match('/Main PID:\s+(\d+)/', $output, $matches)) {
            $mainPid = $matches[1];
            $pid = (int) $matches[1];
        }

        // Parse Memory
        if (preg_match('/Memory:\s+([\d.]+[KMGT]?)/', $output, $matches)) {
            $memoryUsage = $matches[1];
        }

        // Parse Load state
        if (preg_match('/Loaded:\s+(\w+)/', $output, $matches)) {
            $loadState = $matches[1];
        }

        return new self(
            name: $name,
            isRunning: $isRunning,
            uptime: $uptime,
            pid: $pid,
            memoryUsage: $memoryUsage,
            mainPid: $mainPid,
            activeState: $activeState,
            subState: $subState,
            loadState: $loadState
        );
    }

    /**
     * Create from unknown/not found service
     */
    public static function notFound(string $name): self
    {
        return new self(
            name: $name,
            isRunning: false,
            uptime: null,
            pid: null,
            memoryUsage: null,
            mainPid: null,
            activeState: 'inactive',
            subState: 'dead',
            loadState: 'not-found'
        );
    }
}
