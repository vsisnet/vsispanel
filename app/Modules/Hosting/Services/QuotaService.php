<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use RuntimeException;

class QuotaService
{
    /**
     * Check if user can create more domains.
     */
    public function canCreateDomain(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return false;
        }

        $limit = $subscription->plan->domains_limit;
        if ($limit === -1) {
            return true; // Unlimited
        }

        $currentCount = $user->domains()->count();
        return $currentCount < $limit;
    }

    /**
     * Check if user can create more databases.
     */
    public function canCreateDatabase(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return false;
        }

        $limit = $subscription->plan->databases_limit;
        if ($limit === -1) {
            return true; // Unlimited
        }

        $currentCount = $user->databases()->count();
        return $currentCount < $limit;
    }

    /**
     * Check if user can create more email accounts.
     */
    public function canCreateEmailAccount(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return false;
        }

        $limit = $subscription->plan->email_accounts_limit;
        if ($limit === -1) {
            return true; // Unlimited
        }

        // TODO: Get actual count when Email module is implemented
        return true;
    }

    /**
     * Check if user can create more FTP accounts.
     */
    public function canCreateFtpAccount(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return false;
        }

        $limit = $subscription->plan->ftp_accounts_limit;
        if ($limit === -1) {
            return true; // Unlimited
        }

        // TODO: Get actual count when FTP module is implemented
        return true;
    }

    /**
     * Check if user has exceeded disk quota.
     */
    public function hasExceededDiskQuota(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return true;
        }

        $limit = $subscription->plan->disk_limit;
        if ($limit === -1) {
            return false; // Unlimited
        }

        $usedBytes = $this->calculateDiskUsage($user);
        $limitBytes = $limit * 1024 * 1024; // Convert MB to bytes

        return $usedBytes >= $limitBytes;
    }

    /**
     * Check if user has exceeded bandwidth quota.
     */
    public function hasExceededBandwidthQuota(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return true;
        }

        $limit = $subscription->plan->bandwidth_limit;
        if ($limit === -1) {
            return false; // Unlimited
        }

        $usedBytes = $this->calculateBandwidthUsage($user);
        $limitBytes = $limit * 1024 * 1024; // Convert MB to bytes

        return $usedBytes >= $limitBytes;
    }

    /**
     * Get user's quota usage summary.
     */
    public function getQuotaUsage(User $user): array
    {
        $subscription = $this->getActiveSubscription($user);
        if (!$subscription) {
            return [
                'has_subscription' => false,
                'plan' => null,
                'usage' => [],
            ];
        }

        $plan = $subscription->plan;

        return [
            'has_subscription' => true,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'usage' => [
                'domains' => [
                    'used' => $user->domains()->count(),
                    'limit' => $plan->domains_limit,
                    'unlimited' => $plan->domains_limit === -1,
                ],
                'databases' => [
                    'used' => $user->databases()->count(),
                    'limit' => $plan->databases_limit,
                    'unlimited' => $plan->databases_limit === -1,
                ],
                'disk' => [
                    'used_bytes' => $this->calculateDiskUsage($user),
                    'limit_mb' => $plan->disk_limit,
                    'unlimited' => $plan->disk_limit === -1,
                ],
                'bandwidth' => [
                    'used_bytes' => $this->calculateBandwidthUsage($user),
                    'limit_mb' => $plan->bandwidth_limit,
                    'unlimited' => $plan->bandwidth_limit === -1,
                ],
            ],
            'subscription' => [
                'status' => $subscription->status,
                'started_at' => $subscription->started_at?->toIsoString(),
                'expires_at' => $subscription->expires_at?->toIsoString(),
            ],
        ];
    }

    /**
     * Enforce quota check before resource creation.
     */
    public function enforceQuota(User $user, string $resourceType): void
    {
        $canCreate = match ($resourceType) {
            'domain' => $this->canCreateDomain($user),
            'database' => $this->canCreateDatabase($user),
            'email' => $this->canCreateEmailAccount($user),
            'ftp' => $this->canCreateFtpAccount($user),
            default => throw new RuntimeException("Unknown resource type: {$resourceType}"),
        };

        if (!$canCreate) {
            $subscription = $this->getActiveSubscription($user);
            if (!$subscription) {
                throw new RuntimeException("No active subscription. Please subscribe to a plan.");
            }

            throw new RuntimeException("You have reached the {$resourceType} limit for your plan.");
        }
    }

    /**
     * Get user's active subscription.
     */
    public function getActiveSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('plan')
            ->first();
    }

    /**
     * Calculate total disk usage for user.
     */
    protected function calculateDiskUsage(User $user): int
    {
        // Sum disk usage from all domains
        return $user->domains()->sum('disk_used') ?? 0;
    }

    /**
     * Calculate total bandwidth usage for user (current month).
     */
    protected function calculateBandwidthUsage(User $user): int
    {
        // Sum bandwidth usage from all domains
        return $user->domains()->sum('bandwidth_used') ?? 0;
    }
}
