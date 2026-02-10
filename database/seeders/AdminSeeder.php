<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@vsispanel.local');
        $password = env('ADMIN_PASSWORD', Str::random(16));

        $admin = User::create([
            'id' => Str::uuid(),
            'name' => 'Administrator',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'status' => 'active',
            'locale' => 'vi',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        // Assign admin role (spatie permission)
        $admin->assignRole('admin');
    }
}
