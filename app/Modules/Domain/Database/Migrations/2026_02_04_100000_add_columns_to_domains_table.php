<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('domains', 'web_server_type')) {
                $table->enum('web_server_type', ['nginx', 'apache'])->default('nginx')->after('is_main');
            }

            if (!Schema::hasColumn('domains', 'access_log')) {
                $table->string('access_log')->nullable()->after('web_server_type');
            }

            if (!Schema::hasColumn('domains', 'error_log')) {
                $table->string('error_log')->nullable()->after('access_log');
            }

            if (!Schema::hasColumn('domains', 'disk_used')) {
                $table->unsignedBigInteger('disk_used')->default(0)->after('error_log');
            }

            if (!Schema::hasColumn('domains', 'bandwidth_used')) {
                $table->unsignedBigInteger('bandwidth_used')->default(0)->after('disk_used');
            }

            if (!Schema::hasColumn('domains', 'ssl_expires_at')) {
                $table->timestamp('ssl_expires_at')->nullable()->after('bandwidth_used');
            }
        });

        // Update status enum if needed
        // Note: MySQL doesn't easily allow altering ENUM values, so we skip this
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $columns = [
                'web_server_type',
                'access_log',
                'error_log',
                'disk_used',
                'bandwidth_used',
                'ssl_expires_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('domains', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
