<?php

declare(strict_types=1);

namespace App\Modules\AppManager\Jobs;

use App\Modules\AppManager\Models\ManagedApp;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Services\TaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ManagePhpExtensionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes

    protected ?Task $task = null;
    protected ?TaskService $taskService = null;

    /**
     * @param  string  $action  'install' or 'uninstall'
     * @param  array  $packages  e.g. ['php8.3-curl', 'php8.3-gd']
     */
    public function __construct(
        public readonly ManagedApp $app,
        public readonly string $version,
        public readonly string $action,
        public readonly array $packages,
        public readonly ?string $taskId = null,
    ) {
        $this->queue = 'installs';
    }

    public function handle(TaskService $taskService): void
    {
        $this->taskService = $taskService;

        if ($this->taskId) {
            $this->task = Task::find($this->taskId);
            if ($this->task) {
                $this->taskService->start($this->task);
            }
        }

        $verb = $this->action === 'install' ? 'Installing' : 'Removing';
        $packageList = implode(', ', $this->packages);

        Log::info("PHP extension {$this->action}", [
            'version' => $this->version,
            'packages' => $this->packages,
            'task_id' => $this->taskId,
        ]);

        $this->updateTask(5, "{$verb} PHP extensions: {$packageList}");

        try {
            if ($this->action === 'install') {
                $this->installExtensions();
            } else {
                $this->uninstallExtensions();
            }
        } catch (\Exception $e) {
            Log::error("PHP extension {$this->action} failed: " . $e->getMessage());
            $this->failTask("{$verb} failed: {$e->getMessage()}");
        }
    }

    private function installExtensions(): void
    {
        // Step 1: apt-get update (quick, just for PHP packages)
        $this->updateTask(10, 'Updating package lists...');
        $updateResult = Process::timeout(120)->run('apt-get update -y 2>&1');
        if (! $updateResult->successful()) {
            $this->updateTask(15, 'Warning: apt-get update returned non-zero, continuing...');
        }

        // Step 2: Install packages
        $this->updateTask(20, 'Installing packages: ' . implode(', ', $this->packages));
        $installCmd = 'DEBIAN_FRONTEND=noninteractive apt-get install -y '
            . implode(' ', array_map('escapeshellarg', $this->packages))
            . ' 2>&1';

        $result = Process::timeout(240)->run($installCmd);
        $output = $result->output();

        // Stream output lines as progress
        $this->appendLines($output, 25, 80);

        if (! $result->successful()) {
            $this->failTask("Package installation failed:\n{$output}");

            return;
        }

        // Step 3: Restart PHP-FPM
        $this->updateTask(85, "Restarting php{$this->version}-fpm...");
        $restartResult = Process::timeout(30)->run("systemctl restart php{$this->version}-fpm 2>&1");
        if ($restartResult->successful()) {
            $this->updateTask(95, "php{$this->version}-fpm restarted.");
        } else {
            $this->updateTask(95, "Warning: Could not restart php{$this->version}-fpm: " . $restartResult->errorOutput());
        }

        $extNames = array_map(fn ($p) => str_replace("php{$this->version}-", '', $p), $this->packages);
        $this->completeTask('Installed extensions: ' . implode(', ', $extNames));
    }

    private function uninstallExtensions(): void
    {
        // Step 1: Remove packages
        $this->updateTask(15, 'Removing packages: ' . implode(', ', $this->packages));
        $removeCmd = 'DEBIAN_FRONTEND=noninteractive apt-get remove -y '
            . implode(' ', array_map('escapeshellarg', $this->packages))
            . ' 2>&1';

        $result = Process::timeout(120)->run($removeCmd);
        $output = $result->output();

        $this->appendLines($output, 20, 70);

        if (! $result->successful()) {
            $this->failTask("Package removal failed:\n{$output}");

            return;
        }

        // Step 2: Autoremove unused deps
        $this->updateTask(75, 'Cleaning up unused packages...');
        Process::timeout(60)->run('apt-get autoremove -y 2>&1');

        // Step 3: Restart PHP-FPM
        $this->updateTask(85, "Restarting php{$this->version}-fpm...");
        $restartResult = Process::timeout(30)->run("systemctl restart php{$this->version}-fpm 2>&1");
        if ($restartResult->successful()) {
            $this->updateTask(95, "php{$this->version}-fpm restarted.");
        } else {
            $this->updateTask(95, "Warning: Could not restart php{$this->version}-fpm: " . $restartResult->errorOutput());
        }

        $extNames = array_map(fn ($p) => str_replace("php{$this->version}-", '', $p), $this->packages);
        $this->completeTask('Removed extensions: ' . implode(', ', $extNames));
    }

    private function appendLines(string $output, int $startProgress, int $endProgress): void
    {
        $lines = array_filter(explode("\n", $output), fn ($l) => trim($l) !== '');
        $total = count($lines);
        if ($total === 0) {
            return;
        }

        $step = max(1, (int) ceil($total / 10));
        $progressRange = $endProgress - $startProgress;

        foreach ($lines as $i => $line) {
            if ($i % $step === 0 || $i === $total - 1) {
                $p = $startProgress + (int) (($i / $total) * $progressRange);
                $this->updateTask($p, trim($line));
            }
        }
    }

    protected function updateTask(int $progress, string $message): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->updateProgress($this->task, $progress, $message);
        }
    }

    protected function completeTask(string $message): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->complete($this->task, $message);
        }
    }

    protected function failTask(string $error): void
    {
        if ($this->task && $this->taskService) {
            $this->taskService->fail($this->task, $error);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ManagePhpExtensionJob failed', [
            'action' => $this->action,
            'version' => $this->version,
            'packages' => $this->packages,
            'error' => $exception->getMessage(),
        ]);

        if ($this->taskId) {
            $task = Task::find($this->taskId);
            if ($task) {
                app(TaskService::class)->fail($task, $exception->getMessage());
            }
        }
    }
}
