<?php

declare(strict_types=1);

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
        Schema::table('backups', function (Blueprint $table) {
            if (!Schema::hasColumn('backups', 'synced_remotes')) {
                $table->json('synced_remotes')->nullable()->after('remote_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            if (Schema::hasColumn('backups', 'synced_remotes')) {
                $table->dropColumn('synced_remotes');
            }
        });
    }
};
