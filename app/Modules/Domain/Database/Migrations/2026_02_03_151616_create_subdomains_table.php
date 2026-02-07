<?php

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
        Schema::create('subdomains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('document_root')->nullable();
            $table->string('php_version')->default('8.3');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('domain_id');

            // Unique constraint
            $table->unique(['domain_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subdomains');
    }
};
