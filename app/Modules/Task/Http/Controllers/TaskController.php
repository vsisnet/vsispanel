<?php

declare(strict_types=1);

namespace App\Modules\Task\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Services\TaskService;
use App\Modules\Task\Http\Resources\TaskResource;
use App\Modules\Task\Http\Resources\TaskCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * Get all tasks with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }

        // Filter by type
        if ($request->has('type')) {
            $types = is_array($request->type) ? $request->type : [$request->type];
            $query->whereIn('type', $types);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $tasks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => TaskResource::collection($tasks),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Get active tasks
     */
    public function active(): JsonResponse
    {
        $tasks = $this->taskService->getActiveTasks();

        return response()->json([
            'success' => true,
            'data' => TaskResource::collection($tasks),
        ]);
    }

    /**
     * Get recent tasks
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $tasks = $this->taskService->getRecentTasks($limit);

        return response()->json([
            'success' => true,
            'data' => TaskResource::collection($tasks),
        ]);
    }

    /**
     * Get task statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->taskService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get a single task
     */
    public function show(Task $task): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new TaskResource($task),
        ]);
    }

    /**
     * Cancel a task
     */
    public function cancel(Task $task): JsonResponse
    {
        if (!$task->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_CANCEL',
                    'message' => 'This task cannot be cancelled',
                ],
            ], 400);
        }

        $this->taskService->cancel($task);

        return response()->json([
            'success' => true,
            'message' => 'Task cancelled successfully',
            'data' => new TaskResource($task->fresh()),
        ]);
    }

    /**
     * Retry a failed task
     */
    public function retry(Task $task): JsonResponse
    {
        if ($task->status !== Task::STATUS_FAILED) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_RETRY',
                    'message' => 'Only failed tasks can be retried',
                ],
            ], 400);
        }

        $newTask = $this->taskService->retry($task);

        if (!$newTask) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RETRY_FAILED',
                    'message' => 'Failed to create retry task',
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task retry created successfully',
            'data' => new TaskResource($newTask),
        ]);
    }

    /**
     * Delete a task
     */
    public function destroy(Task $task): JsonResponse
    {
        if ($task->isActive()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_ACTIVE',
                    'message' => 'Cannot delete active tasks. Cancel first.',
                ],
            ], 400);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * Get task types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Task::getTypes(),
        ]);
    }

    /**
     * Get task output (for live streaming)
     */
    public function output(Task $task, Request $request): JsonResponse
    {
        // Re-fetch to get latest data
        $task->refresh();

        $offset = (int) $request->query('offset', 0);
        $output = $task->output ?? '';
        $totalLen = strlen($output);

        // Return output from offset
        $newOutput = $offset < $totalLen ? substr($output, $offset) : '';

        return response()->json([
            'success' => true,
            'data' => [
                'output' => $newOutput,
                'offset' => $totalLen,
                'status' => $task->status,
                'progress' => (int) $task->progress,
            ],
        ]);
    }

    /**
     * Bulk delete completed/cancelled tasks
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->get('ids', []);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_IDS',
                    'message' => 'No task IDs provided',
                ],
            ], 400);
        }

        $count = Task::whereIn('id', $ids)
            ->whereNotIn('status', [Task::STATUS_PENDING, Task::STATUS_RUNNING])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} tasks deleted successfully",
            'data' => ['deleted_count' => $count],
        ]);
    }

    /**
     * Clean up old tasks
     */
    public function cleanup(Request $request): JsonResponse
    {
        $daysToKeep = $request->get('days', 30);
        $count = $this->taskService->cleanupOldTasks($daysToKeep);

        return response()->json([
            'success' => true,
            'message' => "{$count} old tasks cleaned up",
            'data' => ['deleted_count' => $count],
        ]);
    }
}
