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
        Schema::create('mail_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->boolean('is_active')->default(true);
            $table->string('catch_all_address')->nullable();
            $table->unsignedInteger('max_accounts')->default(100);
            $table->unsignedInteger('default_quota_mb')->default(1024);
            $table->boolean('dkim_enabled')->default(false);
            $table->string('dkim_selector')->nullable();
            $table->text('dkim_private_key')->nullable();
            $table->text('dkim_public_key')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('domain_id')
                ->references('id')
                ->on('domains')
                ->onDelete('cascade');

            $table->unique('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_domains');
    }
};
