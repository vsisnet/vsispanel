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
        Schema::table('subdomains', function (Blueprint $table) {
            if (!Schema::hasColumn('subdomains', 'status')) {
                $table->enum('status', ['active', 'suspended', 'disabled'])->default('active')->after('php_version');
            }

            if (!Schema::hasColumn('subdomains', 'ssl_enabled')) {
                $table->boolean('ssl_enabled')->default(false)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subdomains', function (Blueprint $table) {
            if (Schema::hasColumn('subdomains', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('subdomains', 'ssl_enabled')) {
                $table->dropColumn('ssl_enabled');
            }
        });
    }
};
