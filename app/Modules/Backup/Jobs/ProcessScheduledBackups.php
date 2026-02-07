<?php

declare(strict_types=1);

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Models\Backup;
use App\Modules\Backup\Models\BackupConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledBackups implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->queue = 'backups';
    }

    public function handle(): void
    {
        // Get all active backup configs that are due
        $configs = BackupConfig::query()
            ->where('is_active', true)
            ->whereNotNull('schedule')
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        foreach ($configs as $config) {
            try {
                // Check if there's already a running backup for this config
                $hasRunningBackup = Backup::query()
                    ->where('backup_config_id', $config->id)
                    ->where('status', Backup::STATUS_RUNNING)
                    ->exists();

                if ($hasRunningBackup) {
                    Log::info('Skipping scheduled backup - already running', [
                        'config_id' => $config->id,
                    ]);
                    continue;
                }

                // Create a new backup record
                $backup = Backup::create([
                    'user_id' => $config->user_id,
                    'backup_config_id' => $config->id,
                    'type' => $config->type,
                    'status' => Backup::STATUS_PENDING,
                ]);

                // Dispatch the backup job
                BackupJob::dispatch($backup);

                // Update next run time
                $config->updateNextRunAt();

                Log::info('Scheduled backup dispatched', [
                    'config_id' => $config->id,
                    'backup_id' => $backup->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to dispatch scheduled backup', [
                    'config_id' => $config->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
