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
        Schema::create('mail_forwards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mail_account_id');
            $table->string('forward_to');
            $table->boolean('keep_copy')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('mail_account_id')
                ->references('id')
                ->on('mail_accounts')
                ->onDelete('cascade');

            $table->index('mail_account_id');
            $table->unique(['mail_account_id', 'forward_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_forwards');
    }
};
