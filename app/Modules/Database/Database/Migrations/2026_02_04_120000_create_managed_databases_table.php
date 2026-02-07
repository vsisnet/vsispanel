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
        Schema::create('managed_databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 64); // MySQL database name (prefixed)
            $table->string('original_name', 64); // Name without prefix
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('charset', 32)->default('utf8mb4');
            $table->string('collation', 64)->default('utf8mb4_unicode_ci');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'original_name']);
            $table->index('name');
            $table->index('status');
        });

        Schema::create('database_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('username', 32); // MySQL username (prefixed)
            $table->string('original_username', 32); // Username without prefix
            $table->string('host', 255)->default('localhost');
            $table->json('privileges')->nullable(); // Privileges JSON array
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'original_username', 'host']);
            $table->index('username');
        });

        Schema::create('database_database_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('managed_database_id')->constrained('managed_databases')->cascadeOnDelete();
            $table->foreignUuid('database_user_id')->constrained('database_users')->cascadeOnDelete();
            $table->json('privileges')->nullable(); // Specific privileges for this database
            $table->timestamps();

            $table->unique(['managed_database_id', 'database_user_id'], 'db_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_database_user');
        Schema::dropIfExists('database_users');
        Schema::dropIfExists('managed_databases');
    }
};
