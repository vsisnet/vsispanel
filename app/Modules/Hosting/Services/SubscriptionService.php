<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use Carbon\Carbon;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}

    /**
     * Create a subscription for a user.
     */
    public function createSubscription(User $user, Plan $plan, ?Carbon $expiresAt = null): Subscription
    {
        // Check if plan is active
        if (!$plan->is_active) {
            throw new RuntimeException("Plan '{$plan->name}' is not available for new subscriptions.");
        }

        // Check if user already has an active subscription
        $activeSubscription = $this->quotaService->getActiveSubscription($user);
        if ($activeSubscription) {
            throw new RuntimeException("User already has an active subscription to '{$activeSubscription->plan->name}'.");
        }

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Change user's subscription to a different plan.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        // Check if new plan is active
        if (!$newPlan->is_active) {
            throw new RuntimeException("Plan '{$newPlan->name}' is not available.");
        }

        // Check if downgrade would exceed new limits
        $this->validatePlanChange($subscription, $newPlan);

        $subscription->update([
            'plan_id' => $newPlan->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * Suspend a subscription.
     */
    public function suspendSubscription(Subscription $subscription, string $reason): Subscription
    {
        if ($subscription->status === 'suspended') {
            throw new RuntimeException("Subscription is already suspended.");
        }

        $subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        // Suspend all domains
        $subscription->domains()->each(function ($domain) {
            $domain->suspend();
        });

        return $subscription->fresh();
    }

    /**
     * Unsuspend a subscription.
     */
    public function unsuspendSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status !== 'suspended') {
            throw new RuntimeException("Subscription is not suspended.");
        }

        // Check if subscription has expired
        if ($subscription->isExpired()) {
            throw new RuntimeException("Subscription has expired. Please renew first.");
        }

        $subscription->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        // Unsuspend all domains
        $subscription->domains()->each(function ($domain) {
            $domain->unsuspend();
        });

        return $subscription->fresh();
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => 'cancelled',
        ]);

        // Suspend all domains
        $subscription->domains()->each(function ($domain) {
            $domain->suspend();
        });

        return $subscription->fresh();
    }

    /**
     * Renew a subscription.
     */
    public function renewSubscription(Subscription $subscription, int $months = 1): Subscription
    {
        $newExpiresAt = $subscription->expires_at && $subscription->expires_at->isFuture()
            ? $subscription->expires_at->addMonths($months)
            : now()->addMonths($months);

        $subscription->update([
            'status' => 'active',
            'expires_at' => $newExpiresAt,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        // Unsuspend all domains if previously suspended
        $subscription->domains()->where('status', 'suspended')->each(function ($domain) {
            $domain->unsuspend();
        });

        return $subscription->fresh();
    }

    /**
     * Check expired subscriptions and suspend them.
     */
    public function processExpiredSubscriptions(): int
    {
        $expiredCount = 0;

        Subscription::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunk(100, function ($subscriptions) use (&$expiredCount) {
                foreach ($subscriptions as $subscription) {
                    $this->suspendSubscription($subscription, 'Subscription expired');
                    $expiredCount++;
                }
            });

        return $expiredCount;
    }

    /**
     * Validate plan change doesn't exceed new limits.
     */
    protected function validatePlanChange(Subscription $subscription, Plan $newPlan): void
    {
        $user = $subscription->user;

        // Check domains limit
        $domainCount = $user->domains()->count();
        if ($newPlan->domains_limit !== -1 && $domainCount > $newPlan->domains_limit) {
            throw new RuntimeException(
                "Cannot downgrade: You have {$domainCount} domains but the new plan only allows {$newPlan->domains_limit}."
            );
        }

        // Check databases limit
        $dbCount = $user->databases()->count();
        if ($newPlan->databases_limit !== -1 && $dbCount > $newPlan->databases_limit) {
            throw new RuntimeException(
                "Cannot downgrade: You have {$dbCount} databases but the new plan only allows {$newPlan->databases_limit}."
            );
        }

        // TODO: Check other limits as modules are implemented
    }

    /**
     * Get subscription statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'suspended' => Subscription::where('status', 'suspended')->count(),
            'cancelled' => Subscription::where('status', 'cancelled')->count(),
            'expiring_soon' => Subscription::where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(7)])
                ->count(),
        ];
    }
}
