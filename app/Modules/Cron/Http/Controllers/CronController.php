<?php

declare(strict_types=1);

namespace App\Modules\Cron\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cron\Models\CronJob;
use App\Modules\Cron\Services\CronService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function __construct(
        private CronService $cronService,
    ) {}

    /**
     * GET /api/v1/cron-jobs - List cron jobs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CronJob::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->user()->role === 'admin') {
            $query = CronJob::with('user')->orderBy('created_at', 'desc');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(function ($job) {
                $job->schedule_human = $this->cronService->toHumanReadable($job->schedule);
                return $job;
            }),
        ]);
    }

    /**
     * POST /api/v1/cron-jobs - Create cron job.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:1000',
            'schedule' => ['required', 'string', function ($attr, $value, $fail) {
                if (!$this->cronService->isValidExpression($value)) {
                    $fail('Invalid cron expression.');
                }
            }],
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'output_handling' => 'string|in:discard,email,log',
            'output_email' => 'nullable|email',
            'log_path' => 'nullable|string|max:500',
        ]);

        $job = $this->cronService->create($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $job,
            'message' => 'Cron job created',
        ], 201);
    }

    /**
     * GET /api/v1/cron-jobs/{job} - Show cron job.
     */
    public function show(CronJob $cronJob): JsonResponse
    {
        $cronJob->schedule_human = $this->cronService->toHumanReadable($cronJob->schedule);
        $cronJob->next_runs = $this->cronService->getNextRuns($cronJob->schedule);

        return response()->json([
            'success' => true,
            'data' => $cronJob,
        ]);
    }

    /**
     * PUT /api/v1/cron-jobs/{job} - Update cron job.
     */
    public function update(Request $request, CronJob $cronJob): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'sometimes|string|max:1000',
            'schedule' => ['sometimes', 'string', function ($attr, $value, $fail) {
                if (!$this->cronService->isValidExpression($value)) {
                    $fail('Invalid cron expression.');
                }
            }],
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'output_handling' => 'sometimes|string|in:discard,email,log',
            'output_email' => 'nullable|email',
            'log_path' => 'nullable|string|max:500',
        ]);

        $job = $this->cronService->update($cronJob, $validated);

        return response()->json([
            'success' => true,
            'data' => $job,
            'message' => 'Cron job updated',
        ]);
    }

    /**
     * DELETE /api/v1/cron-jobs/{job} - Delete cron job.
     */
    public function destroy(CronJob $cronJob): JsonResponse
    {
        $this->cronService->delete($cronJob);

        return response()->json([
            'success' => true,
            'message' => 'Cron job deleted',
        ]);
    }

    /**
     * POST /api/v1/cron-jobs/{job}/toggle - Toggle active state.
     */
    public function toggle(CronJob $cronJob): JsonResponse
    {
        $job = $this->cronService->toggle($cronJob);

        return response()->json([
            'success' => true,
            'data' => $job,
            'message' => $job->is_active ? 'Cron job enabled' : 'Cron job disabled',
        ]);
    }

    /**
     * POST /api/v1/cron-jobs/{job}/run-now - Execute immediately.
     */
    public function runNow(CronJob $cronJob): JsonResponse
    {
        $output = $this->cronService->runNow($cronJob);

        return response()->json([
            'success' => true,
            'data' => [
                'output' => $output,
                'status' => $cronJob->fresh()->last_run_status,
            ],
            'message' => 'Cron job executed',
        ]);
    }

    /**
     * GET /api/v1/cron-jobs/{job}/output - Get last output.
     */
    public function output(CronJob $cronJob): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'output' => $cronJob->last_run_output,
                'status' => $cronJob->last_run_status,
                'run_at' => $cronJob->last_run_at,
            ],
        ]);
    }

    /**
     * POST /api/v1/cron-jobs/validate - Validate cron expression.
     */
    public function validateExpression(Request $request): JsonResponse
    {
        $request->validate(['expression' => 'required|string']);

        $isValid = $this->cronService->isValidExpression($request->expression);
        $nextRuns = $isValid ? $this->cronService->getNextRuns($request->expression) : [];
        $human = $isValid ? $this->cronService->toHumanReadable($request->expression) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $isValid,
                'human_readable' => $human,
                'next_runs' => $nextRuns,
            ],
        ]);
    }
}
