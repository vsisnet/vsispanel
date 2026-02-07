<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Starter', 'Basic', 'Standard', 'Professional', 'Business', 'Enterprise',
            ]) . ' ' . fake()->randomNumber(3),
            'description' => fake()->sentence(),
            'slug' => fn(array $attributes) => \Illuminate\Support\Str::slug($attributes['name']),
            'disk_limit' => fake()->randomElement([1024, 5120, 10240, 20480, 51200, 102400]), // MB
            'bandwidth_limit' => fake()->randomElement([10240, 51200, 102400, 204800, 512000, 1048576]), // MB
            'domains_limit' => fake()->randomElement([1, 3, 5, 10, 25, 0]), // 0 = unlimited
            'subdomains_limit' => fake()->randomElement([5, 10, 25, 50, 0]),
            'databases_limit' => fake()->randomElement([1, 3, 5, 10, 0]),
            'email_accounts_limit' => fake()->randomElement([5, 10, 25, 50, 0]),
            'ftp_accounts_limit' => fake()->randomElement([1, 3, 5, 10, 0]),
            'php_version_default' => fake()->randomElement(['8.1', '8.2', '8.3']),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Starter',
            'slug' => 'starter',
            'disk_limit' => 1024,
            'bandwidth_limit' => 10240,
            'domains_limit' => 1,
            'subdomains_limit' => 5,
            'databases_limit' => 1,
            'email_accounts_limit' => 5,
            'ftp_accounts_limit' => 1,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Unlimited',
            'slug' => 'unlimited',
            'disk_limit' => 0,
            'bandwidth_limit' => 0,
            'domains_limit' => 0,
            'subdomains_limit' => 0,
            'databases_limit' => 0,
            'email_accounts_limit' => 0,
            'ftp_accounts_limit' => 0,
        ]);
    }
}
