<?php

declare(strict_types=1);

namespace App\Modules\Domain\Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Hosting\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'name' => fake()->unique()->domainName(),
            'document_root' => null,
            'php_version' => fake()->randomElement(['8.1', '8.2', '8.3']),
            'status' => 'active',
            'ssl_enabled' => fake()->boolean(30),
            'is_main' => false,
            'web_server_type' => 'nginx',
            'access_log' => null,
            'error_log' => null,
            'disk_used' => fake()->numberBetween(0, 1073741824), // 0 to 1GB
            'bandwidth_used' => fake()->numberBetween(0, 10737418240), // 0 to 10GB
            'ssl_expires_at' => fake()->boolean() ? fake()->dateTimeBetween('now', '+1 year') : null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    public function withSsl(): static
    {
        return $this->state(fn(array $attributes) => [
            'ssl_enabled' => true,
            'ssl_expires_at' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    public function withoutSsl(): static
    {
        return $this->state(fn(array $attributes) => [
            'ssl_enabled' => false,
            'ssl_expires_at' => null,
        ]);
    }

    public function main(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_main' => true,
        ]);
    }
}
