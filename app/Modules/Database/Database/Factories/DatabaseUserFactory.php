<?php

declare(strict_types=1);

namespace App\Modules\Database\Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Database\Models\DatabaseUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class DatabaseUserFactory extends Factory
{
    protected $model = DatabaseUser::class;

    public function definition(): array
    {
        $username = $this->faker->unique()->userName();

        return [
            'user_id' => User::factory(),
            'username' => "testuser_{$username}",
            'original_username' => $username,
            'host' => 'localhost',
            'privileges' => [],
            'notes' => null,
        ];
    }

    public function forUser(User $user): static
    {
        $panelUsername = $user->username ?? $user->name;

        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
            'username' => "{$panelUsername}_{$attributes['original_username']}",
        ]);
    }

    public function withHost(string $host): static
    {
        return $this->state(fn(array $attributes) => [
            'host' => $host,
        ]);
    }

    public function withPrivileges(array $privileges): static
    {
        return $this->state(fn(array $attributes) => [
            'privileges' => $privileges,
        ]);
    }

    public function withDefaultPrivileges(): static
    {
        return $this->state(fn(array $attributes) => [
            'privileges' => [
                'SELECT', 'INSERT', 'UPDATE', 'DELETE',
                'CREATE', 'DROP', 'ALTER', 'INDEX',
                'CREATE TEMPORARY TABLES', 'LOCK TABLES',
                'EXECUTE', 'CREATE VIEW', 'SHOW VIEW',
                'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'TRIGGER',
            ],
        ]);
    }

    public function remoteAccess(): static
    {
        return $this->state(fn(array $attributes) => [
            'host' => '%',
        ]);
    }
}
