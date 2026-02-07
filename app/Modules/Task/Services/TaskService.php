<?php

declare(strict_types=1);

namespace App\Modules\Task\Services;

use App\Modules\Task\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskService
{
    /**
     * Create a new task
     */
    public function create(
        string $type,
        string $name,
        ?string $description = null,
        ?array $inputData = null,
        ?string $relatedType = null,
        ?string $relatedId = null,
        ?array $metadata = null
    ): Task {
        $task = Task::create([
            'user_id' => Auth::id(),
            'type' => $type,
            'name' => $name,
            'description' => $description,
            'status' => Task::STATUS_PENDING,
            'progress' => 0,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'input_data' => $inputData,
            'metadata' => $metadata,
        ]);

        Log::info('Task created', [
            'task_id' => $task->id,
            'type' => $type,
            'name' => $name,
        ]);

        return $task;
    }

    /**
     * Start a task
     */
    public function start(Task $task): void
    {
        $task->markAsRunning();

        Log::info('Task started', [
            'task_id' => $task->id,
            'type' => $task->type,
        ]);
    }

    /**
     * Update task progress
     */
    public function updateProgress(Task $task, int $progress, ?string $message = null): void
    {
        $output = $message ? "[" . now()->format('H:i:s') . "] {$message}\n" : null;
        $task->updateProgress($progress, $output);
    }

    /**
     * Append output to task
     */
    public function appendOutput(Task $task, string $message): void
    {
        $task->appendOutput("[" . now()->format('H:i:s') . "] {$message}\n");
    }

    /**
     * Complete a task
     */
    public function complete(Task $task, ?string $message = null): void
    {
        $output = $message ? "[" . now()->format('H:i:s') . "] {$message}\n" : null;
        $task->markAsCompleted($output);

        Log::info('Task completed', [
            'task_id' => $task->id,
            'type' => $task->type,
            'duration' => $task->duration,
        ]);
    }

    /**
     * Fail a task
     */
    public function fail(Task $task, string $errorMessage, ?string $output = null): void
    {
        $finalOutput = $output ? "[" . now()->format('H:i:s') . "] ERROR: {$output}\n" : null;
        $task->markAsFailed($errorMessage, $finalOutput);

        Log::error('Task failed', [
            'task_id' => $task->id,
            'type' => $task->type,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Cancel a task
     */
    public function cancel(Task $task): bool
    {
        if (!$task->canBeCancelled()) {
            return false;
        }

        $task->markAsCancelled();

        Log::info('Task cancelled', [
            'task_id' => $task->id,
            'type' => $task->type,
        ]);

        return true;
    }

    /**
     * Get active tasks
     */
    public function getActiveTasks(?string $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Task::active()->orderBy('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get recent tasks
     */
    public function getRecentTasks(int $limit = 20, ?string $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Task::orderBy('created_at', 'desc')->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get tasks by type
     */
    public function getTasksByType(string $type, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return Task::where('type', $type)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get tasks by related entity
     */
    public function getTasksByRelated(string $relatedType, string $relatedId): \Illuminate\Database\Eloquent\Collection
    {
        return Task::where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get task statistics
     */
    public function getStatistics(): array
    {
        $today = now()->startOfDay();

        return [
            'total' => Task::count(),
            'active' => Task::active()->count(),
            'pending' => Task::pending()->count(),
            'running' => Task::running()->count(),
            'completed_today' => Task::completed()
                ->where('completed_at', '>=', $today)
                ->count(),
            'failed_today' => Task::failed()
                ->where('completed_at', '>=', $today)
                ->count(),
        ];
    }

    /**
     * Clean up old completed tasks
     */
    public function cleanupOldTasks(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        $count = Task::whereIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
            ->where('completed_at', '<', $cutoffDate)
            ->delete();

        Log::info('Cleaned up old tasks', ['count' => $count, 'days_kept' => $daysToKeep]);

        return $count;
    }

    /**
     * Retry a failed task (creates a new task with same parameters)
     */
    public function retry(Task $task): ?Task
    {
        if ($task->status !== Task::STATUS_FAILED) {
            return null;
        }

        return $this->create(
            $task->type,
            $task->name,
            $task->description,
            $task->input_data,
            $task->related_type,
            $task->related_id,
            array_merge($task->metadata ?? [], ['retry_of' => $task->id])
        );
    }
}
