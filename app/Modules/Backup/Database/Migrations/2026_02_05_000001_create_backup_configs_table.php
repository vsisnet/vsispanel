<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['full', 'files', 'databases', 'emails', 'config'])->default('full');
            $table->enum('destination_type', ['local', 's3', 'ftp', 'b2'])->default('local');
            $table->text('destination_config')->nullable(); // Encrypted JSON
            $table->string('schedule')->nullable(); // Cron expression
            $table->json('retention_policy')->nullable();
            $table->json('include_paths')->nullable();
            $table->json('exclude_patterns')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['next_run_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_configs');
    }
};
