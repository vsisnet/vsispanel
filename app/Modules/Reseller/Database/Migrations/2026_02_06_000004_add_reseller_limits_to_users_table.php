<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('max_customers')->nullable()->after('status');
            $table->integer('max_disk_mb')->nullable()->after('max_customers');
            $table->integer('max_bandwidth_mb')->nullable()->after('max_disk_mb');
            $table->integer('max_domains')->nullable()->after('max_bandwidth_mb');
            $table->integer('max_databases')->nullable()->after('max_domains');
            $table->integer('max_email_accounts')->nullable()->after('max_databases');
            $table->integer('max_ftp_accounts')->nullable()->after('max_email_accounts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'max_customers', 'max_disk_mb', 'max_bandwidth_mb',
                'max_domains', 'max_databases', 'max_email_accounts', 'max_ftp_accounts',
            ]);
        });
    }
};
