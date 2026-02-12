<?php

declare(strict_types=1);

namespace App\Modules\Task\Console\Commands;

use App\Modules\Backup\Models\Backup;
use App\Modules\Task\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStuckTasks extends Command
{
    protected $signature = 'task:cleanup-stuck {--hours=2 : Hours after which a running task is considered stuck}';
    protected $description = 'Auto-fail tasks and backups that have been stuck (running) for too long';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        // Find stuck tasks
        $stuckTasks = Task::where('status', Task::STATUS_RUNNING)
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stuckTasks->isEmpty()) {
            $this->info('No stuck tasks found.');
        } else {
            $this->info("Found {$stuckTasks->count()} stuck task(s).");

            foreach ($stuckTasks as $task) {
                $duration = $task->started_at->diffForHumans(now(), true);
                $message = "Auto-cancelled: task stuck for {$duration}";

                $task->markAsFailed($message);

                $this->warn("Failed task {$task->id}: {$task->name} (stuck {$duration})");
                Log::warning('Auto-failed stuck task', [
                    'task_id' => $task->id,
                    'type' => $task->type,
                    'name' => $task->name,
                    'started_at' => $task->started_at,
                    'duration' => $duration,
                ]);
            }
        }

        // Find stuck backups (running for too long)
        $stuckBackups = Backup::where('status', Backup::STATUS_RUNNING)
            ->where('started_at', '<', $cutoff)
            ->get();

        if ($stuckBackups->isEmpty()) {
            $this->info('No stuck backups found.');
        } else {
            $this->info("Found {$stuckBackups->count()} stuck backup(s).");

            foreach ($stuckBackups as $backup) {
                $duration = $backup->started_at->diffForHumans(now(), true);
                $backup->markAsFailed("Auto-cancelled: backup stuck for {$duration}");

                $this->warn("Failed backup {$backup->id} (stuck {$duration})");
                Log::warning('Auto-failed stuck backup', [
                    'backup_id' => $backup->id,
                    'started_at' => $backup->started_at,
                    'duration' => $duration,
                ]);
            }
        }

        // Also handle pending tasks that never started (older than hours*2)
        $stalePendingCutoff = now()->subHours($hours * 2);
        $stalePending = Task::where('status', Task::STATUS_PENDING)
            ->where('created_at', '<', $stalePendingCutoff)
            ->get();

        if (!$stalePending->isEmpty()) {
            $this->info("Found {$stalePending->count()} stale pending task(s).");
            foreach ($stalePending as $task) {
                $task->markAsFailed('Auto-cancelled: task was pending too long and never started');
                $this->warn("Failed stale pending task {$task->id}: {$task->name}");
            }
        }

        return self::SUCCESS;
    }
}
