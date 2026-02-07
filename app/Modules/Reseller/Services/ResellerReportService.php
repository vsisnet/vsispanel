<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\DB;

class ResellerReportService
{
    public function getOverview(User $reseller): array
    {
        $customers = User::where('parent_id', $reseller->id);

        return [
            'total_customers' => $customers->count(),
            'active_customers' => (clone $customers)->where('status', 'active')->count(),
            'suspended_customers' => (clone $customers)->where('status', 'suspended')->count(),
            'terminated_customers' => (clone $customers)->where('status', 'terminated')->count(),
        ];
    }

    /**
     * Get customer growth report.
     */
    public function getGrowthReport(User $reseller, string $period = '12m'): array
    {
        $months = match ($period) {
            '3m' => 3,
            '6m' => 6,
            '12m' => 12,
            default => 12,
        };

        $startDate = now()->subMonths($months)->startOfMonth();

        $data = User::where('parent_id', $reseller->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Fill in empty months
        $result = [];
        for ($i = 0; $i < $months; $i++) {
            $monthKey = now()->subMonths($months - 1 - $i)->format('Y-m');
            $result[] = [
                'month' => $monthKey,
                'count' => $data[$monthKey] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Get per-customer resource breakdown.
     */
    public function getCustomerBreakdown(User $reseller): array
    {
        $customers = User::where('parent_id', $reseller->id)
            ->withCount('domains')
            ->with(['subscriptions' => function ($q) {
                $q->where('status', 'active')->with('plan');
            }])
            ->get();

        return $customers->map(function ($customer) {
            $plan = $customer->subscriptions->first()?->plan;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'username' => $customer->username,
                'status' => $customer->status,
                'plan' => $plan?->name ?? 'No Plan',
                'domains' => $customer->domains_count,
                'created_at' => $customer->created_at->toISOString(),
            ];
        })->toArray();
    }
}
