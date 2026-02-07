<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'pending' status
        DB::statement("ALTER TABLE `domains` MODIFY COLUMN `status` ENUM('active', 'suspended', 'disabled', 'pending') DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'pending' from the enum
        DB::statement("ALTER TABLE `domains` MODIFY COLUMN `status` ENUM('active', 'suspended', 'disabled') DEFAULT 'active'");
    }
};
