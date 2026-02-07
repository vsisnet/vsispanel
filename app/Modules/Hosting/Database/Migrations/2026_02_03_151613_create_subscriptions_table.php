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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('plan_id')->constrained()->onDelete('restrict');

            $table->enum('status', ['active', 'suspended', 'expired', 'cancelled'])->default('active');

            // Usage tracking (in MB)
            $table->unsignedBigInteger('disk_used')->default(0)->comment('In MB');
            $table->unsignedBigInteger('bandwidth_used')->default(0)->comment('In MB');

            // Validity period
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
