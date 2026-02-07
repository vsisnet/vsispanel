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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Rename starts_at to started_at for consistency
            $table->renameColumn('starts_at', 'started_at');

            // Add suspension tracking columns
            $table->timestamp('suspended_at')->nullable()->after('expires_at');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
            $table->renameColumn('started_at', 'starts_at');
        });
    }
};
