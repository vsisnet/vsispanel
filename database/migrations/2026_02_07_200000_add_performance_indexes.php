<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add indexes for commonly queried columns to improve performance.
     */
    public function up(): void
    {
        // Domains: frequently filtered by name and user+status
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->index('name', 'idx_domains_name');
                $table->index(['user_id', 'status'], 'idx_domains_user_status');
            });
        }

        // Backup configs: due() scope queries is_active + next_run_at
        if (Schema::hasTable('backup_configs')) {
            Schema::table('backup_configs', function (Blueprint $table) {
                $table->index(['is_active', 'next_run_at'], 'idx_backup_configs_active_nextrun');
            });
        }

        // Cron jobs: filtered by user + active status
        if (Schema::hasTable('cron_jobs')) {
            Schema::table('cron_jobs', function (Blueprint $table) {
                $table->index(['user_id', 'is_active'], 'idx_cron_jobs_user_active');
            });
        }

        // Alert rules: frequently filtered by is_active
        if (Schema::hasTable('alert_rules')) {
            Schema::table('alert_rules', function (Blueprint $table) {
                $table->index('is_active', 'idx_alert_rules_active');
            });
        }

        // Managed apps: looked up by slug
        if (Schema::hasTable('managed_apps')) {
            Schema::table('managed_apps', function (Blueprint $table) {
                $table->index('slug', 'idx_managed_apps_slug');
            });
        }

        // System settings: queried by group+key
        if (Schema::hasTable('system_settings')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->index(['group', 'key'], 'idx_system_settings_group_key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropIndex('idx_domains_name');
                $table->dropIndex('idx_domains_user_status');
            });
        }

        if (Schema::hasTable('backup_configs')) {
            Schema::table('backup_configs', function (Blueprint $table) {
                $table->dropIndex('idx_backup_configs_active_nextrun');
            });
        }

        if (Schema::hasTable('cron_jobs')) {
            Schema::table('cron_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_cron_jobs_user_active');
            });
        }

        if (Schema::hasTable('alert_rules')) {
            Schema::table('alert_rules', function (Blueprint $table) {
                $table->dropIndex('idx_alert_rules_active');
            });
        }

        if (Schema::hasTable('managed_apps')) {
            Schema::table('managed_apps', function (Blueprint $table) {
                $table->dropIndex('idx_managed_apps_slug');
            });
        }

        if (Schema::hasTable('system_settings')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropIndex('idx_system_settings_group_key');
            });
        }
    }
};
