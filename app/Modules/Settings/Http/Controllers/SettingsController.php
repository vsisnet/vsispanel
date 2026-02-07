<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\Http\Requests\UpdateSettingsRequest;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    /**
     * GET /api/v1/settings
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $this->settingsService->getAllGrouped(),
                'server_time' => $this->settingsService->getServerTime(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/settings
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        // validated() returns nested structure; flatten back to dot-notation for updateBatch()
        $this->settingsService->updateBatch(Arr::dot($request->validated()));

        return response()->json([
            'success' => true,
            'data' => $this->settingsService->getAllGrouped(),
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * GET /api/v1/settings/timezones
     */
    public function timezones(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settingsService->getTimezones(),
        ]);
    }

    /**
     * POST /api/v1/settings/notifications/test
     */
    public function testNotification(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['required', 'string', Rule::in(['email', 'telegram', 'slack', 'discord'])],
        ]);

        $channel = $request->input('channel');
        $success = $this->settingsService->testNotification($channel);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOTIFICATION_TEST_FAILED',
                'message' => 'Failed to send test notification. Check your configuration.',
            ],
        ], 422);
    }

    /**
     * POST /api/v1/settings/time/sync
     */
    public function syncTime(): JsonResponse
    {
        $success = $this->settingsService->syncTime();

        if ($success) {
            return response()->json([
                'success' => true,
                'data' => $this->settingsService->getServerTime(),
                'message' => 'Server time synced successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TIME_SYNC_FAILED',
                'message' => 'Failed to sync server time',
            ],
        ], 500);
    }

    /**
     * GET /api/v1/settings/mail/gmail/status
     * Check Gmail OAuth2 authorization status.
     */
    public function gmailOAuthStatus(): JsonResponse
    {
        $proxyClientId = config('backup.oauth.proxy_client_id');
        $tokenJson = $this->settingsService->get('mail.gmail_token');

        return response()->json([
            'success' => true,
            'data' => [
                'proxy_configured' => ! empty($proxyClientId),
                'authorized' => ! empty($tokenJson),
                'email' => $this->settingsService->get('mail.gmail_email', ''),
            ],
        ]);
    }

    /**
     * POST /api/v1/settings/mail/gmail/authorize
     * Initiate Gmail OAuth2 flow via OAuth Proxy.
     */
    public function gmailAuthorize(Request $request): JsonResponse
    {
        $proxyUrl = config('backup.oauth.proxy_url');
        $proxyClientId = config('backup.oauth.proxy_client_id');

        if (empty($proxyClientId)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PROXY_NOT_CONFIGURED',
                    'message' => 'OAuth Proxy client is not configured',
                ],
            ], 400);
        }

        // Generate CSRF state token
        $state = Str::random(40);

        Cache::put("gmail_oauth_state_{$state}", [
            'user_id' => $request->user()->id,
        ], now()->addMinutes(10));

        // Build callback URL
        $callbackUrl = url('/api/v1/settings/mail/gmail/callback');

        // Gmail send scope
        $scopes = 'https://mail.google.com/ https://www.googleapis.com/auth/userinfo.email';

        $authUrl = "{$proxyUrl}/auth/google?" . http_build_query([
            'client_id' => $proxyClientId,
            'redirect_url' => $callbackUrl,
            'scope' => $scopes,
            'state' => $state,
            'mode' => 'token',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'auth_url' => $authUrl,
                'state' => $state,
            ],
        ]);
    }

    /**
     * GET /api/v1/settings/mail/gmail/callback
     * Handle OAuth callback from proxy (receives encrypted tokens).
     */
    public function gmailCallback(Request $request): RedirectResponse
    {
        $state = $request->input('state');
        $encryptedTokens = $request->input('tokens');
        $error = $request->input('error');

        if ($error) {
            Log::error('Gmail OAuth error', ['error' => $error]);
            return redirect('/settings?tab=notifications&gmail_auth=error&gmail_error=' . urlencode($error));
        }

        // Validate state
        $stateData = Cache::get("gmail_oauth_state_{$state}");
        if (! $stateData) {
            Log::error('Invalid Gmail OAuth state');
            return redirect('/settings?tab=notifications&gmail_auth=error&gmail_error=invalid_state');
        }
        Cache::forget("gmail_oauth_state_{$state}");

        if (empty($encryptedTokens)) {
            return redirect('/settings?tab=notifications&gmail_auth=error&gmail_error=no_tokens');
        }

        // Decrypt tokens via OAuth Proxy
        $tokens = $this->decryptTokensFromProxy($encryptedTokens);
        if (! $tokens) {
            return redirect('/settings?tab=notifications&gmail_auth=error&gmail_error=decrypt_failed');
        }

        // Get user's email from Google
        $email = $this->getGoogleUserEmail($tokens['access_token']);

        // Store tokens in system_settings (encrypted as JSON)
        $tokenData = json_encode([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expiry' => $tokens['expiry'],
            'client_id' => $tokens['client_id'],
            'client_secret' => $tokens['client_secret'],
        ]);

        $this->settingsService->set('mail.gmail_token', $tokenData, 'string');

        if ($email) {
            $this->settingsService->set('mail.gmail_email', $email, 'string');
            // Also set from_address if not already set
            if (! $this->settingsService->get('mail.from_address')) {
                $this->settingsService->set('mail.from_address', $email, 'string');
            }
        }

        // Set provider to gmail_oauth
        $this->settingsService->set('mail.provider', 'gmail_oauth', 'string');

        // Re-apply config overrides
        $this->settingsService->applyNotificationConfigOverrides();

        Log::channel('commands')->info('Gmail OAuth2 authorized', ['email' => $email]);

        return redirect('/settings?tab=notifications&gmail_auth=success');
    }

    /**
     * POST /api/v1/settings/mail/gmail/revoke
     * Revoke Gmail OAuth2 authorization.
     */
    public function gmailRevoke(): JsonResponse
    {
        $this->settingsService->set('mail.gmail_token', '', 'string');
        $this->settingsService->set('mail.gmail_email', '', 'string');

        // Switch back to smtp provider
        $this->settingsService->set('mail.provider', 'smtp', 'string');
        $this->settingsService->applyNotificationConfigOverrides();

        return response()->json([
            'success' => true,
            'message' => 'Gmail authorization revoked',
        ]);
    }

    /**
     * Decrypt tokens from OAuth Proxy.
     */
    private function decryptTokensFromProxy(string $encryptedTokens): ?array
    {
        $proxyUrl = config('backup.oauth.proxy_url');
        $proxyClientId = config('backup.oauth.proxy_client_id');

        try {
            $response = Http::post("{$proxyUrl}/api/tokens/decrypt", [
                'client_id' => $proxyClientId,
                'encrypted_tokens' => $encryptedTokens,
                'provider' => 'google',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] && isset($data['tokens'])) {
                    $tokens = $data['tokens'];

                    return [
                        'access_token' => $tokens['access_token'],
                        'refresh_token' => $tokens['refresh_token'],
                        'token_type' => $tokens['token_type'] ?? 'Bearer',
                        'expires_in' => $tokens['expires_in'] ?? 3600,
                        'expiry' => now()->addSeconds($tokens['expires_in'] ?? 3600)->toIso8601String(),
                        'client_id' => $tokens['client_id'] ?? null,
                        'client_secret' => $tokens['client_secret'] ?? null,
                    ];
                }
            }

            Log::error('Gmail token decrypt failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Gmail token decrypt exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get Google user email from access token.
     */
    private function getGoogleUserEmail(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($response->successful()) {
                return $response->json('email');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get Google user email', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
