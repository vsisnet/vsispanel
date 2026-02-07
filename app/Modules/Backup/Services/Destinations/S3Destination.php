<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services\Destinations;

class S3Destination implements BackupDestinationInterface
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $path,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region = 'us-east-1',
        private readonly ?string $endpoint = null
    ) {}

    public function getRepositoryUrl(): string
    {
        $path = trim($this->path, '/');
        return "s3:{$this->bucket}/{$path}";
    }

    public function getEnvironmentVariables(): array
    {
        $env = [
            'AWS_ACCESS_KEY_ID' => $this->accessKey,
            'AWS_SECRET_ACCESS_KEY' => $this->secretKey,
            'AWS_DEFAULT_REGION' => $this->region,
        ];

        if ($this->endpoint) {
            // For S3-compatible storage (MinIO, DigitalOcean Spaces, etc.)
            $env['RESTIC_REPOSITORY_S3_ENDPOINT'] = $this->endpoint;
        }

        return $env;
    }

    public function validate(): bool
    {
        return !empty($this->bucket)
            && !empty($this->accessKey)
            && !empty($this->secretKey)
            && !empty($this->region);
    }

    public function getType(): string
    {
        return 's3';
    }

    public function initializeRepository(string $password): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'error' => 'Invalid S3 configuration',
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
            bucket: $config['bucket'] ?? '',
            path: $config['path'] ?? 'backups',
            accessKey: $config['access_key'] ?? '',
            secretKey: $config['secret_key'] ?? '',
            region: $config['region'] ?? 'us-east-1',
            endpoint: $config['endpoint'] ?? null
        );
    }
}
