<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->string('category', 30)->default('resource')->after('name');
            $table->string('severity', 20)->default('warning')->after('category');
            $table->json('config')->nullable()->after('notification_channels');
        });

        Schema::table('alert_history', function (Blueprint $table) {
            $table->string('severity', 20)->default('warning')->after('message');
            $table->string('category', 30)->nullable()->after('severity');
            $table->string('status', 20)->default('triggered')->after('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('alert_history', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['severity', 'category', 'status']);
        });

        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropColumn(['category', 'severity', 'config']);
        });
    }
};
