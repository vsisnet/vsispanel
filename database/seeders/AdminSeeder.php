<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'id' => Str::uuid(),
            'name' => 'Administrator',
            'email' => 'admin@vsispanel.local',
            'password' => 'Quanghuy@@3112',
            'role' => 'admin',
            'status' => 'active',
            'locale' => 'vi',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        // Assign admin role (spatie permission)
        $admin->assignRole('admin');
    }
}
