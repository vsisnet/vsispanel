<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('backup_config_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['full', 'files', 'databases', 'emails', 'config'])->default('full');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->bigInteger('size_bytes')->nullable();
            $table->string('snapshot_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['backup_config_id', 'status']);
            $table->index('snapshot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
