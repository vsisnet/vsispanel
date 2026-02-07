<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('type', 50)->index(); // backup.create, backup.restore, service.start, etc.
            $table->string('name'); // Human readable name
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, running, completed, failed, cancelled
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->string('related_type')->nullable(); // Polymorphic relation type
            $table->uuid('related_id')->nullable()->index(); // Polymorphic relation id
            $table->json('input_data')->nullable(); // Input parameters for the task
            $table->longText('output')->nullable(); // Task output/logs
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();
            $table->softDeletes();

            // Composite index for common queries
            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
