<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Http\Controllers;

use App\Modules\Reseller\Services\ResellerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResellerReportController extends Controller
{
    public function __construct(
        private readonly ResellerReportService $reportService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->reportService->getOverview($user),
        ]);
    }

    public function growth(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        $period = $request->get('period', '12m');

        return response()->json([
            'success' => true,
            'data' => $this->reportService->getGrowthReport($user, $period),
        ]);
    }

    public function customerBreakdown(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isReseller() && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.']], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->reportService->getCustomerBreakdown($user),
        ]);
    }
}
