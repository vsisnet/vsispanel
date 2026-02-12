<?php

declare(strict_types=1);

namespace App\Modules\Migration\Jobs;

use App\Modules\Migration\Models\MigrationJob;
use App\Modules\Migration\Services\MigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours
    public int $tries = 1;

    public function __construct(
        public MigrationJob $migrationJob
    ) {
        $this->onQueue('installs');
    }

    public function handle(MigrationService $service): void
    {
        Log::channel('commands')->info("Starting migration job {$this->migrationJob->id}");

        try {
            $service->executeJob($this->migrationJob);
        } catch (\Exception $e) {
            Log::channel('commands')->error("Migration job {$this->migrationJob->id} failed: {$e->getMessage()}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->migrationJob->markFailed($exception->getMessage());
        Log::channel('commands')->error("Migration job {$this->migrationJob->id} failed: {$exception->getMessage()}");
    }
}
