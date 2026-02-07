<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')
                ->constrained('domains')
                ->onDelete('cascade');
            $table->string('zone_name')->unique();
            $table->unsignedBigInteger('serial')->default(1);
            $table->integer('refresh')->default(10800); // 3 hours
            $table->integer('retry')->default(3600); // 1 hour
            $table->integer('expire')->default(604800); // 1 week
            $table->integer('minimum_ttl')->default(3600); // 1 hour
            $table->string('primary_ns')->default('ns1.example.com');
            $table->string('admin_email')->default('admin.example.com');
            $table->enum('status', ['active', 'disabled', 'pending'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_zones');
    }
};
