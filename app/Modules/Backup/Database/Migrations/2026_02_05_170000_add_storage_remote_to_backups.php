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
        Schema::table('backups', function (Blueprint $table) {
            $table->uuid('storage_remote_id')->nullable()->after('metadata');
            $table->string('remote_path', 500)->nullable()->after('storage_remote_id');

            $table->foreign('storage_remote_id')
                ->references('id')
                ->on('storage_remotes')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropForeign(['storage_remote_id']);
            $table->dropColumn(['storage_remote_id', 'remote_path']);
        });
    }
};
