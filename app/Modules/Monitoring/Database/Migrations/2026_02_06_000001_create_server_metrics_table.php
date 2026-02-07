<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->float('cpu_usage')->default(0);
            $table->bigInteger('memory_used')->default(0);
            $table->bigInteger('memory_total')->default(0);
            $table->json('disk_usage')->nullable();
            $table->bigInteger('network_in')->default(0);
            $table->bigInteger('network_out')->default(0);
            $table->float('load_1m')->default(0);
            $table->float('load_5m')->default(0);
            $table->float('load_15m')->default(0);
            $table->integer('processes_total')->default(0);
            $table->integer('processes_running')->default(0);
            $table->timestamp('recorded_at')->useCurrent();
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
