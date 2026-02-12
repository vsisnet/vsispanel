<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Http\Requests\CreateUserRequest;
use App\Modules\Auth\Http\Requests\UpdateUserRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all users with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $authUser = $request->user();
        $query = User::query();

        // Scoping: reseller sees only their customers
        if ($authUser->isReseller()) {
            $query->where('parent_id', $authUser->id);
        }

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'message' => '',
        ]);
    }

    /**
     * Get user statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $authUser = $request->user();

        if ($authUser->isAdmin()) {
            $stats = [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'resellers' => User::where('role', 'reseller')->count(),
                'users' => User::where('role', 'user')->count(),
            ];
        } else {
            // Reseller: only their customers
            $base = User::where('parent_id', $authUser->id);
            $stats = [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'suspended' => (clone $base)->where('status', 'suspended')->count(),
                'admins' => 0,
                'resellers' => 0,
                'users' => (clone $base)->where('role', 'user')->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => '',
        ]);
    }

    /**
     * Get a single user.
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => '',
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $authUser = $request->user();

        // Resellers can only create 'user' role
        if ($authUser->isReseller()) {
            $data['role'] = 'user';
            $data['parent_id'] = $authUser->id;
        }

        $user = User::create($data);

        // Assign Spatie role
        $roleName = $data['role'] ?? 'user';
        $user->assignRole($roleName);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => 'User created successfully.',
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $authUser = $request->user();

        // Resellers cannot change role to admin/reseller
        if ($authUser->isReseller() && isset($data['role']) && in_array($data['role'], ['admin', 'reseller'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You cannot assign this role.',
                ],
            ], 403);
        }

        $user->update($data);

        // Sync Spatie role if role changed
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()),
            'message' => 'User updated successfully.',
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_SELF',
                    'message' => 'You cannot delete your own account.',
                ],
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Suspend a user.
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', $user);

        // Prevent suspending yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_SUSPEND_SELF',
                    'message' => 'You cannot suspend your own account.',
                ],
            ], 403);
        }

        $user->update(['status' => 'suspended']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()),
            'message' => 'User suspended successfully.',
        ]);
    }

    /**
     * Unsuspend a user.
     */
    public function unsuspend(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', $user);

        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()),
            'message' => 'User unsuspended successfully.',
        ]);
    }

    /**
     * Impersonate a user.
     */
    public function impersonate(Request $request, User $user): JsonResponse
    {
        $this->authorize('impersonate', $user);

        $token = $user->createToken('impersonation', ['*'])->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
                'original_user_id' => $request->user()->id,
            ],
            'message' => "Now viewing as {$user->name}.",
        ]);
    }

    /**
     * Get all users for dropdown selection (minimal data).
     */
    public function listForSelect(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $query = User::query()
            ->where('status', 'active')
            ->select(['id', 'name', 'email', 'username', 'role']);

        // Scoping
        if ($authUser->isReseller()) {
            $query->where('parent_id', $authUser->id);
        } elseif ($authUser->isUser()) {
            $query->where('id', $authUser->id);
        }

        // Optional role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Search filter for autocomplete
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role,
                'label' => "{$user->name} ({$user->email})",
            ]),
            'message' => '',
        ]);
    }
}
