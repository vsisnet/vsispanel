<?php

declare(strict_types=1);

namespace App\Modules\Backup\Console\Commands;

use App\Modules\Backup\Jobs\BackupJob;
use App\Modules\Backup\Models\Backup;
use App\Modules\Backup\Models\BackupConfig;
use App\Modules\Task\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledBackups extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:run-scheduled {--force : Force run all active configs regardless of schedule}';

    /**
     * The console command description.
     */
    protected $description = 'Run scheduled backup jobs that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');

        if ($force) {
            $configs = BackupConfig::where('is_active', true)->get();
            $this->info("Force mode: Found {$configs->count()} active backup configs.");
        } else {
            // Find all due backup configs
            $configs = BackupConfig::due()->get();
            $this->info("Found {$configs->count()} backup configs due for execution.");
        }

        if ($configs->isEmpty()) {
            $this->info('No backup configs are due. Exiting.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($configs as $config) {
            // Check if there's already a running backup for this config
            $runningBackup = Backup::where('backup_config_id', $config->id)
                ->whereIn('status', [Backup::STATUS_PENDING, Backup::STATUS_RUNNING])
                ->exists();

            if ($runningBackup) {
                $this->warn("Skipping '{$config->name}' - backup already in progress.");
                Log::info('Skipping scheduled backup - already in progress', [
                    'backup_config_id' => $config->id,
                    'config_name' => $config->name,
                ]);

                // Still update next_run_at so scheduler doesn't keep retrying every minute
                $config->updateNextRunAt();

                $skipped++;
                continue;
            }

            $this->info("Dispatching backup job for '{$config->name}'...");

            try {
                // Create backup record
                $backup = Backup::create([
                    'user_id' => $config->user_id,
                    'backup_config_id' => $config->id,
                    'type' => $config->type,
                    'status' => Backup::STATUS_PENDING,
                    'metadata' => [
                        'triggered_by' => 'scheduler',
                        'schedule' => $config->schedule,
                        'scheduled_at' => now()->toISOString(),
                    ],
                ]);

                // Create task for tracking
                $task = Task::create([
                    'user_id' => $config->user_id,
                    'type' => 'backup',
                    'name' => "Scheduled Backup: {$config->name}",
                    'status' => 'pending',
                    'progress' => 0,
                    'metadata' => [
                        'backup_id' => $backup->id,
                        'backup_config_id' => $config->id,
                        'triggered_by' => 'scheduler',
                    ],
                ]);

                // Dispatch backup job
                BackupJob::dispatch($backup, $task->id);

                // Update last_run_at and calculate next_run_at
                $config->update(['last_run_at' => now()]);
                $config->updateNextRunAt();

                Log::info('Scheduled backup job dispatched', [
                    'backup_config_id' => $config->id,
                    'config_name' => $config->name,
                    'backup_id' => $backup->id,
                    'task_id' => $task->id,
                    'next_run_at' => $config->fresh()->next_run_at,
                ]);

                $dispatched++;
            } catch (\Exception $e) {
                $this->error("Failed to dispatch backup for '{$config->name}': {$e->getMessage()}");
                Log::error('Failed to dispatch scheduled backup', [
                    'backup_config_id' => $config->id,
                    'config_name' => $config->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Completed: {$dispatched} jobs dispatched, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
