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

class UninstallAppVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    protected ?Task $task = null;
    protected ?TaskService $taskService = null;

    public function __construct(
        public readonly ManagedApp $app,
        public readonly ?string $version = null,
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
                $this->updateTask(5, "Starting uninstall of {$this->app->name}" . ($this->version ? " {$this->version}" : '') . '...');
            }
        }

        Log::info('Starting app uninstall job', [
            'app' => $this->app->slug,
            'version' => $this->version,
            'task_id' => $this->taskId,
        ]);

        $this->app->update(['status' => 'uninstalling']);

        try {
            $config = config("appmanager.apps.{$this->app->slug}", []);

            match ($this->app->slug) {
                'php' => $this->uninstallPhp($config),
                'nodejs' => $this->uninstallNodejs($config),
                'python' => $this->uninstallPython($config),
                'composer' => $this->uninstallComposer(),
                default => $config['type'] === 'multi_version'
                    ? $this->uninstallMultiVersionGeneric($config)
                    : $this->uninstallSingleVersionGeneric($config),
            };
        } catch (\Exception $e) {
            $this->app->update(['status' => $this->app->installed_versions ? 'installed' : 'error']);
            Log::error("Uninstall failed for {$this->app->slug}: " . $e->getMessage());
            $this->failTask("Uninstall failed: {$e->getMessage()}");
        }
    }

    // ──────────────────────────────────────────────
    // PHP
    // ──────────────────────────────────────────────
    private function uninstallPhp(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required for PHP uninstall');
        }

        // Step 1: Stop service
        $serviceName = str_replace('{version}', $version, $config['service_pattern'] ?? '');
        if ($serviceName) {
            $this->updateTask(10, "Stopping service {$serviceName}...");
            Process::timeout(15)->run("systemctl stop " . escapeshellarg($serviceName) . " 2>&1");

            $this->updateTask(20, "Disabling service {$serviceName}...");
            Process::timeout(15)->run("systemctl disable " . escapeshellarg($serviceName) . " 2>&1");
        }

        // Step 2: Remove packages
        $packages = ["php{$version}-*"];
        $this->updateTask(30, 'Removing PHP ' . $version . ' packages...');

        $result = Process::timeout(120)->run('DEBIAN_FRONTEND=noninteractive apt-get remove --purge -y ' . escapeshellarg("php{$version}-*") . ' 2>&1');
        $this->appendLines($result->output(), 35, 70);

        // Step 3: Autoremove
        $this->updateTask(75, 'Running autoremove...');
        Process::timeout(60)->run('apt-get autoremove -y 2>&1');

        // Step 4: Update database
        $versions = array_values(array_diff($this->app->installed_versions ?? [], [$version]));
        $activeVersion = $this->app->active_version;

        if ($activeVersion === $version) {
            $activeVersion = ! empty($versions) ? $versions[count($versions) - 1] : null;

            // Update alternatives if needed
            if ($activeVersion) {
                Process::timeout(5)->run("update-alternatives --set php /usr/bin/php{$activeVersion} 2>/dev/null");
            }
        }

        $this->app->update([
            'status' => ! empty($versions) ? 'installed' : 'not_installed',
            'installed_versions' => $versions,
            'active_version' => $activeVersion,
        ]);

        $this->updateTask(90, 'Database updated.');
        $this->completeTask("PHP {$version} uninstalled successfully!");
    }

    // ──────────────────────────────────────────────
    // Node.js
    // ──────────────────────────────────────────────
    private function uninstallNodejs(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required for Node.js uninstall');
        }

        $installDir = $config['install_dir'] ?? '/usr/local/lib/nodejs';
        $versionDir = "{$installDir}/node-{$version}";

        // Step 1: Remove symlinks
        $this->updateTask(15, 'Removing symlinks...');
        Process::timeout(5)->run("rm -f /usr/local/bin/node{$version} /usr/local/bin/npm{$version} /usr/local/bin/npx{$version}");

        // Step 2: Remove binary directory
        $this->updateTask(30, "Removing {$versionDir}...");
        if (is_dir($versionDir)) {
            Process::timeout(30)->run("rm -rf " . escapeshellarg($versionDir));
        }

        $this->updateTask(60, 'Directory removed.');

        // Step 3: Update database
        $versions = array_values(array_diff($this->app->installed_versions ?? [], [$version]));
        $activeVersion = $this->app->active_version;

        // If removing the active version, switch to another
        if ($activeVersion === $version) {
            $activeVersion = ! empty($versions) ? $versions[count($versions) - 1] : null;

            if ($activeVersion) {
                $newBinDir = "{$installDir}/node-{$activeVersion}/bin";
                Process::timeout(5)->run("ln -sf {$newBinDir}/node /usr/local/bin/node");
                Process::timeout(5)->run("ln -sf {$newBinDir}/npm /usr/local/bin/npm");
                Process::timeout(5)->run("ln -sf {$newBinDir}/npx /usr/local/bin/npx");
                $this->updateTask(75, "Switched default to Node.js {$activeVersion}.");
            } else {
                Process::timeout(5)->run('rm -f /usr/local/bin/node /usr/local/bin/npm /usr/local/bin/npx /usr/local/bin/pm2 /usr/local/bin/yarn');
                $this->updateTask(75, 'Removed all Node.js symlinks.');
            }
        }

        $this->app->update([
            'status' => ! empty($versions) ? 'installed' : 'not_installed',
            'installed_versions' => $versions,
            'active_version' => $activeVersion,
        ]);

        $this->updateTask(90, 'Database updated.');
        $this->completeTask("Node.js {$version} uninstalled successfully!");
    }

    // ──────────────────────────────────────────────
    // Python
    // ──────────────────────────────────────────────
    private function uninstallPython(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required for Python uninstall');
        }

        // Step 1: Remove packages
        $packages = [];
        if (! empty($config['package_pattern'])) {
            $packages[] = str_replace('{version}', $version, $config['package_pattern']);
        }
        foreach ($config['extra_packages'] ?? [] as $pkg) {
            $packages[] = str_replace('{version}', $version, $pkg);
        }

        $this->updateTask(15, 'Removing Python ' . $version . ' packages...');
        $result = Process::timeout(120)->run('DEBIAN_FRONTEND=noninteractive apt-get remove --purge -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1');
        $this->appendLines($result->output(), 20, 60);

        // Step 2: Autoremove
        $this->updateTask(65, 'Running autoremove...');
        Process::timeout(60)->run('apt-get autoremove -y 2>&1');

        // Step 3: Update database
        $versions = array_values(array_diff($this->app->installed_versions ?? [], [$version]));
        $activeVersion = $this->app->active_version;

        if ($activeVersion === $version) {
            $activeVersion = ! empty($versions) ? $versions[count($versions) - 1] : null;
        }

        $this->app->update([
            'status' => ! empty($versions) ? 'installed' : 'not_installed',
            'installed_versions' => $versions,
            'active_version' => $activeVersion,
        ]);

        $this->updateTask(90, 'Database updated.');
        $this->completeTask("Python {$version} uninstalled successfully!");
    }

    // ──────────────────────────────────────────────
    // Composer
    // ──────────────────────────────────────────────
    private function uninstallComposer(): void
    {
        $this->updateTask(20, 'Removing Composer...');
        Process::timeout(5)->run('rm -f /usr/local/bin/composer');

        $this->app->update(['status' => 'not_installed']);

        $this->updateTask(80, 'Composer removed.');
        $this->completeTask('Composer uninstalled successfully!');
    }

    // ──────────────────────────────────────────────
    // Generic
    // ──────────────────────────────────────────────
    private function uninstallSingleVersionGeneric(array $config): void
    {
        $packages = $config['packages'] ?? [];
        if (empty($packages)) {
            $this->failTask('No packages defined');

            return;
        }

        $this->updateTask(15, 'Removing packages...');
        $result = Process::timeout(120)->run('apt-get remove -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1');
        $this->appendLines($result->output(), 20, 70);

        $this->app->update(['status' => $result->successful() ? 'not_installed' : 'error']);

        if ($result->successful()) {
            Process::timeout(60)->run('apt-get autoremove -y 2>&1');
            $this->completeTask("Uninstalled {$this->app->name}");
        } else {
            $this->failTask('Uninstall failed: ' . $result->output());
        }
    }

    private function uninstallMultiVersionGeneric(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required');
        }

        $packages = [];
        if (! empty($config['package_pattern'])) {
            $packages[] = str_replace('{version}', $version, $config['package_pattern']);
        }
        foreach ($config['extra_packages'] ?? [] as $pkg) {
            $packages[] = str_replace('{version}', $version, $pkg);
        }

        $this->updateTask(15, 'Removing packages...');
        $result = Process::timeout(120)->run('apt-get remove -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1');
        $this->appendLines($result->output(), 20, 70);

        $versions = array_values(array_diff($this->app->installed_versions ?? [], [$version]));
        $this->app->update([
            'status' => ! empty($versions) ? 'installed' : 'not_installed',
            'installed_versions' => $versions,
            'active_version' => ! empty($versions) ? $versions[0] : null,
        ]);

        if ($result->successful()) {
            $this->completeTask("Uninstalled {$this->app->name} {$version}");
        } else {
            $this->failTask('Uninstall failed: ' . $result->output());
        }
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────
    private function appendLines(string $output, int $startProgress, int $endProgress): void
    {
        $lines = array_filter(explode("\n", $output), fn ($l) => trim($l) !== '');
        $total = count($lines);
        if ($total === 0) {
            return;
        }

        $step = max(1, (int) ceil($total / 8));
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
        Log::error('Uninstall app job failed', [
            'app' => $this->app->slug,
            'version' => $this->version,
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);

        $this->app->update(['status' => $this->app->installed_versions ? 'installed' : 'error']);

        if ($this->taskId) {
            $task = Task::find($this->taskId);
            if ($task) {
                app(TaskService::class)->fail($task, $exception->getMessage());
            }
        }
    }
}
