<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'custom' to the type ENUM for backup_configs
        DB::statement("ALTER TABLE backup_configs MODIFY COLUMN type ENUM('full', 'files', 'databases', 'emails', 'config', 'custom') DEFAULT 'full'");

        // Add 'custom' to the type ENUM for backups
        DB::statement("ALTER TABLE backups MODIFY COLUMN type ENUM('full', 'files', 'databases', 'emails', 'config', 'custom') DEFAULT 'full'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'custom' from the type ENUM (only if no records use it)
        DB::statement("ALTER TABLE backup_configs MODIFY COLUMN type ENUM('full', 'files', 'databases', 'emails', 'config') DEFAULT 'full'");
        DB::statement("ALTER TABLE backups MODIFY COLUMN type ENUM('full', 'files', 'databases', 'emails', 'config') DEFAULT 'full'");
    }
};
