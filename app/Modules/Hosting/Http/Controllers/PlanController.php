<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hosting\Http\Requests\CreatePlanRequest;
use App\Modules\Hosting\Http\Requests\UpdatePlanRequest;
use App\Modules\Hosting\Http\Resources\PlanResource;
use App\Modules\Hosting\Http\Resources\PlanCollection;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(
        protected PlanService $planService
    ) {}

    /**
     * List all plans (admin only).
     */
    public function index(Request $request): PlanCollection
    {
        $this->authorize('viewAny', Plan::class);

        $query = Plan::query();

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $plans = $query->withCount('subscriptions')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return new PlanCollection($plans);
    }

    /**
     * Get available plans for subscription.
     */
    public function available(): JsonResponse
    {
        $plans = $this->planService->getAvailablePlans();

        return response()->json([
            'success' => true,
            'data' => PlanResource::collection($plans),
        ]);
    }

    /**
     * Create a new plan (admin only).
     */
    public function store(CreatePlanRequest $request): JsonResponse
    {
        $this->authorize('create', Plan::class);

        try {
            $plan = $this->planService->createPlan(
                $request->user(),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully.',
                'data' => new PlanResource($plan),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAN_CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get a specific plan (admin only for full details).
     */
    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        $plan->loadCount('subscriptions');

        return response()->json([
            'success' => true,
            'data' => new PlanResource($plan),
        ]);
    }

    /**
     * Update a plan (admin only).
     */
    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        try {
            $plan = $this->planService->updatePlan($plan, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully.',
                'data' => new PlanResource($plan),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAN_UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Delete a plan (admin only).
     */
    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        try {
            $this->planService->deletePlan($plan);

            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAN_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Activate a plan (admin only).
     */
    public function activate(Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $plan = $this->planService->activatePlan($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan activated successfully.',
            'data' => new PlanResource($plan),
        ]);
    }

    /**
     * Deactivate a plan (admin only).
     */
    public function deactivate(Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $plan = $this->planService->deactivatePlan($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan deactivated successfully.',
            'data' => new PlanResource($plan),
        ]);
    }

    /**
     * Clone a plan (admin only).
     */
    public function clone(Request $request, Plan $plan): JsonResponse
    {
        $this->authorize('create', Plan::class);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $newPlan = $this->planService->clonePlan(
                $plan,
                $request->name,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Plan cloned successfully.',
                'data' => new PlanResource($newPlan),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAN_CLONE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
