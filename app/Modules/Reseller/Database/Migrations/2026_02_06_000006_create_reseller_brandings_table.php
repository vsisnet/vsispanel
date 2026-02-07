<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_brandings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reseller_id')->unique();
            $table->string('company_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color', 7)->default('#1A5276');
            $table->text('custom_css')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_url')->nullable();
            $table->json('nameservers')->nullable();
            $table->timestamps();

            $table->foreign('reseller_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_brandings');
    }
};
