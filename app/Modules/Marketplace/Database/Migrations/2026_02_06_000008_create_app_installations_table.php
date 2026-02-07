<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_installations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->uuid('app_template_id');
            $table->uuid('installed_by');
            $table->string('app_version');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('progress')->default(0);
            $table->string('current_step')->nullable();
            $table->text('logs')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('domain_id')->references('id')->on('domains')->cascadeOnDelete();
            $table->foreign('app_template_id')->references('id')->on('app_templates')->cascadeOnDelete();
            $table->foreign('installed_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_installations');
    }
};
