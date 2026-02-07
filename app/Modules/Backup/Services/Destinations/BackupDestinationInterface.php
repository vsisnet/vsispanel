<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services\Destinations;

interface BackupDestinationInterface
{
    /**
     * Get the restic repository URL
     */
    public function getRepositoryUrl(): string;

    /**
     * Get environment variables for restic
     */
    public function getEnvironmentVariables(): array;

    /**
     * Validate the destination configuration
     */
    public function validate(): bool;

    /**
     * Get destination type identifier
     */
    public function getType(): string;

    /**
     * Initialize the repository if needed
     */
    public function initializeRepository(string $password): array;

    /**
     * Check if repository exists
     */
    public function repositoryExists(): bool;
}
