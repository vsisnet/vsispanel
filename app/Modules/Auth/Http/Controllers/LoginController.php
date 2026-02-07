<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\TwoFactorLoginRequest;
use App\Modules\Auth\Http\Resources\LoginResource;
use App\Modules\Auth\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Facades\Activity;

class LoginController extends Controller
{
    /**
     * Handle login request.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:' . $request->ip();

        // Rate limiting: 5 attempts per minute
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->logLoginAttempt($request->email, false, 'Rate limited');

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => "Too many attempts. Please try again in {$seconds} seconds.",
                ],
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->logLoginAttempt($request->email, false, 'Invalid credentials');

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'The provided credentials are incorrect.',
                ],
            ], 401);
        }

        // Check if account is active
        if ($user->status !== 'active') {
            $this->logLoginAttempt($request->email, false, "Account {$user->status}");

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_INACTIVE',
                    'message' => "Your account has been {$user->status}.",
                ],
            ], 403);
        }

        // Check if 2FA is enabled
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            // Return partial response, require 2FA verification
            $tempToken = encrypt([
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'requires_2fa' => true,
                    'temp_token' => $tempToken,
                ],
                'message' => 'Please provide your 2FA code.',
            ]);
        }

        // Generate token and update login info
        RateLimiter::clear($key);
        $token = $this->createTokenAndUpdateLogin($user, $request);

        $this->logLoginAttempt($request->email, true);

        return response()->json([
            'success' => true,
            'data' => new LoginResource($user, $token),
            'message' => 'Login successful.',
        ]);
    }

    /**
     * Verify 2FA code after initial login.
     */
    public function verifyTwoFactor(TwoFactorLoginRequest $request): JsonResponse
    {
        try {
            $data = decrypt($request->temp_token);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Invalid or expired token.',
                ],
            ], 401);
        }

        if ($data['expires_at'] < now()->timestamp) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_EXPIRED',
                    'message' => 'Token has expired. Please login again.',
                ],
            ], 401);
        }

        $user = User::find($data['user_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Verify 2FA code
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $request->code
        );

        if (!$valid) {
            $this->logLoginAttempt($user->email, false, 'Invalid 2FA code');

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_2FA_CODE',
                    'message' => 'Invalid 2FA code.',
                ],
            ], 401);
        }

        $token = $this->createTokenAndUpdateLogin($user, $request);

        $this->logLoginAttempt($user->email, true, '2FA verified');

        return response()->json([
            'success' => true,
            'data' => new LoginResource($user, $token),
            'message' => 'Login successful.',
        ]);
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        Activity::causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('logout');

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Create token and update login info.
     */
    protected function createTokenAndUpdateLogin(User $user, Request $request): string
    {
        $token = $user->createToken('auth-token')->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return $token;
    }

    /**
     * Log login attempt.
     */
    protected function logLoginAttempt(string $email, bool $success, ?string $reason = null): void
    {
        $properties = [
            'email' => $email,
            'success' => $success,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($reason) {
            $properties['reason'] = $reason;
        }

        $user = User::where('email', $email)->first();

        $activity = Activity::withProperties($properties);

        if ($user) {
            $activity->causedBy($user)->performedOn($user);
        }

        $activity->log($success ? 'login_success' : 'login_failed');
    }
}
