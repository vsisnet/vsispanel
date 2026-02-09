<?php

declare(strict_types=1);

namespace App\Modules\Cron\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cron\Models\CronJob;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class CronService
{
    /**
     * Create a new cron job.
     */
    public function create(User $user, array $data): CronJob
    {
        $data['user_id'] = $user->id;
        $data['run_as_user'] = $data['run_as_user'] ?? $user->username ?? 'root';
        $data['next_run_at'] = $this->getNextRun($data['schedule']);

        $job = CronJob::create($data);
        $this->syncCrontab($user);

        return $job;
    }

    /**
     * Update a cron job.
     */
    public function update(CronJob $job, array $data): CronJob
    {
        if (isset($data['schedule'])) {
            $data['next_run_at'] = $this->getNextRun($data['schedule']);
        }

        $job->update($data);
        $this->syncCrontab($job->user);

        return $job->fresh();
    }

    /**
     * Delete a cron job.
     */
    public function delete(CronJob $job): void
    {
        $user = $job->user;
        $job->delete();
        $this->syncCrontab($user);
    }

    /**
     * Toggle cron job active state.
     */
    public function toggle(CronJob $job): CronJob
    {
        $job->update(['is_active' => !$job->is_active]);
        $this->syncCrontab($job->user);

        return $job->fresh();
    }

    /**
     * Execute a cron job immediately.
     */
    public function runNow(CronJob $job): string
    {
        $job->update(['last_run_status' => 'running', 'last_run_at' => now()]);

        try {
            $runAs = $job->run_as_user ?? 'root';
            $result = Process::timeout(300)->run("sudo -u {$runAs} bash -c " . escapeshellarg($job->command));

            $output = $result->output() . $result->errorOutput();
            $output = mb_substr($output, 0, 10240); // Truncate to 10KB

            $job->update([
                'last_run_status' => $result->successful() ? 'success' : 'failed',
                'last_run_output' => $output,
                'next_run_at' => $this->getNextRun($job->schedule),
            ]);

            return $output;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $job->update([
                'last_run_status' => 'failed',
                'last_run_output' => $error,
                'next_run_at' => $this->getNextRun($job->schedule),
            ]);

            return $error;
        }
    }

    /**
     * Rebuild user crontab from database.
     * Preserves system entries (Laravel scheduler, etc.) while managing user jobs.
     */
    public function syncCrontab(User $user): void
    {
        $username = $user->username ?? 'root';
        $jobs = CronJob::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        $lines = [];

        // System entries for root user (Laravel scheduler, etc.)
        if ($username === 'root') {
            $lines[] = '# VSISPanel system entries - DO NOT REMOVE';
            $lines[] = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
            $lines[] = '';
        }

        $lines[] = "# VSISPanel managed crontab for {$username}";
        $lines[] = "# DO NOT EDIT - managed by VSISPanel";

        foreach ($jobs as $job) {
            $cmd = $job->command;

            // Handle output
            $cmd = match ($job->output_handling) {
                'log' => $job->log_path ? "{$cmd} >> {$job->log_path} 2>&1" : "{$cmd} > /dev/null 2>&1",
                'email' => $cmd, // cron default behavior sends to MAILTO
                default => "{$cmd} > /dev/null 2>&1",
            };

            $lines[] = "{$job->schedule} {$cmd}";
        }

        $lines[] = ''; // trailing newline

        $crontabContent = implode("\n", $lines);
        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tempFile, $crontabContent);

        Process::timeout(10)->run("sudo crontab -u {$username} {$tempFile}");
        unlink($tempFile);

        Log::info("Synced crontab for user {$username}", ['jobs_count' => $jobs->count()]);
    }

    /**
     * Parse cron expression and return next N run times.
     */
    public function getNextRuns(string $expression, int $count = 5): array
    {
        try {
            $cron = new CronExpression($expression);
            $runs = [];

            for ($i = 0; $i < $count; $i++) {
                $runs[] = Carbon::instance($cron->getNextRunDate('now', $i))->format('Y-m-d H:i:s');
            }

            return $runs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get next run time.
     */
    public function getNextRun(string $expression): ?Carbon
    {
        try {
            $cron = new CronExpression($expression);
            return Carbon::instance($cron->getNextRunDate());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert cron expression to human-readable string.
     */
    public function toHumanReadable(string $expression): string
    {
        $presets = [
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Every hour',
            '0 */2 * * *' => 'Every 2 hours',
            '0 */6 * * *' => 'Every 6 hours',
            '0 */12 * * *' => 'Every 12 hours',
            '0 0 * * *' => 'Daily at midnight',
            '0 0 * * 0' => 'Weekly on Sunday',
            '0 0 1 * *' => 'Monthly on the 1st',
        ];

        return $presets[$expression] ?? $expression;
    }

    /**
     * Validate a cron expression.
     */
    public function isValidExpression(string $expression): bool
    {
        try {
            new CronExpression($expression);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
