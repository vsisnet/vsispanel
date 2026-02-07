<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'started_at' => $startedAt,
            'expires_at' => fake()->dateTimeBetween($startedAt, '+2 years'),
            'suspended_at' => null,
            'suspension_reason' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => fake()->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'expired',
            'expires_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forPlan(Plan $plan): static
    {
        return $this->state(fn(array $attributes) => [
            'plan_id' => $plan->id,
        ]);
    }
}
