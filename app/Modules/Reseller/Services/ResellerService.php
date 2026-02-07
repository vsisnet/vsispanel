<?php

declare(strict_types=1);

namespace App\Modules\Reseller\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResellerService
{
    /**
     * List customers for a reseller.
     */
    public function listCustomers(User $reseller, int $perPage = 15): LengthAwarePaginator
    {
        return User::where('parent_id', $reseller->id)
            ->with('subscriptions.plan')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a customer under the reseller.
     */
    public function createCustomer(User $reseller, array $data): User
    {
        $this->validateResellerLimits($reseller);

        $customer = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'parent_id' => $reseller->id,
            'status' => 'active',
        ]);

        return $customer;
    }

    /**
     * Suspend a customer.
     */
    public function suspendCustomer(User $reseller, User $customer, string $reason = ''): User
    {
        $this->ensureOwnership($reseller, $customer);

        $customer->update([
            'status' => 'suspended',
        ]);

        // Suspend active subscriptions
        $customer->subscriptions()
            ->where('status', 'active')
            ->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $reason ?: 'Suspended by reseller',
            ]);

        return $customer->fresh();
    }

    /**
     * Unsuspend a customer.
     */
    public function unsuspendCustomer(User $reseller, User $customer): User
    {
        $this->ensureOwnership($reseller, $customer);

        $customer->update([
            'status' => 'active',
        ]);

        $customer->subscriptions()
            ->where('status', 'suspended')
            ->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

        return $customer->fresh();
    }

    /**
     * Terminate a customer.
     */
    public function terminateCustomer(User $reseller, User $customer): void
    {
        $this->ensureOwnership($reseller, $customer);

        $customer->subscriptions()->update(['status' => 'cancelled']);
        $customer->update(['status' => 'terminated']);
    }

    /**
     * Get resource usage summary for the reseller.
     */
    public function getResourceUsage(User $reseller): array
    {
        $customers = User::where('parent_id', $reseller->id)->get();
        $customerCount = $customers->count();

        $totalDomains = 0;
        $totalDatabases = 0;
        $totalEmailAccounts = 0;

        foreach ($customers as $customer) {
            $totalDomains += $customer->domains()->count();
        }

        return [
            'customers' => [
                'used' => $customerCount,
                'limit' => $reseller->max_customers,
            ],
            'disk_mb' => [
                'used' => 0, // TODO: calculate from du
                'limit' => $reseller->max_disk_mb,
            ],
            'bandwidth_mb' => [
                'used' => 0,
                'limit' => $reseller->max_bandwidth_mb,
            ],
            'domains' => [
                'used' => $totalDomains,
                'limit' => $reseller->max_domains,
            ],
            'databases' => [
                'used' => $totalDatabases,
                'limit' => $reseller->max_databases,
            ],
            'email_accounts' => [
                'used' => $totalEmailAccounts,
                'limit' => $reseller->max_email_accounts,
            ],
        ];
    }

    /**
     * Validate reseller hasn't exceeded customer limits.
     */
    private function validateResellerLimits(User $reseller): void
    {
        if ($reseller->max_customers !== null) {
            $currentCount = User::where('parent_id', $reseller->id)->count();
            if ($currentCount >= $reseller->max_customers) {
                throw new \RuntimeException('Maximum customer limit reached.');
            }
        }
    }

    /**
     * Ensure the reseller owns the customer.
     */
    private function ensureOwnership(User $reseller, User $customer): void
    {
        if ($customer->parent_id !== $reseller->id) {
            throw new \RuntimeException('Customer does not belong to this reseller.');
        }
    }
}
