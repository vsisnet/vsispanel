<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('action', ['allow', 'deny', 'limit', 'reject'])->default('allow');
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->enum('protocol', ['tcp', 'udp', 'any'])->default('any');
            $table->string('port')->nullable(); // e.g. "80", "8000:8100", "22,80,443"
            $table->string('source_ip')->nullable(); // e.g. "192.168.1.1", "192.168.1.0/24"
            $table->string('destination_ip')->nullable();
            $table->string('comment')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_essential')->default(false); // Cannot delete essential rules
            $table->integer('priority')->default(100); // Lower = higher priority
            $table->integer('ufw_rule_number')->nullable(); // Synced from UFW status
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'priority']);
            $table->index('is_essential');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
