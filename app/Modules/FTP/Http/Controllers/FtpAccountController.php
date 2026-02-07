<?php

declare(strict_types=1);

namespace App\Modules\FTP\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domain\Models\Domain;
use App\Modules\FTP\Http\Requests\StoreFtpAccountRequest;
use App\Modules\FTP\Http\Requests\UpdateFtpAccountRequest;
use App\Modules\FTP\Http\Resources\FtpAccountResource;
use App\Modules\FTP\Models\FtpAccount;
use App\Modules\FTP\Services\FtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FtpAccountController extends Controller
{
    public function __construct(
        protected FtpService $ftpService
    ) {}

    /**
     * List all FTP accounts
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FtpAccount::with(['domain', 'user']);

        // Filter by domain
        if ($domainId = $request->get('domain_id')) {
            $query->forDomain($domainId);
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by user (for non-admin)
        if (!$request->user()->isAdmin()) {
            $query->forUser($request->user()->id);
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('domain', function ($dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $accounts = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return FtpAccountResource::collection($accounts);
    }

    /**
     * Store new FTP account
     */
    public function store(StoreFtpAccountRequest $request): JsonResponse
    {
        $domain = Domain::findOrFail($request->validated('domain_id'));

        $account = $this->ftpService->createAccount($domain, [
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FTP account created successfully.',
            'data' => new FtpAccountResource($account->load(['domain', 'user'])),
        ], 201);
    }

    /**
     * Show FTP account details
     */
    public function show(FtpAccount $ftpAccount): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new FtpAccountResource($ftpAccount->load(['domain', 'user'])),
        ]);
    }

    /**
     * Update FTP account
     */
    public function update(UpdateFtpAccountRequest $request, FtpAccount $ftpAccount): JsonResponse
    {
        $account = $this->ftpService->updateAccount($ftpAccount, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'FTP account updated successfully.',
            'data' => new FtpAccountResource($account->load(['domain', 'user'])),
        ]);
    }

    /**
     * Delete FTP account
     */
    public function destroy(FtpAccount $ftpAccount): JsonResponse
    {
        $this->ftpService->deleteAccount($ftpAccount);

        return response()->json([
            'success' => true,
            'message' => 'FTP account deleted successfully.',
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request, FtpAccount $ftpAccount): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $this->ftpService->changePassword($ftpAccount, $request->password);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Toggle account status (suspend/activate)
     */
    public function toggleStatus(FtpAccount $ftpAccount): JsonResponse
    {
        if ($ftpAccount->status === FtpAccount::STATUS_ACTIVE) {
            $this->ftpService->suspendAccount($ftpAccount);
            $message = 'FTP account suspended.';
        } else {
            $this->ftpService->activateAccount($ftpAccount);
            $message = 'FTP account activated.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new FtpAccountResource($ftpAccount->fresh()->load(['domain', 'user'])),
        ]);
    }

    /**
     * Get FTP service status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ftpService->getStatus(),
        ]);
    }

    /**
     * Get FTP statistics
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ftpService->getStatistics(),
        ]);
    }

    /**
     * Get connected users
     */
    public function connectedUsers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ftpService->getConnectedUsers(),
        ]);
    }

    /**
     * Disconnect a user session
     */
    public function disconnectUser(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
        ]);

        $result = $this->ftpService->disconnectUser($request->username);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'User disconnected successfully.' : 'Failed to disconnect user.',
        ]);
    }

    /**
     * Get FTP logs
     */
    public function logs(Request $request): JsonResponse
    {
        $lines = min($request->get('lines', 100), 500);

        return response()->json([
            'success' => true,
            'data' => $this->ftpService->getLogs($lines),
        ]);
    }

    /**
     * Get transfer logs
     */
    public function transferLogs(Request $request): JsonResponse
    {
        $lines = min($request->get('lines', 100), 500);

        return response()->json([
            'success' => true,
            'data' => $this->ftpService->getTransferLogs($lines),
        ]);
    }

    /**
     * Restart FTP service
     */
    public function restart(): JsonResponse
    {
        $result = $this->ftpService->restart();

        return response()->json([
            'success' => $result,
            'message' => $result ? 'FTP service restarted.' : 'Failed to restart FTP service.',
        ]);
    }

    /**
     * Reload FTP configuration
     */
    public function reload(): JsonResponse
    {
        $result = $this->ftpService->reload();

        return response()->json([
            'success' => $result,
            'message' => $result ? 'FTP configuration reloaded.' : 'Failed to reload configuration.',
        ]);
    }

    /**
     * Test FTP configuration
     */
    public function testConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ftpService->testConfig(),
        ]);
    }

    /**
     * Bulk activate accounts
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $request->validate([
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['required', 'uuid', 'exists:ftp_accounts,id'],
        ]);

        $count = 0;
        foreach ($request->account_ids as $accountId) {
            $account = FtpAccount::find($accountId);
            if ($account && $account->status !== FtpAccount::STATUS_ACTIVE) {
                $this->ftpService->activateAccount($account);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} account(s) activated successfully.",
            'data' => ['activated_count' => $count],
        ]);
    }

    /**
     * Bulk suspend accounts
     */
    public function bulkSuspend(Request $request): JsonResponse
    {
        $request->validate([
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['required', 'uuid', 'exists:ftp_accounts,id'],
        ]);

        $count = 0;
        foreach ($request->account_ids as $accountId) {
            $account = FtpAccount::find($accountId);
            if ($account && $account->status === FtpAccount::STATUS_ACTIVE) {
                $this->ftpService->suspendAccount($account);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} account(s) suspended successfully.",
            'data' => ['suspended_count' => $count],
        ]);
    }

    /**
     * Bulk delete accounts
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['required', 'uuid', 'exists:ftp_accounts,id'],
        ]);

        $count = 0;
        foreach ($request->account_ids as $accountId) {
            $account = FtpAccount::find($accountId);
            if ($account) {
                $this->ftpService->deleteAccount($account);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} account(s) deleted successfully.",
            'data' => ['deleted_count' => $count],
        ]);
    }
}
