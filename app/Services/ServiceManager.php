<?php

declare(strict_types=1);

namespace App\Services;

class ServiceManager
{
    /**
     * List of allowed services
     */
    protected array $allowedServices;

    /**
     * System command executor
     */
    protected SystemCommandExecutor $executor;

    public function __construct(SystemCommandExecutor $executor)
    {
        $this->executor = $executor;
        $this->allowedServices = config('vsispanel.allowed_services', []);
    }

    /**
     * Start a service
     */
    public function start(string $service): CommandResult
    {
        if (!$this->isAllowedService($service)) {
            return CommandResult::failed("Service '{$service}' is not in the allowed services list.");
        }

        return $this->executor->executeAsRoot('systemctl', ['start', $service]);
    }

    /**
     * Stop a service
     */
    public function stop(string $service): CommandResult
    {
        if (!$this->isAllowedService($service)) {
            return CommandResult::failed("Service '{$service}' is not in the allowed services list.");
        }

        return $this->executor->executeAsRoot('systemctl', ['stop', $service]);
    }

    /**
     * Restart a service
     */
    public function restart(string $service): CommandResult
    {
        if (!$this->isAllowedService($service)) {
            return CommandResult::failed("Service '{$service}' is not in the allowed services list.");
        }

        return $this->executor->executeAsRoot('systemctl', ['restart', $service]);
    }

    /**
     * Reload a service
     */
    public function reload(string $service): CommandResult
    {
        if (!$this->isAllowedService($service)) {
            return CommandResult::failed("Service '{$service}' is not in the allowed services list.");
        }

        return $this->executor->executeAsRoot('systemctl', ['reload', $service]);
    }

    /**
     * Get service status
     */
    public function status(string $service): ServiceStatus
    {
        if (!$this->isAllowedService($service)) {
            return ServiceStatus::notFound($service);
        }

        $result = $this->executor->execute('systemctl', ['status', $service]);

        if ($result->exitCode === 4) {
            // Unit not found
            return ServiceStatus::notFound($service);
        }

        return ServiceStatus::fromSystemctl($service, $result->getCombinedOutput());
    }

    /**
     * Check if service is running
     */
    public function isRunning(string $service): bool
    {
        if (!$this->isAllowedService($service)) {
            return false;
        }

        $result = $this->executor->execute('systemctl', ['is-active', $service]);
        return $result->isSuccess() && trim($result->stdout) === 'active';
    }

    /**
     * Check if service is enabled (starts at boot)
     */
    public function isEnabled(string $service): bool
    {
        if (!$this->isAllowedService($service)) {
            return false;
        }

        $result = $this->executor->execute('systemctl', ['is-enabled', $service]);
        return $result->isSuccess() && trim($result->stdout) === 'enabled';
    }

    /**
     * Enable service to start at boot
     */
    public function enable(string $service): CommandResult
    {
        if (!$this->isAllowedService($service)) {
            return CommandResult::failed("Service '{$service}' is not in the allowed services list.");
        }

        return $this->executor->executeAsRoot('systemctl', ['enable', $service]);
    }

    /**
     * Disable service from starting at boot
     */
    public function disable(string $service): CommandResult
    {
        if (!$this->isAllowedService($service)) {
            return CommandResult::failed("Service '{$service}' is not in the allowed services list.");
        }

        return $this->executor->executeAsRoot('systemctl', ['disable', $service]);
    }

    /**
     * Get status of all allowed services
     */
    public function getAllServicesStatus(): array
    {
        $statuses = [];

        foreach ($this->allowedServices as $service) {
            // Skip wildcards
            if (str_contains($service, '*')) {
                continue;
            }

            $statuses[$service] = $this->status($service)->toArray();
        }

        return $statuses;
    }

    /**
     * Check if a service is in the allowed list
     */
    public function isAllowedService(string $service): bool
    {
        // Check against whitelist with wildcard support
        foreach ($this->allowedServices as $allowed) {
            // Exact match
            if ($service === $allowed) {
                return true;
            }

            // Wildcard match (e.g., php*-fpm matches php8.3-fpm)
            if (str_contains($allowed, '*')) {
                // Replace * with placeholder, escape regex, then replace placeholder with .*
                $placeholder = '___WILDCARD___';
                $escaped = str_replace('*', $placeholder, $allowed);
                $escaped = preg_quote($escaped, '/');
                $pattern = '/^' . str_replace($placeholder, '.*', $escaped) . '$/';
                if (preg_match($pattern, $service)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get allowed services list
     */
    public function getAllowedServices(): array
    {
        return $this->allowedServices;
    }

    /**
     * List running services from allowed list
     */
    public function getRunningServices(): array
    {
        $running = [];

        foreach ($this->allowedServices as $service) {
            // Skip wildcards
            if (str_contains($service, '*')) {
                continue;
            }

            if ($this->isRunning($service)) {
                $running[] = $service;
            }
        }

        return $running;
    }

    /**
     * Batch restart multiple services
     */
    public function restartMultiple(array $services): array
    {
        $results = [];

        foreach ($services as $service) {
            $results[$service] = $this->restart($service)->toArray();
        }

        return $results;
    }
}
