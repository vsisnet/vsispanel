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
        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('username', 64)->unique();
            $table->string('password'); // Hashed password
            $table->string('home_directory');
            $table->string('status')->default('active'); // active, suspended, disabled
            $table->unsignedBigInteger('quota_mb')->nullable(); // Quota in MB, null = unlimited
            $table->unsignedBigInteger('bandwidth_mb')->nullable(); // Bandwidth limit in MB/month
            $table->unsignedBigInteger('upload_bandwidth_kbps')->nullable(); // Upload speed limit KB/s
            $table->unsignedBigInteger('download_bandwidth_kbps')->nullable(); // Download speed limit KB/s
            $table->unsignedInteger('max_connections')->default(2); // Max concurrent connections
            $table->unsignedInteger('max_connections_per_ip')->default(2); // Max connections per IP
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->json('denied_ips')->nullable(); // IP blacklist
            $table->boolean('allow_upload')->default(true);
            $table->boolean('allow_download')->default(true);
            $table->boolean('allow_mkdir')->default(true);
            $table->boolean('allow_delete')->default(true);
            $table->boolean('allow_rename')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->unsignedBigInteger('total_uploaded_bytes')->default(0);
            $table->unsignedBigInteger('total_downloaded_bytes')->default(0);
            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['domain_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ftp_accounts');
    }
};
