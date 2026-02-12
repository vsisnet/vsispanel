<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services;

use App\Modules\Migration\Models\MigrationJob;

interface MigratorInterface
{
    /**
     * Test connection to the source server.
     * Returns ['success' => bool, 'message' => string]
     */
    public function testConnection(array $credentials): array;

    /**
     * Discover available accounts, domains, databases, etc.
     * Returns ['domains' => [...], 'databases' => [...], 'emails' => [...], 'crons' => [...]]
     */
    public function discover(array $credentials): array;

    /**
     * Execute the migration job.
     */
    public function migrate(MigrationJob $job): void;
}
