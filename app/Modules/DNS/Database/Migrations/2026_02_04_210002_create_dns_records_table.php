<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dns_zone_id')
                ->constrained('dns_zones')
                ->onDelete('cascade');
            $table->string('name'); // Record name (@ for root, www, mail, etc.)
            $table->enum('type', [
                'A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'NS', 'CAA', 'PTR', 'SOA'
            ]);
            $table->text('content'); // Record value
            $table->integer('ttl')->default(3600);
            $table->integer('priority')->nullable(); // For MX, SRV records
            $table->integer('weight')->nullable(); // For SRV records
            $table->integer('port')->nullable(); // For SRV records
            $table->boolean('disabled')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dns_zone_id', 'type']);
            $table->index(['dns_zone_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
