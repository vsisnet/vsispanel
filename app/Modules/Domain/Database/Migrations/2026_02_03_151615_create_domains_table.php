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
        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('subscription_id')->constrained()->onDelete('cascade');

            $table->string('name')->unique();
            $table->string('document_root')->nullable();
            $table->string('php_version')->default('8.3');

            $table->enum('status', ['active', 'suspended', 'disabled'])->default('active');
            $table->boolean('ssl_enabled')->default(false);
            $table->boolean('is_main')->default(false)->comment('Main domain for subscription');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('user_id');
            $table->index('subscription_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
