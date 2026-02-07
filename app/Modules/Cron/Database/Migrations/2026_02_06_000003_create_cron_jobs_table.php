<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('command');
            $table->string('schedule'); // cron expression
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('run_as_user')->nullable();
            $table->string('output_handling')->default('discard'); // discard, email, log
            $table->string('output_email')->nullable();
            $table->string('log_path')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable(); // success, failed, running
            $table->text('last_run_output')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
