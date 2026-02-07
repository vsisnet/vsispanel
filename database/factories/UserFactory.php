<?php

namespace Database\Factories;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Auth\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The model associated with this factory.
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user',
            'status' => 'active',
            'locale' => 'vi',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ];
    }

    /**
     * Set user as admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Set user as reseller.
     */
    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'reseller',
        ]);
    }

    /**
     * Set user as suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }
}
