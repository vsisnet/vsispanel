<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Reseller\Services\ResellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResellerCustomerController extends Controller
{
    public function __construct(
        private readonly ResellerService $resellerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $reseller = $user->isAdmin() && $request->has('reseller_id')
            ? User::findOrFail($request->reseller_id)
            : $user;

        $customers = $this->resellerService->listCustomers($reseller, (int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|string|max:32|unique:users,username|alpha_dash',
            'password' => 'required|string|min:8',
        ]);

        try {
            $customer = $this->resellerService->createCustomer($user, $validated);

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer created successfully.',
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'LIMIT_REACHED', 'message' => $e->getMessage()],
            ], 422);
        }
    }

    public function show(Request $request, User $customer): JsonResponse
    {
        $user = $request->user();

        if (! $user->canView($customer)) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $customer->load('subscriptions.plan', 'domains');

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function suspend(Request $request, User $customer): JsonResponse
    {
        $user = $request->user();
        $reason = $request->input('reason', '');

        try {
            $customer = $this->resellerService->suspendCustomer($user, $customer, $reason);

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer suspended.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], 403);
        }
    }

    public function unsuspend(Request $request, User $customer): JsonResponse
    {
        $user = $request->user();

        try {
            $customer = $this->resellerService->unsuspendCustomer($user, $customer);

            return response()->json([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer unsuspended.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], 403);
        }
    }

    public function terminate(Request $request, User $customer): JsonResponse
    {
        $user = $request->user();

        try {
            $this->resellerService->terminateCustomer($user, $customer);

            return response()->json([
                'success' => true,
                'message' => 'Customer terminated.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], 403);
        }
    }

    /**
     * Impersonate a customer.
     */
    public function impersonate(Request $request, User $customer): JsonResponse
    {
        $user = $request->user();

        if (! $user->canManage($customer)) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        // Store original user ID in session/token
        $token = $customer->createToken('impersonation', ['*'])->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => $customer,
                'original_user_id' => $user->id,
            ],
            'message' => "Now viewing as {$customer->name}.",
        ]);
    }

    /**
     * Get resource usage for the reseller.
     */
    public function resourceUsage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $usage = $this->resellerService->getResourceUsage($user);

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }
}
