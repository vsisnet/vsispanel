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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->unique();

            // Limits (in MB)
            $table->unsignedInteger('disk_limit')->comment('In MB');
            $table->unsignedInteger('bandwidth_limit')->comment('In MB per month');

            // Resource limits
            $table->unsignedInteger('domains_limit')->default(1);
            $table->unsignedInteger('subdomains_limit')->default(10);
            $table->unsignedInteger('databases_limit')->default(5);
            $table->unsignedInteger('email_accounts_limit')->default(10);
            $table->unsignedInteger('ftp_accounts_limit')->default(5);

            // Configuration
            $table->string('php_version_default')->default('8.3');
            $table->boolean('is_active')->default(true);

            // Creator
            $table->foreignUuid('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
