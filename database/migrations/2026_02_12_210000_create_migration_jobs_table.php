<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->enum('source_type', ['plesk', 'cpanel', 'aapanel', 'directadmin', 'ssh']);
            $table->string('source_host');
            $table->integer('source_port')->default(22);
            $table->text('source_credentials')->nullable(); // encrypted JSON
            $table->json('items')->nullable(); // what to migrate
            $table->json('discovered_data')->nullable(); // discovered accounts/domains
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->longText('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_jobs');
    }
};
