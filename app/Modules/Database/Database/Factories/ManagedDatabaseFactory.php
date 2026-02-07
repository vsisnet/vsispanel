<?php

declare(strict_types=1);

namespace App\Modules\Database\Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManagedDatabaseFactory extends Factory
{
    protected $model = ManagedDatabase::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'user_id' => User::factory(),
            'domain_id' => null,
            'name' => "testuser_{$name}",
            'original_name' => $name,
            'size_bytes' => $this->faker->numberBetween(0, 1073741824), // 0 to 1GB
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function forUser(User $user): static
    {
        $username = $user->username ?? $user->name;

        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
            'name' => "{$username}_{$attributes['original_name']}",
        ]);
    }

    public function forDomain(Domain $domain): static
    {
        return $this->state(fn(array $attributes) => [
            'domain_id' => $domain->id,
            'user_id' => $domain->user_id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'deleted',
        ]);
    }

    public function withSize(int $sizeBytes): static
    {
        return $this->state(fn(array $attributes) => [
            'size_bytes' => $sizeBytes,
        ]);
    }
}
