<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services\Destinations;

class B2Destination implements BackupDestinationInterface
{
    public function __construct(
        private readonly string $accountId,
        private readonly string $applicationKey,
        private readonly string $bucketName,
        private readonly string $path = 'backups'
    ) {}

    public function getRepositoryUrl(): string
    {
        $path = trim($this->path, '/');
        return "b2:{$this->bucketName}:{$path}";
    }

    public function getEnvironmentVariables(): array
    {
        return [
            'B2_ACCOUNT_ID' => $this->accountId,
            'B2_ACCOUNT_KEY' => $this->applicationKey,
        ];
    }

    public function validate(): bool
    {
        return !empty($this->accountId)
            && !empty($this->applicationKey)
            && !empty($this->bucketName);
    }

    public function getType(): string
    {
        return 'b2';
    }

    public function initializeRepository(string $password): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'error' => 'Invalid Backblaze B2 configuration',
            ];
        }

        return ['success' => true];
    }

    public function repositoryExists(): bool
    {
        // Will be checked during restic operations
        return true;
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            accountId: $config['account_id'] ?? '',
            applicationKey: $config['application_key'] ?? '',
            bucketName: $config['bucket_name'] ?? '',
            path: $config['path'] ?? 'backups'
        );
    }
}
