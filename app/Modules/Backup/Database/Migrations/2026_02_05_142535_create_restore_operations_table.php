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
        Schema::create('restore_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('backup_id');
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->string('target_path');
            $table->json('include_paths')->nullable();
            $table->bigInteger('files_restored')->default(0);
            $table->bigInteger('bytes_restored')->default(0);
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('backup_id')->references('id')->on('backups')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index(['backup_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restore_operations');
    }
};
