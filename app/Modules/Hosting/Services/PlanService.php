<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use Illuminate\Support\Str;
use RuntimeException;

class PlanService
{
    /**
     * Create a new plan.
     */
    public function createPlan(User $creator, array $data): Plan
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Check for unique slug
        if (Plan::where('slug', $data['slug'])->exists()) {
            throw new RuntimeException("A plan with slug '{$data['slug']}' already exists.");
        }

        return Plan::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $data['slug'],
            'disk_limit' => $data['disk_limit'] ?? 1024, // Default 1GB
            'bandwidth_limit' => $data['bandwidth_limit'] ?? 10240, // Default 10GB
            'domains_limit' => $data['domains_limit'] ?? 1,
            'subdomains_limit' => $data['subdomains_limit'] ?? 5,
            'databases_limit' => $data['databases_limit'] ?? 1,
            'email_accounts_limit' => $data['email_accounts_limit'] ?? 5,
            'ftp_accounts_limit' => $data['ftp_accounts_limit'] ?? 2,
            'php_version_default' => $data['php_version_default'] ?? '8.3',
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Update a plan.
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        // Check for unique slug if changed
        if (!empty($data['slug']) && $data['slug'] !== $plan->slug) {
            if (Plan::where('slug', $data['slug'])->where('id', '!=', $plan->id)->exists()) {
                throw new RuntimeException("A plan with slug '{$data['slug']}' already exists.");
            }
        }

        $plan->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'slug' => $data['slug'] ?? null,
            'disk_limit' => $data['disk_limit'] ?? null,
            'bandwidth_limit' => $data['bandwidth_limit'] ?? null,
            'domains_limit' => $data['domains_limit'] ?? null,
            'subdomains_limit' => $data['subdomains_limit'] ?? null,
            'databases_limit' => $data['databases_limit'] ?? null,
            'email_accounts_limit' => $data['email_accounts_limit'] ?? null,
            'ftp_accounts_limit' => $data['ftp_accounts_limit'] ?? null,
            'php_version_default' => $data['php_version_default'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($value) => $value !== null));

        return $plan->fresh();
    }

    /**
     * Delete a plan.
     */
    public function deletePlan(Plan $plan): void
    {
        // Check if plan has active subscriptions
        $activeSubscriptions = $plan->subscriptions()
            ->where('status', 'active')
            ->count();

        if ($activeSubscriptions > 0) {
            throw new RuntimeException("Cannot delete plan with {$activeSubscriptions} active subscription(s).");
        }

        $plan->delete();
    }

    /**
     * Activate a plan.
     */
    public function activatePlan(Plan $plan): Plan
    {
        $plan->update(['is_active' => true]);
        return $plan->fresh();
    }

    /**
     * Deactivate a plan.
     */
    public function deactivatePlan(Plan $plan): Plan
    {
        $plan->update(['is_active' => false]);
        return $plan->fresh();
    }

    /**
     * Get available plans for new subscriptions.
     */
    public function getAvailablePlans()
    {
        return Plan::where('is_active', true)
            ->orderBy('disk_limit')
            ->get();
    }

    /**
     * Clone a plan with new name.
     */
    public function clonePlan(Plan $plan, string $newName, User $creator): Plan
    {
        $data = $plan->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);

        $data['name'] = $newName;
        $data['slug'] = Str::slug($newName);
        $data['created_by'] = $creator->id;

        // Ensure unique slug
        $baseSlug = $data['slug'];
        $counter = 1;
        while (Plan::where('slug', $data['slug'])->exists()) {
            $data['slug'] = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return Plan::create($data);
    }
}
