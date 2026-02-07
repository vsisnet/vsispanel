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
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mail_domain_id');
            $table->uuid('user_id');
            $table->string('email')->unique();
            $table->string('username'); // Local part before @
            $table->string('password_hash');
            $table->unsignedBigInteger('quota_mb')->default(1024);
            $table->unsignedBigInteger('quota_used_bytes')->default(0);
            $table->enum('status', ['active', 'suspended', 'disabled'])->default('active');
            $table->boolean('auto_responder_enabled')->default(false);
            $table->string('auto_responder_subject')->nullable();
            $table->text('auto_responder_message')->nullable();
            $table->timestamp('auto_responder_start_at')->nullable();
            $table->timestamp('auto_responder_end_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('mail_domain_id')
                ->references('id')
                ->on('mail_domains')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['mail_domain_id', 'status']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
