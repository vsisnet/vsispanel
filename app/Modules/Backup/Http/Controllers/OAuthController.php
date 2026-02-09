<?php

declare(strict_types=1);

namespace App\Modules\Backup\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Backup\Models\StorageRemote;
use App\Modules\Backup\Services\RcloneService;
use App\Modules\Security\Services\AuditLogService;
use App\Modules\Security\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    public function __construct(
        private readonly RcloneService $rcloneService,
        private readonly AuditLogService $auditService
    ) {}

    /**
     * Get OAuth configuration for a provider
     */
    public function getConfig(string $provider): JsonResponse
    {
        $proxyClientId = config('backup.oauth.proxy_client_id');

        // Check if OAuth Proxy is configured (no need for local credentials)
        $isConfigured = !empty($proxyClientId);

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $provider,
                'configured' => $isConfigured,
                'requires_oauth' => true,
            ],
        ]);
    }

    /**
     * Initiate OAuth authorization flow through OAuth Proxy Server
     */
    public function initiateAuth(Request $request, string $provider): JsonResponse
    {
        // Get OAuth Proxy configuration
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

        // Generate state token for CSRF protection
        $state = Str::random(40);

        // Store state in cache with user info (expires in 10 minutes)
        Cache::put("oauth_state_{$state}", [
            'user_id' => $request->user()->id,
            'provider' => $provider,
            'remote_name' => $request->input('remote_name', 'google_drive_' . time()),
            'display_name' => $request->input('display_name', 'Google Drive'),
        ], now()->addMinutes(10));

        // Build VSISPanel callback URL (where OAuth Proxy will redirect after Google auth)
        $vsispanelCallbackUrl = url("/api/v1/storage-remotes/oauth/{$provider}/callback");

        // Map provider name for OAuth Proxy
        $proxyProvider = match ($provider) {
            'google' => 'google',
            'onedrive', 'microsoft' => 'microsoft',
            'dropbox' => 'dropbox',
            default => $provider,
        };

        // Build scopes
        $scopes = match ($provider) {
            'google' => 'https://www.googleapis.com/auth/drive',
            'onedrive', 'microsoft' => 'Files.ReadWrite.All offline_access',
            default => '',
        };

        // Build OAuth Proxy authorization URL with mode=token (proxy will exchange code for tokens)
        $authUrl = "{$proxyUrl}/auth/{$proxyProvider}?" . http_build_query([
            'client_id' => $proxyClientId,
            'redirect_url' => $vsispanelCallbackUrl,
            'scope' => $scopes,
            'state' => $state,
            'mode' => 'token',  // OAuth Proxy will exchange code for tokens
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
     * Handle OAuth callback from proxy server (receives encrypted tokens)
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            Log::info('OAuth callback received', [
                'provider' => $provider,
                'has_tokens' => $request->has('tokens'),
                'has_state' => $request->has('state'),
                'has_error' => $request->has('error'),
                'query_keys' => array_keys($request->query()),
            ]);

            $state = $request->input('state');
            $encryptedTokens = $request->input('tokens');
            $error = $request->input('error');

            // Check for OAuth error
            if ($error) {
                $errorDesc = $request->input('error_description', $error);
                Log::error('OAuth error', ['provider' => $provider, 'error' => $error, 'description' => $errorDesc]);
                return redirect('/backup?tab=remotes&oauth_error=' . urlencode($error));
            }

            // Validate state
            $stateData = Cache::get("oauth_state_{$state}");

            if (!$stateData) {
                Log::error('Invalid OAuth state', ['state' => $state]);
                return redirect('/backup?tab=remotes&oauth_error=invalid_state');
            }

            // Clear state from cache
            Cache::forget("oauth_state_{$state}");

            // Check if we received encrypted tokens
            if (empty($encryptedTokens)) {
                Log::error('No tokens received from OAuth Proxy');
                return redirect('/backup?tab=remotes&oauth_error=no_tokens');
            }

            // Decrypt tokens via OAuth Proxy API
            $tokens = $this->decryptTokensFromProxy($encryptedTokens, $provider);

            if (!$tokens) {
                return redirect('/backup?tab=remotes&oauth_error=token_decrypt_failed');
            }

            // Create storage remote with OAuth tokens
            $result = $this->createOAuthRemoteFromProxy(
                $stateData['remote_name'],
                $stateData['display_name'],
                $provider,
                $tokens
            );

            if (!$result['success']) {
                return redirect('/backup?tab=remotes&oauth_error=' . urlencode($result['error']));
            }

            // Log the action
            try {
                $this->auditService->log(
                    AuditLog::ACTION_CREATE,
                    'backup',
                    'storage_remote',
                    $result['remote_id'],
                    "Created OAuth storage remote: {$stateData['display_name']}"
                );
            } catch (\Exception $e) {
                Log::warning('Failed to log OAuth audit', ['error' => $e->getMessage()]);
            }

            return redirect('/backup?tab=remotes&oauth_success=true&remote_id=' . $result['remote_id']);
        } catch (\Throwable $e) {
            Log::error('OAuth callback exception', [
                'provider' => $provider,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect('/backup?tab=remotes&oauth_error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Decrypt tokens from OAuth Proxy
     */
    protected function decryptTokensFromProxy(string $encryptedTokens, string $provider): ?array
    {
        $proxyUrl = config('backup.oauth.proxy_url');
        $proxyClientId = config('backup.oauth.proxy_client_id');

        Log::info('Decrypting tokens from proxy', [
            'proxy_url' => $proxyUrl,
            'has_client_id' => !empty($proxyClientId),
            'client_id_prefix' => $proxyClientId ? substr($proxyClientId, 0, 10) . '...' : 'empty',
            'provider' => $provider,
            'tokens_length' => strlen($encryptedTokens),
        ]);

        try {
            $response = Http::timeout(15)->post("{$proxyUrl}/api/tokens/decrypt", [
                'client_id' => $proxyClientId,
                'encrypted_tokens' => $encryptedTokens,
                'provider' => $provider,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && !empty($data['success']) && isset($data['tokens'])) {
                    $tokens = $data['tokens'];
                    Log::info('Token decrypt successful', ['provider' => $provider]);
                    return [
                        'access_token' => $tokens['access_token'] ?? '',
                        'refresh_token' => $tokens['refresh_token'] ?? '',
                        'token_type' => $tokens['token_type'] ?? 'Bearer',
                        'expires_in' => $tokens['expires_in'] ?? 3600,
                        'expiry' => now()->addSeconds($tokens['expires_in'] ?? 3600)->toIso8601String(),
                        'client_id' => $tokens['client_id'] ?? null,
                        'client_secret' => $tokens['client_secret'] ?? null,
                    ];
                }
            }

            Log::error('Token decrypt failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Token decrypt exception', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return null;
        }
    }

    /**
     * Create storage remote with tokens from OAuth Proxy (includes credentials)
     */
    protected function createOAuthRemoteFromProxy(string $name, string $displayName, string $provider, array $tokens): array
    {
        // Map provider to rclone type
        $rcloneType = match ($provider) {
            'google' => 'drive',
            'onedrive', 'microsoft' => 'onedrive',
            'dropbox' => 'dropbox',
            default => $provider,
        };

        // Build token JSON for rclone
        $tokenJson = json_encode([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expiry' => $tokens['expiry'],
        ]);

        // Prepare rclone config with credentials from OAuth Proxy
        $rcloneConfig = [
            'token' => $tokenJson,
        ];

        // Add client credentials if provided (for token refresh)
        if (!empty($tokens['client_id'])) {
            $rcloneConfig['client_id'] = $tokens['client_id'];
        }
        if (!empty($tokens['client_secret'])) {
            $rcloneConfig['client_secret'] = $tokens['client_secret'];
        }

        // Additional config based on provider
        if ($rcloneType === 'drive') {
            $rcloneConfig['scope'] = 'drive';
        }

        // Create rclone remote
        $rcloneName = 'vsispanel_' . $name;
        $result = $this->rcloneService->createRemote($rcloneName, $rcloneType, $rcloneConfig);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to create rclone remote',
            ];
        }

        // Store in database
        try {
            $remote = StorageRemote::create([
                'name' => $name,
                'display_name' => $displayName,
                'type' => $rcloneType,
                'config' => [
                    'has_oauth_token' => true,
                    'token_expiry' => $tokens['expiry'],
                ],
                'is_active' => true,
            ]);

            return [
                'success' => true,
                'remote_id' => $remote->id,
            ];
        } catch (\Exception $e) {
            // Rollback rclone remote
            $this->rcloneService->deleteRemote($rcloneName);

            return [
                'success' => false,
                'error' => 'Failed to save remote configuration: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle OAuth callback with token directly (for popup flow)
     */
    public function callbackWithToken(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $state = $request->input('state');
        $code = $request->input('code');

        // Validate state
        $stateData = Cache::get("oauth_state_{$state}");

        if (!$stateData) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE',
                    'message' => 'Invalid or expired OAuth state',
                ],
            ], 400);
        }

        // Verify the user matches
        if ($stateData['user_id'] !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'USER_MISMATCH',
                    'message' => 'OAuth state does not match current user',
                ],
            ], 403);
        }

        // Clear state from cache
        Cache::forget("oauth_state_{$state}");

        // Exchange code for tokens
        $config = config("backup.oauth.{$provider}");
        $proxyUrl = config('backup.oauth.proxy_url');
        $callbackUrl = "{$proxyUrl}/callback/{$provider}";

        $tokens = $this->exchangeCodeForTokens($provider, $config, $code, $callbackUrl);

        if (!$tokens) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_EXCHANGE_FAILED',
                    'message' => 'Failed to exchange authorization code for tokens',
                ],
            ], 500);
        }

        // Create storage remote with OAuth tokens
        $result = $this->createOAuthRemote(
            $stateData['remote_name'],
            $stateData['display_name'],
            $provider,
            $tokens,
            $config
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REMOTE_CREATE_FAILED',
                    'message' => $result['error'],
                ],
            ], 500);
        }

        // Log the action
        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            'backup',
            'storage_remote',
            $result['remote_id'],
            "Created OAuth storage remote: {$stateData['display_name']}"
        );

        return response()->json([
            'success' => true,
            'data' => [
                'remote_id' => $result['remote_id'],
                'message' => 'Storage remote created successfully',
            ],
        ]);
    }

    /**
     * Build OAuth authorization URL
     */
    protected function buildAuthorizationUrl(string $provider, array $config, string $state, string $redirectUri): string
    {
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        // Add scopes based on provider
        if ($provider === 'google' || $provider === 'drive') {
            $params['scope'] = implode(' ', $config['scopes'] ?? ['https://www.googleapis.com/auth/drive']);
        } elseif ($provider === 'onedrive') {
            $params['scope'] = implode(' ', $config['scopes'] ?? ['Files.ReadWrite.All', 'offline_access']);
        } elseif ($provider === 'dropbox') {
            $params['token_access_type'] = 'offline';
        }

        return $config['auth_url'] . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access tokens
     */
    protected function exchangeCodeForTokens(string $provider, array $config, string $code, string $redirectUri): ?array
    {
        $params = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        try {
            $response = Http::asForm()->post($config['token_url'], $params);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'access_token' => $data['access_token'] ?? null,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'token_type' => $data['token_type'] ?? 'Bearer',
                    'expires_in' => $data['expires_in'] ?? 3600,
                    'expiry' => now()->addSeconds($data['expires_in'] ?? 3600)->toIso8601String(),
                ];
            }

            Log::error('OAuth token exchange failed', [
                'provider' => $provider,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('OAuth token exchange exception', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create storage remote with OAuth tokens
     */
    protected function createOAuthRemote(string $name, string $displayName, string $provider, array $tokens, array $config): array
    {
        // Map provider to rclone type
        $rcloneType = match ($provider) {
            'google', 'drive' => 'drive',
            'onedrive' => 'onedrive',
            'dropbox' => 'dropbox',
            default => $provider,
        };

        // Build token JSON for rclone
        $tokenJson = json_encode([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expiry' => $tokens['expiry'],
        ]);

        // Prepare rclone config
        $rcloneConfig = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'token' => $tokenJson,
        ];

        // Additional config based on provider
        if ($rcloneType === 'drive') {
            $rcloneConfig['scope'] = 'drive';
        }

        // Create rclone remote
        $rcloneName = 'vsispanel_' . $name;
        $result = $this->rcloneService->createRemote($rcloneName, $rcloneType, $rcloneConfig);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to create rclone remote',
            ];
        }

        // Store in database
        try {
            $remote = StorageRemote::create([
                'name' => $name,
                'display_name' => $displayName,
                'type' => $rcloneType,
                'config' => [
                    'client_id' => $config['client_id'],
                    // Don't store client_secret in DB, it's in rclone config
                    'has_oauth_token' => true,
                    'token_expiry' => $tokens['expiry'],
                ],
                'is_active' => true,
            ]);

            return [
                'success' => true,
                'remote_id' => $remote->id,
            ];
        } catch (\Exception $e) {
            // Rollback rclone remote
            $this->rcloneService->deleteRemote($rcloneName);

            return [
                'success' => false,
                'error' => 'Failed to save remote configuration: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh OAuth token for a storage remote
     */
    public function refreshToken(StorageRemote $storageRemote): JsonResponse
    {
        $provider = $storageRemote->type;
        $config = config("backup.oauth.{$provider}");

        if (!$config) {
            // Try mapping the type
            $providerMap = [
                'drive' => 'google',
                'onedrive' => 'onedrive',
                'dropbox' => 'dropbox',
            ];
            $provider = $providerMap[$storageRemote->type] ?? null;
            $config = $provider ? config("backup.oauth.{$provider}") : null;
        }

        if (!$config) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_OAUTH_REMOTE',
                    'message' => 'This remote does not use OAuth',
                ],
            ], 400);
        }

        // Get current token from rclone
        $rcloneConfig = $this->rcloneService->getRemoteConfig($storageRemote->getRcloneRemoteName());
        $tokenJson = $rcloneConfig['token'] ?? null;

        if (!$tokenJson) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_TOKEN',
                    'message' => 'No OAuth token found for this remote',
                ],
            ], 400);
        }

        $token = json_decode($tokenJson, true);
        $refreshToken = $token['refresh_token'] ?? null;

        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_REFRESH_TOKEN',
                    'message' => 'No refresh token available',
                ],
            ], 400);
        }

        // Refresh the token
        $newTokens = $this->refreshAccessToken($config, $refreshToken);

        if (!$newTokens) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REFRESH_FAILED',
                    'message' => 'Failed to refresh access token',
                ],
            ], 500);
        }

        // Update rclone config with new token
        $newTokenJson = json_encode([
            'access_token' => $newTokens['access_token'],
            'refresh_token' => $newTokens['refresh_token'] ?? $refreshToken,
            'token_type' => $newTokens['token_type'],
            'expiry' => $newTokens['expiry'],
        ]);

        $result = $this->rcloneService->updateRemote($storageRemote->getRcloneRemoteName(), [
            'token' => $newTokenJson,
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => 'Failed to update token in rclone config',
                ],
            ], 500);
        }

        // Update database
        $storageRemote->update([
            'config' => array_merge($storageRemote->config ?? [], [
                'token_expiry' => $newTokens['expiry'],
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
        ]);
    }

    /**
     * Refresh access token using refresh token
     */
    protected function refreshAccessToken(array $config, string $refreshToken): ?array
    {
        $params = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        try {
            $response = Http::asForm()->post($config['token_url'], $params);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                    'token_type' => $data['token_type'] ?? 'Bearer',
                    'expires_in' => $data['expires_in'] ?? 3600,
                    'expiry' => now()->addSeconds($data['expires_in'] ?? 3600)->toIso8601String(),
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OAuth token refresh failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
