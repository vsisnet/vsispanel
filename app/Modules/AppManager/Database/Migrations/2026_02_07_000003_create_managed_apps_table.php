<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('category', 30);
            $table->string('type', 20); // single, multi_version
            $table->string('status', 20)->default('not_installed');
            $table->string('installed_version')->nullable();
            $table->json('installed_versions')->nullable();
            $table->string('active_version')->nullable();
            $table->string('service_name')->nullable();
            $table->boolean('is_running')->default(false);
            $table->boolean('is_enabled')->default(false);
            $table->json('config_files')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_apps');
    }
};
