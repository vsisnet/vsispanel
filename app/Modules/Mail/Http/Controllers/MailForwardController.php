<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mail\Http\Resources\MailForwardResource;
use App\Modules\Mail\Models\MailForward;
use App\Modules\Mail\Services\MailAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailForwardController extends Controller
{
    public function __construct(
        protected MailAccountService $mailAccountService
    ) {}

    /**
     * Delete a forward.
     */
    public function destroy(Request $request, MailForward $forward): JsonResponse
    {
        $account = $forward->mailAccount;

        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to delete this forward.',
                ],
            ], 403);
        }

        try {
            $this->mailAccountService->removeForwarding($forward);

            return response()->json([
                'success' => true,
                'message' => 'Forward removed successfully.',
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
     * Toggle forward active status.
     */
    public function toggle(Request $request, MailForward $forward): JsonResponse
    {
        $account = $forward->mailAccount;

        // Check ownership
        if (!$request->user()->isAdmin() && $account->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this forward.',
                ],
            ], 403);
        }

        $forward->update(['is_active' => !$forward->is_active]);

        return response()->json([
            'success' => true,
            'data' => new MailForwardResource($forward->fresh()),
            'message' => $forward->is_active ? 'Forward enabled.' : 'Forward disabled.',
        ]);
    }
}
