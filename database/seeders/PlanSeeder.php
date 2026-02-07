<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@vsispanel.local')->first();

        DB::table('plans')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Starter',
                'description' => 'Perfect for small websites and blogs',
                'slug' => 'starter',
                'disk_limit' => 5120,
                'bandwidth_limit' => 102400,
                'domains_limit' => 1,
                'subdomains_limit' => 5,
                'databases_limit' => 2,
                'email_accounts_limit' => 5,
                'ftp_accounts_limit' => 2,
                'php_version_default' => '8.3',
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Business',
                'description' => 'Ideal for growing businesses',
                'slug' => 'business',
                'disk_limit' => 20480,
                'bandwidth_limit' => 409600,
                'domains_limit' => 10,
                'subdomains_limit' => 50,
                'databases_limit' => 20,
                'email_accounts_limit' => 50,
                'ftp_accounts_limit' => 10,
                'php_version_default' => '8.3',
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Enterprise',
                'description' => 'Maximum resources for large websites',
                'slug' => 'enterprise',
                'disk_limit' => 102400,
                'bandwidth_limit' => 2097152,
                'domains_limit' => 100,
                'subdomains_limit' => 500,
                'databases_limit' => 100,
                'email_accounts_limit' => 500,
                'ftp_accounts_limit' => 50,
                'php_version_default' => '8.3',
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
