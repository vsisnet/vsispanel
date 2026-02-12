<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services\Migrators;

use App\Modules\Migration\Models\MigrationJob;

class DirectAdminMigrator extends BaseMigrator
{
    public function testConnection(array $credentials): array
    {
        // For now, use SSH to test connection
        $result = $this->sshExec($credentials, 'echo "ok"', 15);

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? 'DirectAdmin server connection successful via SSH'
                : 'Connection failed: ' . ($result['stderr'] ?: 'Unknown error'),
        ];
    }

    public function discover(array $credentials): array
    {
        // Use SSH migrator for discovery
        $sshMigrator = new SshMigrator();
        $data = $sshMigrator->discover($credentials);
        $data['server_type'] = 'directadmin';
        return $data;
    }

    public function migrate(MigrationJob $job): void
    {
        // Delegate to SSH migrator for now
        $sshMigrator = new SshMigrator();
        $sshMigrator->migrate($job);
    }
}
