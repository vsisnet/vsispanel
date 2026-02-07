<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('metric'); // cpu, memory, disk, network, service_down, ssl_expiry, backup_failed
            $table->string('condition'); // above, below, equals
            $table->float('threshold');
            $table->integer('duration_seconds')->default(0);
            $table->json('notification_channels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('cooldown_minutes')->default(15);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('alert_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->float('current_value');
            $table->boolean('notification_sent')->default(false);
            $table->string('notification_channel')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->index('triggered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_history');
        Schema::dropIfExists('alert_rules');
    }
};
