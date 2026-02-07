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
        Schema::create('mail_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mail_domain_id');
            $table->string('source_address'); // The alias address (e.g., sales@domain.com)
            $table->string('destination_address'); // Where to forward (e.g., user@domain.com)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('mail_domain_id')
                ->references('id')
                ->on('mail_domains')
                ->onDelete('cascade');

            $table->index('mail_domain_id');
            $table->unique(['mail_domain_id', 'source_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_aliases');
    }
};
