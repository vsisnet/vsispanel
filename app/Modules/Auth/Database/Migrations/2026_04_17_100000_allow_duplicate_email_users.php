<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique constraint on email (allow multiple users per email)
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });
        // Keep email indexed for lookup (non-unique)
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'users_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
            $table->unique('email');
        });
    }
};
