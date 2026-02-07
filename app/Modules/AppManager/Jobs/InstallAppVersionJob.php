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

class InstallAppVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900; // 15 minutes

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
                $this->updateTask(2, "Starting installation of {$this->app->name}" . ($this->version ? " {$this->version}" : '') . '...');
            }
        }

        Log::info('Starting app install job', [
            'app' => $this->app->slug,
            'version' => $this->version,
            'task_id' => $this->taskId,
        ]);

        $this->app->update(['status' => 'installing']);

        try {
            $config = config("appmanager.apps.{$this->app->slug}", []);

            match ($this->app->slug) {
                'php' => $this->installPhp($config),
                'nodejs' => $this->installNodejs($config),
                'python' => $this->installPython($config),
                'composer' => $this->installComposer(),
                default => $config['type'] === 'multi_version'
                    ? $this->installMultiVersionGeneric($config)
                    : $this->installSingleVersionGeneric($config),
            };
        } catch (\Exception $e) {
            $this->app->update(['status' => $this->app->installed_versions ? 'installed' : 'error']);
            Log::error("Install failed for {$this->app->slug}: " . $e->getMessage());
            $this->failTask("Installation failed: {$e->getMessage()}");
        }
    }

    // ──────────────────────────────────────────────
    // PHP
    // ──────────────────────────────────────────────
    private function installPhp(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required for PHP installation');
        }

        // Step 1: Add PPA
        $this->updateTask(5, 'Checking ondrej/php PPA...');
        $this->addPpa($config['ppa'] ?? 'ppa:ondrej/php');

        // Step 2: apt-get update
        $this->updateTask(15, 'Updating package lists...');
        $this->runCommand('apt-get update -y 2>&1', 120, 'Failed to update package lists');

        // Step 3: Build package list
        $packages = [];
        if (! empty($config['package_pattern'])) {
            $packages[] = str_replace('{version}', $version, $config['package_pattern']);
        }
        foreach ($config['extra_packages'] ?? [] as $pkg) {
            $packages[] = str_replace('{version}', $version, $pkg);
        }

        $this->updateTask(20, 'Installing packages: ' . implode(', ', $packages));

        // Step 4: Install packages
        $installCmd = 'DEBIAN_FRONTEND=noninteractive apt-get install -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1';
        $result = Process::timeout(600)->run($installCmd);

        $output = $result->output();
        $this->appendLines($output, 25, 60);

        if (! $result->successful()) {
            $this->app->update(['status' => $this->app->installed_versions ? 'installed' : 'error']);
            $this->failTask("Package installation failed:\n{$output}");

            return;
        }

        $this->updateTask(65, "PHP {$version} packages installed successfully.");

        // Step 5: Enable and start service
        $serviceName = str_replace('{version}', $version, $config['service_pattern'] ?? '');
        if ($serviceName) {
            $this->updateTask(70, "Enabling service {$serviceName}...");
            Process::timeout(15)->run("systemctl enable " . escapeshellarg($serviceName) . " 2>&1");

            $this->updateTask(75, "Starting service {$serviceName}...");
            $startResult = Process::timeout(30)->run("systemctl start " . escapeshellarg($serviceName) . " 2>&1");

            if ($startResult->successful()) {
                $this->updateTask(80, "Service {$serviceName} started successfully.");
            } else {
                $this->updateTask(80, "Warning: Could not start {$serviceName}: " . $startResult->errorOutput());
            }
        }

        // Step 6: Install Composer if not present
        $this->updateTask(85, 'Checking for Composer...');
        $composerCheck = Process::timeout(5)->run('which composer 2>/dev/null');
        if (! $composerCheck->successful()) {
            $this->updateTask(87, 'Installing Composer...');
            $this->installComposerBinary();
            $this->updateTask(92, 'Composer installed.');
        } else {
            $this->updateTask(92, 'Composer already installed.');
        }

        // Step 7: Update database
        $versions = $this->app->installed_versions ?? [];
        if (! in_array($version, $versions)) {
            $versions[] = $version;
            sort($versions);
        }

        $this->app->update([
            'status' => 'installed',
            'installed_versions' => $versions,
            'active_version' => $this->app->active_version ?? $version,
            'installed_at' => $this->app->installed_at ?? now(),
        ]);

        $this->updateTask(95, 'Database updated.');
        $this->completeTask("PHP {$version} installed successfully!");
    }

    // ──────────────────────────────────────────────
    // Node.js
    // ──────────────────────────────────────────────
    private function installNodejs(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required for Node.js installation');
        }

        $versionMap = $config['version_map'] ?? [];
        $fullVersion = $versionMap[$version] ?? null;
        if (! $fullVersion) {
            // Fetch latest LTS for this major version
            $this->updateTask(5, "Resolving latest Node.js {$version}.x version...");
            $fullVersion = $this->resolveNodeVersion($version);
        }

        if (! $fullVersion) {
            $this->failTask("Cannot resolve Node.js version {$version}");

            return;
        }

        $installDir = $config['install_dir'] ?? '/usr/local/lib/nodejs';
        $arch = php_uname('m') === 'aarch64' ? 'linux-arm64' : 'linux-x64';
        $tarball = "node-v{$fullVersion}-{$arch}.tar.xz";
        $url = "https://nodejs.org/dist/v{$fullVersion}/{$tarball}";
        $versionDir = "{$installDir}/node-{$version}";

        // Step 1: Download
        $this->updateTask(10, "Downloading Node.js v{$fullVersion}...");
        $tmpFile = "/tmp/{$tarball}";
        $dlResult = Process::timeout(120)->run("curl -fsSL " . escapeshellarg($url) . " -o " . escapeshellarg($tmpFile) . " 2>&1");

        if (! $dlResult->successful()) {
            $this->failTask("Download failed: " . $dlResult->output());

            return;
        }

        $this->updateTask(35, 'Download complete. Extracting...');

        // Step 2: Extract
        Process::timeout(10)->run("mkdir -p " . escapeshellarg($installDir));
        $extractResult = Process::timeout(60)->run("tar -xf " . escapeshellarg($tmpFile) . " -C " . escapeshellarg($installDir) . " 2>&1");

        if (! $extractResult->successful()) {
            @unlink($tmpFile);
            $this->failTask("Extraction failed: " . $extractResult->output());

            return;
        }

        // Rename extracted directory
        $extractedDir = "{$installDir}/node-v{$fullVersion}-{$arch}";
        Process::timeout(5)->run("rm -rf " . escapeshellarg($versionDir));
        Process::timeout(5)->run("mv " . escapeshellarg($extractedDir) . " " . escapeshellarg($versionDir));
        @unlink($tmpFile);

        $this->updateTask(55, 'Extraction complete. Setting up symlinks...');

        // Step 3: Create versioned symlinks
        $binDir = "{$versionDir}/bin";
        Process::timeout(5)->run("ln -sf {$binDir}/node /usr/local/bin/node{$version}");
        Process::timeout(5)->run("ln -sf {$binDir}/npm /usr/local/bin/npm{$version}");
        Process::timeout(5)->run("ln -sf {$binDir}/npx /usr/local/bin/npx{$version}");

        $this->updateTask(70, 'Symlinks created. Verifying...');

        // Step 4: Verify
        $verifyResult = Process::timeout(5)->run("/usr/local/bin/node{$version} --version 2>&1");
        $nodeVersion = trim($verifyResult->output());
        $this->updateTask(80, "Node.js verified: {$nodeVersion}");

        // Step 5: Install global packages (yarn, pm2)
        $this->updateTask(82, 'Installing pm2 and yarn globally...');
        Process::timeout(60)->run("{$binDir}/npm install -g pm2 yarn 2>&1");

        // Step 6: Update installed versions
        $versions = $this->app->installed_versions ?? [];
        $isFirst = empty($versions);
        if (! in_array($version, $versions)) {
            $versions[] = $version;
            sort($versions, SORT_NUMERIC);
        }

        // Only set as system default if no other Node.js exists on the system
        $systemNode = trim(Process::timeout(5)->run('which /usr/bin/node 2>/dev/null')->output());
        if ($isFirst && empty($systemNode)) {
            $this->setNodeDefault($version, $binDir);
            $this->updateTask(90, "Node.js {$version} set as system default.");
        } else {
            $this->updateTask(90, "Node.js {$version} installed. Use 'Set Default' to switch versions.");
        }

        $this->app->update([
            'status' => 'installed',
            'installed_versions' => $versions,
            'active_version' => $this->app->active_version ?? $version,
            'installed_at' => $this->app->installed_at ?? now(),
        ]);

        $this->updateTask(95, 'Database updated.');
        $this->completeTask("Node.js {$version} ({$nodeVersion}) installed successfully!");
    }

    private function resolveNodeVersion(string $major): ?string
    {
        $result = Process::timeout(15)->run("curl -fsSL https://nodejs.org/dist/index.json 2>/dev/null | php -r '\$d=json_decode(file_get_contents(\"php://stdin\"),true);foreach(\$d as \$v){if(str_starts_with(\$v[\"version\"],\"v{$major}.\")){echo ltrim(\$v[\"version\"],\"v\");exit;}}'");

        $v = trim($result->output());

        return $v ?: null;
    }

    private function setNodeDefault(string $version, string $binDir): void
    {
        Process::timeout(5)->run("ln -sf {$binDir}/node /usr/local/bin/node");
        Process::timeout(5)->run("ln -sf {$binDir}/npm /usr/local/bin/npm");
        Process::timeout(5)->run("ln -sf {$binDir}/npx /usr/local/bin/npx");

        // Also link pm2 and yarn if they exist
        if (file_exists("{$binDir}/pm2")) {
            Process::timeout(5)->run("ln -sf {$binDir}/pm2 /usr/local/bin/pm2");
        }
        if (file_exists("{$binDir}/yarn")) {
            Process::timeout(5)->run("ln -sf {$binDir}/yarn /usr/local/bin/yarn");
        }
    }

    // ──────────────────────────────────────────────
    // Python
    // ──────────────────────────────────────────────
    private function installPython(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required for Python installation');
        }

        // Step 1: Add PPA
        $this->updateTask(5, 'Checking deadsnakes PPA...');
        $this->addPpa($config['ppa'] ?? 'ppa:deadsnakes/ppa');

        // Step 2: apt-get update
        $this->updateTask(15, 'Updating package lists...');
        $this->runCommand('apt-get update -y 2>&1', 120, 'Failed to update package lists');

        // Step 3: Build package list
        $packages = [];
        if (! empty($config['package_pattern'])) {
            $packages[] = str_replace('{version}', $version, $config['package_pattern']);
        }
        foreach ($config['extra_packages'] ?? [] as $pkg) {
            $packages[] = str_replace('{version}', $version, $pkg);
        }

        $this->updateTask(20, 'Installing packages: ' . implode(', ', $packages));

        // Step 4: Install
        $installCmd = 'DEBIAN_FRONTEND=noninteractive apt-get install -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1';
        $result = Process::timeout(600)->run($installCmd);

        $output = $result->output();
        $this->appendLines($output, 25, 60);

        if (! $result->successful()) {
            $this->app->update(['status' => $this->app->installed_versions ? 'installed' : 'error']);
            $this->failTask("Package installation failed:\n{$output}");

            return;
        }

        $this->updateTask(65, "Python {$version} packages installed.");

        // Step 5: Install pip for this version
        $this->updateTask(70, "Installing pip for Python {$version}...");
        $pipResult = Process::timeout(60)->run("python{$version} -m ensurepip --upgrade 2>&1 || curl -fsSL https://bootstrap.pypa.io/get-pip.py | python{$version} 2>&1");
        $this->updateTask(80, 'pip: ' . (str_contains($pipResult->output(), 'Successfully') ? 'installed' : 'may need manual setup'));

        // Step 6: Update database
        $versions = $this->app->installed_versions ?? [];
        if (! in_array($version, $versions)) {
            $versions[] = $version;
            sort($versions);
        }

        $this->app->update([
            'status' => 'installed',
            'installed_versions' => $versions,
            'active_version' => $this->app->active_version ?? $version,
            'installed_at' => $this->app->installed_at ?? now(),
        ]);

        $this->updateTask(90, 'Database updated.');

        // Step 7: Set default if needed
        if (! $this->app->active_version || $this->app->active_version === $version) {
            Process::timeout(5)->run("update-alternatives --install /usr/bin/python3 python3 /usr/bin/python{$version} 1 2>/dev/null");
        }

        $this->completeTask("Python {$version} installed successfully!");
    }

    // ──────────────────────────────────────────────
    // Composer
    // ──────────────────────────────────────────────
    private function installComposer(): void
    {
        $this->updateTask(10, 'Downloading Composer installer...');
        $this->installComposerBinary();

        $this->app->update([
            'status' => 'installed',
            'installed_at' => now(),
        ]);

        $verResult = Process::timeout(5)->run('composer --version 2>&1');
        $this->completeTask('Composer installed: ' . trim($verResult->output()));
    }

    private function installComposerBinary(): void
    {
        $commands = [
            'cd /tmp && php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');"',
            'cd /tmp && php composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>&1',
            'cd /tmp && php -r "unlink(\'composer-setup.php\');"',
            'chmod +x /usr/local/bin/composer',
        ];

        foreach ($commands as $cmd) {
            $result = Process::timeout(60)->run($cmd);
            if (! $result->successful() && str_contains($cmd, 'composer-setup.php --install-dir')) {
                $this->updateTask(50, 'Warning: Composer install issue: ' . $result->output());
            }
        }
    }

    // ──────────────────────────────────────────────
    // Generic single-version install
    // ──────────────────────────────────────────────
    private function installSingleVersionGeneric(array $config): void
    {
        $packages = $config['packages'] ?? [];
        if (empty($packages)) {
            $this->app->update(['status' => 'error']);
            $this->failTask('No packages defined for this app');

            return;
        }

        $this->updateTask(10, 'Installing packages: ' . implode(', ', $packages));

        $result = Process::timeout(300)->run('DEBIAN_FRONTEND=noninteractive apt-get install -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1');
        $output = $result->output();
        $this->appendLines($output, 15, 70);

        if ($result->successful()) {
            $this->app->update([
                'status' => 'installed',
                'installed_at' => now(),
            ]);
            $this->completeTask("Installed {$this->app->name}");
        } else {
            $this->app->update(['status' => 'error']);
            $this->failTask("Installation failed:\n{$output}");
        }
    }

    // ──────────────────────────────────────────────
    // Generic multi-version install
    // ──────────────────────────────────────────────
    private function installMultiVersionGeneric(array $config): void
    {
        $version = $this->version;
        if (! $version) {
            throw new \RuntimeException('Version is required');
        }

        if (! empty($config['ppa'])) {
            $this->updateTask(5, 'Adding PPA...');
            $this->addPpa($config['ppa']);
            $this->updateTask(15, 'Updating package lists...');
            $this->runCommand('apt-get update -y 2>&1', 120, 'Failed to update');
        }

        $packages = [];
        if (! empty($config['package_pattern'])) {
            $packages[] = str_replace('{version}', $version, $config['package_pattern']);
        }
        foreach ($config['extra_packages'] ?? [] as $pkg) {
            $packages[] = str_replace('{version}', $version, $pkg);
        }

        $this->updateTask(20, 'Installing: ' . implode(', ', $packages));
        $result = Process::timeout(300)->run('DEBIAN_FRONTEND=noninteractive apt-get install -y ' . implode(' ', array_map('escapeshellarg', $packages)) . ' 2>&1');
        $this->appendLines($result->output(), 25, 70);

        if ($result->successful()) {
            $versions = $this->app->installed_versions ?? [];
            if (! in_array($version, $versions)) {
                $versions[] = $version;
                sort($versions);
            }
            $this->app->update([
                'status' => 'installed',
                'installed_versions' => $versions,
                'active_version' => $this->app->active_version ?? $version,
                'installed_at' => $this->app->installed_at ?? now(),
            ]);
            $this->completeTask("Installed {$this->app->name} {$version}");
        } else {
            $this->app->update(['status' => $this->app->installed_versions ? 'installed' : 'error']);
            $this->failTask("Installation failed:\n" . $result->output());
        }
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────
    private function addPpa(string $ppa): void
    {
        // Check if PPA already added
        $ppaName = str_replace('ppa:', '', $ppa);
        $check = Process::timeout(5)->run("grep -r " . escapeshellarg($ppaName) . " /etc/apt/sources.list.d/ 2>/dev/null | head -1");

        if (! empty(trim($check->output()))) {
            $this->updateTask(10, "PPA {$ppa} already configured.");

            return;
        }

        $this->updateTask(8, "Adding PPA: {$ppa}...");
        $result = Process::timeout(60)->run('add-apt-repository -y ' . escapeshellarg($ppa) . ' 2>&1');

        if (! $result->successful()) {
            $this->updateTask(10, 'Warning: PPA add returned non-zero, continuing... ' . $result->output());
        } else {
            $this->updateTask(10, 'PPA added successfully.');
        }
    }

    private function runCommand(string $cmd, int $timeout, string $failMessage): string
    {
        $result = Process::timeout($timeout)->run($cmd);

        if (! $result->successful()) {
            throw new \RuntimeException("{$failMessage}: " . $result->output());
        }

        return $result->output();
    }

    /**
     * Stream apt output lines as progress updates.
     */
    private function appendLines(string $output, int $startProgress, int $endProgress): void
    {
        $lines = array_filter(explode("\n", $output), fn ($l) => trim($l) !== '');
        $total = count($lines);
        if ($total === 0) {
            return;
        }

        $step = max(1, (int) ceil($total / 10)); // Update every ~10% of lines
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
        Log::error('Install app job failed', [
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
