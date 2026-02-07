<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services\Destinations;

class FtpDestination implements BackupDestinationInterface
{
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly string $path = '/backups',
        private readonly int $port = 21,
        private readonly bool $useSftp = false
    ) {}

    public function getRepositoryUrl(): string
    {
        $protocol = $this->useSftp ? 'sftp' : 'ftp';
        $path = trim($this->path, '/');
        return "{$protocol}://{$this->host}:{$this->port}/{$path}";
    }

    public function getEnvironmentVariables(): array
    {
        return [
            'RESTIC_FTP_USER' => $this->username,
            'RESTIC_FTP_PASSWORD' => $this->password,
        ];
    }

    public function validate(): bool
    {
        return !empty($this->host)
            && !empty($this->username)
            && !empty($this->password)
            && $this->port > 0
            && $this->port < 65536;
    }

    public function getType(): string
    {
        return 'ftp';
    }

    public function initializeRepository(string $password): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'error' => 'Invalid FTP configuration',
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
            host: $config['host'] ?? '',
            username: $config['username'] ?? '',
            password: $config['password'] ?? '',
            path: $config['path'] ?? '/backups',
            port: $config['port'] ?? 21,
            useSftp: $config['use_sftp'] ?? false
        );
    }
}
