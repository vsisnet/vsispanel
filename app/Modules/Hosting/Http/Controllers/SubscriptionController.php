<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Http\Requests\CreateSubscriptionRequest;
use App\Modules\Hosting\Http\Resources\SubscriptionResource;
use App\Modules\Hosting\Http\Resources\SubscriptionCollection;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\Hosting\Services\QuotaService;
use App\Modules\Hosting\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected QuotaService $quotaService
    ) {}

    /**
     * List all subscriptions (admin).
     */
    public function index(Request $request): SubscriptionCollection
    {
        $this->authorize('viewAny', Subscription::class);

        $query = Subscription::with(['user', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('expiring')) {
            $query->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays((int) $request->expiring)]);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return new SubscriptionCollection($subscriptions);
    }

    /**
     * Get current user's subscription.
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $this->quotaService->getActiveSubscription($request->user());

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active subscription.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Get current user's quota usage.
     */
    public function quota(Request $request): JsonResponse
    {
        $usage = $this->quotaService->getQuotaUsage($request->user());

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }

    /**
     * Create a subscription (admin).
     */
    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $validated = $request->validated();

        try {
            $user = User::findOrFail($validated['user_id']);
            $plan = Plan::findOrFail($validated['plan_id']);

            $expiresAt = !empty($validated['expires_at'])
                ? Carbon::parse($validated['expires_at'])
                : null;

            $subscription = $this->subscriptionService->createSubscription(
                $user,
                $plan,
                $expiresAt
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => new SubscriptionResource($subscription),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get a specific subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        $subscription->load(['user', 'plan', 'domains']);

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Change subscription plan (admin).
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);
            $subscription = $this->subscriptionService->changePlan($subscription, $plan);

            return response()->json([
                'success' => true,
                'message' => 'Plan changed successfully.',
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAN_CHANGE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Suspend a subscription (admin).
     */
    public function suspend(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $subscription = $this->subscriptionService->suspendSubscription(
                $subscription,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription suspended successfully.',
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUSPEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Unsuspend a subscription (admin).
     */
    public function unsuspend(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        try {
            $subscription = $this->subscriptionService->unsuspendSubscription($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Subscription unsuspended successfully.',
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNSUSPEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Cancel a subscription (admin).
     */
    public function cancel(Subscription $subscription): JsonResponse
    {
        $this->authorize('delete', $subscription);

        try {
            $subscription = $this->subscriptionService->cancelSubscription($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully.',
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANCEL_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Renew a subscription (admin).
     */
    public function renew(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        try {
            $subscription = $this->subscriptionService->renewSubscription(
                $subscription,
                $validated['months']
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription renewed successfully.',
                'data' => new SubscriptionResource($subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RENEW_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Get subscription statistics (admin).
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $stats = $this->subscriptionService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
