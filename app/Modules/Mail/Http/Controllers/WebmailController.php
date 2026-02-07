<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mail\Services\WebmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebmailController extends Controller
{
    public function __construct(
        protected WebmailService $webmailService
    ) {}

    /**
     * Validate SSO token (called by Roundcube plugin).
     */
    public function validateSso(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        // Verify API key
        $apiKey = $request->header('X-API-Key');
        $expectedKey = config('webmail.roundcube.api_key');

        if (!$expectedKey || $apiKey !== $expectedKey) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_API_KEY',
                    'message' => 'Invalid or missing API key.',
                ],
            ], 401);
        }

        $account = $this->webmailService->validateSsoToken($request->token);

        if (!$account) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Token is invalid or expired.',
                ],
            ], 401);
        }

        // We need to return credentials for Roundcube to authenticate
        // This requires storing the password or using a different approach
        // For security, we'll use encrypted credentials stored during token generation

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $account->email,
                // Note: Password is not returned for security
                // Roundcube should use a different auth mechanism
                'account_id' => $account->id,
            ],
        ]);
    }

    /**
     * Get webmail configuration.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->webmailService->getWebmailConfig(),
        ]);
    }

    /**
     * Generate auto-login URL for direct webmail access.
     * This uses Roundcube's HTTP authentication.
     */
    public function autoLogin(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['required', 'uuid', 'exists:mail_accounts,id'],
        ]);

        $user = $request->user();
        $account = \App\Modules\Mail\Models\MailAccount::with('mailDomain.domain')
            ->findOrFail($request->account_id);

        // Check ownership
        if (!$user->isAdmin() && $account->mailDomain->domain->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this account.',
                ],
            ], 403);
        }

        // Check if account is active
        if (!$account->isActive()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_INACTIVE',
                    'message' => 'Cannot access webmail for inactive account.',
                ],
            ], 403);
        }

        try {
            $url = $this->webmailService->getWebmailUrl($account);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'email' => $account->email,
                    'expires_in' => config('webmail.sso.token_ttl', 300),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WEBMAIL_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
