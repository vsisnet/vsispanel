<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            // Add new columns for multiple destinations and custom backup items
            if (!Schema::hasColumn('backup_configs', 'destinations')) {
                $table->json('destinations')->nullable()->after('destination_type');
            }
            if (!Schema::hasColumn('backup_configs', 'backup_items')) {
                $table->json('backup_items')->nullable()->after('type');
            }
            if (!Schema::hasColumn('backup_configs', 'storage_remote_id')) {
                $table->foreignUuid('storage_remote_id')->nullable()->after('destination_config');
            }

            // Add custom schedule fields
            if (!Schema::hasColumn('backup_configs', 'schedule_time')) {
                $table->string('schedule_time')->nullable()->after('schedule'); // HH:MM format
            }
            if (!Schema::hasColumn('backup_configs', 'schedule_day')) {
                $table->string('schedule_day')->nullable()->after('schedule_time'); // day of week (0-6) or day of month (1-31)
            }
            if (!Schema::hasColumn('backup_configs', 'schedule_cron')) {
                $table->string('schedule_cron')->nullable()->after('schedule_day'); // Full cron expression for advanced users
            }
        });

        // Modify destination_type enum to include 'rclone'
        // SQLite doesn't support ALTER COLUMN for enum, so we use a workaround
        if (DB::getDriverName() !== 'sqlite') {
            try {
                DB::statement("ALTER TABLE backup_configs MODIFY COLUMN destination_type ENUM('local', 's3', 'ftp', 'b2', 'rclone') DEFAULT 'local'");
            } catch (\Exception $e) {
                // Column might already have the correct type
            }
        }

        // Migrate existing data: convert single destination to array
        DB::table('backup_configs')->whereNull('destinations')->update([
            'destinations' => DB::raw("JSON_ARRAY(destination_type)")
        ]);
    }

    public function down(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->dropColumn(['destinations', 'backup_items', 'storage_remote_id', 'schedule_time', 'schedule_day', 'schedule_cron']);
        });
    }
};
