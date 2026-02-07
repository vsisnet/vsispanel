<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->enum('type', ['lets_encrypt', 'custom'])->default('lets_encrypt');
            $table->enum('status', ['pending', 'active', 'expired', 'revoked', 'failed'])->default('pending');
            $table->string('certificate_path')->nullable();
            $table->string('private_key_path')->nullable();
            $table->string('ca_bundle_path')->nullable();
            $table->string('issuer')->nullable();
            $table->string('serial_number')->nullable();
            $table->json('san')->nullable(); // Subject Alternative Names
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->unsignedSmallInteger('renewal_attempts')->default(0);
            $table->timestamp('last_renewal_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index(['domain_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
