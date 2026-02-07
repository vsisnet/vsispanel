<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category', 30);
            $table->string('metric');
            $table->string('condition');
            $table->float('threshold');
            $table->string('severity', 20);
            $table->json('config')->nullable();
            $table->integer('cooldown_minutes')->default(15);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_templates');
    }
};
