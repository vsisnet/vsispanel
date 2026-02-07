<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mail\Http\Requests\CreateMailAccountRequest;
use App\Modules\Mail\Http\Requests\UpdateMailAccountRequest;
use App\Modules\Mail\Http\Resources\MailAccountResource;
use App\Modules\Mail\Models\MailAccount;
use App\Modules\Mail\Models\MailDomain;
use App\Modules\Mail\Services\MailAccountService;
use App\Modules\Mail\Services\WebmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MailAccountController extends Controller
{
    public function __construct(
        protected MailAccountService $mailAccountService,
        protected WebmailService $webmailService
    ) {}

    /**
     * List mail accounts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = MailAccount::with(['mailDomain.domain', 'forwards'])
            ->whereHas('mailDomain.domain', function ($q) use ($user) {
                if (!$user->isAdmin()) {
                    $q->where('user_id', $user->id);
                }
            });

        // Filter by domain
        if ($request->has('domain') || $request->has('mail_domain_id')) {
            $domainId = $request->domain ?? $request->mail_domain_id;
            $query->where('mail_domain_id', $domainId);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $accounts = $query->latest()->paginate($request->per_page ?? 15);

        return MailAccountResource::collection($accounts);
    }

    /**
     * Create a mail account.
     */
    public function store(CreateMailAccountRequest $request): JsonResponse
    {
        $mailDomain = MailDomain::findOrFail($request->mail_domain_id);

        // Check ownership
        if (!$request->user()->isAdmin() && $mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to create accounts for this domain.',
                ],
            ], 403);
        }

        try {
            $account = $this->mailAccountService->createAccount(
                $request->user(),
                $mailDomain,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'data' => new MailAccountResource($account->load('mailDomain.domain')),
                'message' => 'Mail account created successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get mail account details.
     */
    public function show(Request $request, MailAccount $account): MailAccountResource|JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view this account.',
                ],
            ], 403);
        }

        return new MailAccountResource($account->load(['mailDomain.domain', 'forwards']));
    }

    /**
     * Update a mail account.
     */
    public function update(UpdateMailAccountRequest $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to update this account.',
                ],
            ], 403);
        }

        try {
            // Update quota if provided
            if ($request->has('quota_mb')) {
                $this->mailAccountService->setQuota($account, $request->quota_mb);
            }

            // Update status if provided
            if ($request->has('status')) {
                if ($request->status === 'suspended') {
                    $this->mailAccountService->suspendAccount($account);
                } elseif ($request->status === 'active') {
                    $this->mailAccountService->unsuspendAccount($account);
                }
            }

            return response()->json([
                'success' => true,
                'data' => new MailAccountResource($account->fresh(['mailDomain.domain'])),
                'message' => 'Mail account updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Delete a mail account.
     */
    public function destroy(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to delete this account.',
                ],
            ], 403);
        }

        try {
            $this->mailAccountService->deleteAccount($account);

            return response()->json([
                'success' => true,
                'message' => 'Mail account deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Change account password.
     */
    public function changePassword(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to change this password.',
                ],
            ], 403);
        }

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        try {
            $this->mailAccountService->changePassword($account, $request->password);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PASSWORD_CHANGE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Set auto-responder.
     */
    public function setAutoResponder(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this account.',
                ],
            ], 403);
        }

        $request->validate([
            'enabled' => ['required', 'boolean'],
            'subject' => ['required_if:enabled,true', 'nullable', 'string', 'max:255'],
            'message' => ['required_if:enabled,true', 'nullable', 'string', 'max:10000'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
        ]);

        try {
            if ($request->enabled) {
                $this->mailAccountService->setAutoResponder(
                    $account,
                    $request->subject,
                    $request->message,
                    $request->start_at,
                    $request->end_at
                );
            } else {
                $this->mailAccountService->disableAutoResponder($account);
            }

            return response()->json([
                'success' => true,
                'data' => new MailAccountResource($account->fresh()),
                'message' => $request->enabled
                    ? 'Auto-responder enabled successfully.'
                    : 'Auto-responder disabled successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTO_RESPONDER_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Add forwarding.
     */
    public function addForward(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this account.',
                ],
            ], 403);
        }

        $request->validate([
            'forward_to' => ['required', 'email'],
            'keep_copy' => ['nullable', 'boolean'],
        ]);

        try {
            $forward = $this->mailAccountService->addForwarding(
                $account,
                $request->forward_to,
                $request->keep_copy ?? true
            );

            return response()->json([
                'success' => true,
                'data' => $forward,
                'message' => 'Forwarding added successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORWARD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get account usage statistics.
     */
    public function usage(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view this account.',
                ],
            ], 403);
        }

        try {
            $stats = $this->mailAccountService->getUsageStats($account);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STATS_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get webmail URL with SSO token.
     */
    public function webmailUrl(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this account.',
                ],
            ], 403);
        }

        // Check if webmail is enabled
        if (!$this->webmailService->isEnabled()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WEBMAIL_DISABLED',
                    'message' => 'Webmail is currently disabled.',
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

    /**
     * Get mail client configuration.
     */
    public function mailClientConfig(Request $request, MailAccount $account): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view this account.',
                ],
            ], 403);
        }

        try {
            $config = $this->webmailService->getMailClientConfig($account);

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONFIG_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get webmail configuration info (global).
     */
    public function webmailConfig(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->webmailService->getWebmailConfig(),
        ]);
    }
}
