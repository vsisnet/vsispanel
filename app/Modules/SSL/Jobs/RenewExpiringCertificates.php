<?php

declare(strict_types=1);

namespace App\Modules\SSL\Jobs;

use App\Modules\SSL\Models\SslCertificate;
use App\Modules\SSL\Services\SslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenewExpiringCertificates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected int $daysBeforeExpiry = 30
    ) {}

    public function handle(SslService $sslService): void
    {
        Log::info('Starting SSL certificate auto-renewal job', [
            'days_before_expiry' => $this->daysBeforeExpiry,
        ]);

        $result = $sslService->processAutoRenewals($this->daysBeforeExpiry);

        Log::info('SSL certificate auto-renewal job completed', [
            'processed' => $result['processed'],
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
        ]);
    }
}
