<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_remotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->string('type', 20);
            $table->text('config'); // Encrypted JSON
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_result')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        // Add storage_remote_id to backup_configs table
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->foreignUuid('storage_remote_id')
                ->nullable()
                ->after('destination_config')
                ->constrained('storage_remotes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('backup_configs', function (Blueprint $table) {
            $table->dropForeign(['storage_remote_id']);
            $table->dropColumn('storage_remote_id');
        });

        Schema::dropIfExists('storage_remotes');
    }
};
