<?php

declare(strict_types=1);

namespace App\Modules\Migration\Services\Migrators;

use App\Modules\Migration\Models\MigrationJob;
use App\Modules\Migration\Services\MigratorInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

abstract class BaseMigrator implements MigratorInterface
{
    protected int $sshTimeout = 30;
    protected int $rsyncTimeout = 3600;

    /**
     * Build SSH command prefix.
     */
    protected function sshCommand(array $credentials): array
    {
        $cmd = ['ssh', '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=10'];

        if (!empty($credentials['port']) && (int) $credentials['port'] !== 22) {
            $cmd[] = '-p';
            $cmd[] = (string) $credentials['port'];
        }

        if (!empty($credentials['private_key'])) {
            $keyFile = tempnam(sys_get_temp_dir(), 'mig_key_');
            file_put_contents($keyFile, $credentials['private_key']);
            chmod($keyFile, 0600);
            $cmd[] = '-i';
            $cmd[] = $keyFile;
        } elseif (!empty($credentials['password'])) {
            // Use sshpass for password auth
            array_unshift($cmd, 'sshpass', '-p', $credentials['password']);
        }

        $user = $credentials['username'] ?? 'root';
        $host = $credentials['host'];
        $cmd[] = "{$user}@{$host}";

        return $cmd;
    }

    /**
     * Execute SSH command on remote server.
     */
    protected function sshExec(array $credentials, string $remoteCommand, int $timeout = 30): array
    {
        $cmd = $this->sshCommand($credentials);
        $cmd[] = $remoteCommand;

        $process = new Process($cmd);
        $process->setTimeout($timeout);

        try {
            $process->run();
            return [
                'success' => $process->isSuccessful(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
                'exitCode' => $process->getExitCode(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stdout' => '',
                'stderr' => $e->getMessage(),
                'exitCode' => -1,
            ];
        } finally {
            // Cleanup temp key files
            foreach ($cmd as $arg) {
                if (str_starts_with($arg, sys_get_temp_dir() . '/mig_key_')) {
                    @unlink($arg);
                }
            }
        }
    }

    /**
     * Rsync files from remote server.
     */
    protected function rsyncFrom(array $credentials, string $remotePath, string $localPath, ?MigrationJob $job = null): bool
    {
        $user = $credentials['username'] ?? 'root';
        $host = $credentials['host'];
        $port = $credentials['port'] ?? 22;

        $cmd = ['rsync', '-avz', '--progress', '--delete'];

        $sshCmd = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -p {$port}";

        if (!empty($credentials['private_key'])) {
            $keyFile = tempnam(sys_get_temp_dir(), 'mig_key_');
            file_put_contents($keyFile, $credentials['private_key']);
            chmod($keyFile, 0600);
            $sshCmd .= " -i {$keyFile}";
        } elseif (!empty($credentials['password'])) {
            $sshCmd = "sshpass -p " . escapeshellarg($credentials['password']) . " " . $sshCmd;
        }

        $cmd[] = '-e';
        $cmd[] = $sshCmd;
        $cmd[] = "{$user}@{$host}:{$remotePath}";
        $cmd[] = $localPath;

        $process = new Process($cmd);
        $process->setTimeout($this->rsyncTimeout);

        try {
            $process->run(function ($type, $buffer) use ($job) {
                if ($job && $type === Process::OUT) {
                    // Don't log every rsync line, just track it
                    Log::channel('commands')->debug('rsync: ' . trim($buffer));
                }
            });

            return $process->isSuccessful();
        } catch (\Exception $e) {
            $job?->appendLog("rsync error: {$e->getMessage()}");
            return false;
        } finally {
            if (isset($keyFile)) {
                @unlink($keyFile);
            }
        }
    }

    /**
     * Import a MySQL database from a SQL file.
     */
    protected function importDatabase(string $dbName, string $sqlFile, ?MigrationJob $job = null): bool
    {
        // Create database if not exists
        $process = new Process(['mysql', '-e', "CREATE DATABASE IF NOT EXISTS `{$dbName}`"]);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $job?->appendLog("Failed to create database {$dbName}: {$process->getErrorOutput()}");
            return false;
        }

        // Import SQL file
        $process = Process::fromShellCommandline("mysql `{$dbName}` < " . escapeshellarg($sqlFile));
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $job?->appendLog("Failed to import database {$dbName}: {$process->getErrorOutput()}");
            return false;
        }

        $job?->appendLog("Database {$dbName} imported successfully");
        return true;
    }

    /**
     * Create a VSISPanel domain using DomainService.
     */
    protected function createDomain(string $domainName, string $userId, ?MigrationJob $job = null): ?object
    {
        try {
            $domainService = app(\App\Modules\Domain\Services\DomainService::class);
            $user = \App\Modules\Auth\Models\User::findOrFail($userId);

            $domain = $domainService->create($user, [
                'name' => $domainName,
                'php_version' => config('webserver.default_php_version', '8.3'),
                'is_main' => false,
                'create_dns' => true,
            ]);

            $job?->appendLog("Domain {$domainName} created in VSISPanel");
            return $domain;
        } catch (\Exception $e) {
            $job?->appendLog("Failed to create domain {$domainName}: {$e->getMessage()}");
            return null;
        }
    }
}
