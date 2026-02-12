<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services;

use App\Modules\Migration\Models\MigrationJob;
use App\Modules\Migration\Services\Migrators\AaPanelMigrator;
use App\Modules\Migration\Services\Migrators\CpanelMigrator;
use App\Modules\Migration\Services\Migrators\DirectAdminMigrator;
use App\Modules\Migration\Services\Migrators\PleskMigrator;
use App\Modules\Migration\Services\Migrators\SshMigrator;

class MigrationService
{
    /**
     * Get the appropriate migrator for a source type.
     */
    public function getMigrator(string $sourceType): MigratorInterface
    {
        return match ($sourceType) {
            'plesk' => new PleskMigrator(),
            'cpanel' => new CpanelMigrator(),
            'aapanel' => new AaPanelMigrator(),
            'directadmin' => new DirectAdminMigrator(),
            'ssh' => new SshMigrator(),
            default => throw new \InvalidArgumentException("Unknown source type: {$sourceType}"),
        };
    }

    /**
     * Test connection to source server.
     */
    public function testConnection(string $sourceType, array $credentials): array
    {
        return $this->getMigrator($sourceType)->testConnection($credentials);
    }

    /**
     * Discover available resources on source server.
     */
    public function discover(string $sourceType, array $credentials): array
    {
        return $this->getMigrator($sourceType)->discover($credentials);
    }

    /**
     * Create and queue a migration job.
     */
    public function createJob(array $data): MigrationJob
    {
        $job = MigrationJob::create([
            'user_id' => $data['user_id'],
            'source_type' => $data['source_type'],
            'source_host' => $data['source_host'],
            'source_port' => $data['source_port'] ?? 22,
            'source_credentials' => $data['credentials'],
            'items' => $data['items'] ?? [],
            'discovered_data' => $data['discovered_data'] ?? [],
            'status' => 'pending',
            'progress' => 0,
        ]);

        // Dispatch the job
        \App\Modules\Migration\Jobs\RunMigrationJob::dispatch($job)
            ->onQueue('installs');

        return $job;
    }

    /**
     * Execute a migration job (called from queue worker).
     */
    public function executeJob(MigrationJob $job): void
    {
        $job->markRunning();

        try {
            $migrator = $this->getMigrator($job->source_type);
            $migrator->migrate($job);
            $job->markCompleted();
        } catch (\Exception $e) {
            $job->markFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel a running migration job.
     */
    public function cancelJob(MigrationJob $job): void
    {
        if ($job->isRunning() || $job->isPending()) {
            $job->markCancelled();
        }
    }
}
