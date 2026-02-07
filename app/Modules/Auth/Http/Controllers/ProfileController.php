<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Requests\UpdatePasswordRequest;
use App\Modules\Auth\Http\Requests\UpdateProfileRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Get current authenticated user info.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
            'message' => '',
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()),
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'Current password is incorrect.',
                ],
            ], 422);
        }

        $user->update([
            'password' => $request->new_password,
        ]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => 'Password updated successfully.',
        ]);
    }
}
