<?php

namespace Database\Seeders;

use App\Modules\Marketplace\Database\Seeders\AppTemplateSeeder;
use App\Modules\Monitoring\Database\Seeders\AlertTemplateSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminSeeder::class,
            PlanSeeder::class,
            AlertTemplateSeeder::class,
            AppTemplateSeeder::class,
        ]);
    }
}
