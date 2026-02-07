<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class PanelInstall extends Command
{
    protected $signature = 'vsispanel:install {--force : Overwrite existing configuration}';

    protected $description = 'Install and configure VSISPanel (CLI version of setup wizard)';

    public function handle(): int
    {
        if (file_exists(storage_path('installed')) && !$this->option('force')) {
            $this->warn('VSISPanel is already installed. Use --force to re-run.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     VSISPanel CLI Installer v1.0.0       ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        // Step 1: Database
        $this->info('Step 1: Database Configuration');
        $dbHost = $this->ask('Database host', '127.0.0.1');
        $dbPort = $this->ask('Database port', '3306');
        $dbName = $this->ask('Database name', 'vsispanel');
        $dbUser = $this->ask('Database username', 'root');
        $dbPass = $this->secret('Database password (leave empty for none)') ?? '';

        // Test connection
        try {
            new \PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [\PDO::ATTR_TIMEOUT => 5]);
            $this->info('  ✓ Database connection successful');
        } catch (\Exception $e) {
            $this->error("  ✗ Cannot connect: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Step 2: Admin account
        $this->info('');
        $this->info('Step 2: Admin Account');
        $adminName = $this->ask('Admin name', 'Administrator');
        $adminEmail = $this->ask('Admin email', 'admin@vsispanel.local');
        $adminPass = $this->secret('Admin password');

        if (empty($adminPass) || strlen($adminPass) < 8) {
            $this->error('Password must be at least 8 characters');
            return self::FAILURE;
        }

        // Step 3: Apply configuration
        $this->info('');
        $this->info('Step 3: Applying configuration...');

        $this->updateEnv([
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPass,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
        ]);

        // Generate key if needed
        if (empty(config('app.key')) || config('app.key') === 'base64:') {
            $this->call('key:generate', ['--force' => true]);
        }

        // Clear config so new DB settings load
        $this->call('config:clear');

        // Create database
        try {
            $pdo = new \PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass);
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info('  ✓ Database created/verified');
        } catch (\Exception $e) {
            $this->warn("  Could not create database: {$e->getMessage()}");
        }

        // Migrations
        $this->call('migrate', ['--force' => true]);
        $this->info('  ✓ Migrations complete');

        // Seeders
        $this->call('db:seed', ['--force' => true]);
        $this->info('  ✓ Seeders complete');

        // Create/update admin
        $userModel = config('auth.providers.users.model');
        $admin = $userModel::where('email', $adminEmail)->first();
        if ($admin) {
            $admin->update([
                'name' => $adminName,
                'password' => bcrypt($adminPass),
            ]);
        } else {
            $admin = $userModel::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => bcrypt($adminPass),
                'email_verified_at' => now(),
            ]);
            $admin->assignRole('admin');
        }
        $this->info('  ✓ Admin account configured');

        // Optimize
        $this->call('vsispanel:optimize');

        // Storage link
        if (!file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        // Mark installed
        file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

        $serverIp = trim(Process::timeout(5)->run("hostname -I | awk '{print $1}'")->output()) ?: 'localhost';

        $this->info('');
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     Installation Complete!                ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');
        $this->info("  Panel URL: http://{$serverIp}:8000");
        $this->info("  Admin:     {$adminEmail}");
        $this->info('');

        return self::SUCCESS;
    }

    private function updateEnv(array $values): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $escaped = str_contains($value, ' ') ? "\"{$value}\"" : $value;
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
