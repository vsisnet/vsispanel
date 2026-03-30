<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Here you may define all of your scheduled tasks. Laravel's scheduler
| checks these tasks every minute and runs them when due.
|
*/

// Run scheduled backups every minute (the command checks next_run_at internally)
Schedule::command('backup:run-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduled-backups.log'));

// Collect server metrics every minute
Schedule::job(new \App\Modules\Monitoring\Jobs\CollectMetricsJob())
    ->everyMinute()
    ->withoutOverlapping();

// Cleanup old metrics daily
Schedule::job(new \App\Modules\Monitoring\Jobs\CleanupOldMetrics())
    ->daily()
    ->at('03:00');

// Auto-fail stuck tasks and backups every 15 minutes
Schedule::command('task:cleanup-stuck --hours=2')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/task-cleanup.log'));
