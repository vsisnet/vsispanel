<?php

declare(strict_types=1);

namespace App\Modules\SSL\Console\Commands;

use App\Modules\SSL\Jobs\RenewExpiringCertificates;
use Illuminate\Console\Command;

class RenewSslCertificates extends Command
{
    protected $signature = 'ssl:renew
        {--days=30 : Days before expiry to trigger renewal}
        {--sync : Run synchronously instead of queuing}';

    protected $description = 'Renew expiring SSL certificates';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $sync = $this->option('sync');

        $this->info("Processing SSL certificates expiring within {$days} days...");

        if ($sync) {
            $job = new RenewExpiringCertificates($days);
            $job->handle(app(\App\Modules\SSL\Services\SslService::class));
            $this->info('SSL certificate renewal completed synchronously.');
        } else {
            RenewExpiringCertificates::dispatch($days);
            $this->info('SSL certificate renewal job dispatched to queue.');
        }

        return self::SUCCESS;
    }
}
