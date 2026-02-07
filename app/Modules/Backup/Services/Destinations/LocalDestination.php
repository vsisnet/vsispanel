<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services\Destinations;

class LocalDestination implements BackupDestinationInterface
{
    public function __construct(
        private readonly string $path
    ) {}

    public function getRepositoryUrl(): string
    {
        return $this->path;
    }

    public function getEnvironmentVariables(): array
    {
        return [];
    }

    public function validate(): bool
    {
        // Check if path is valid
        if (empty($this->path)) {
            return false;
        }

        // Check if directory exists or can be created
        if (!is_dir($this->path)) {
            return @mkdir($this->path, 0755, true);
        }

        return is_writable($this->path);
    }

    public function getType(): string
    {
        return 'local';
    }

    public function initializeRepository(string $password): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'error' => 'Cannot write to local path: ' . $this->path,
            ];
        }

        return ['success' => true];
    }

    public function repositoryExists(): bool
    {
        return is_dir($this->path . '/data');
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            path: $config['path'] ?? '/var/backups/vsispanel'
        );
    }
}
