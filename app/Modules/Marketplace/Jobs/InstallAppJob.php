<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Jobs;

use App\Modules\Marketplace\Models\AppInstallation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class InstallAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 600; // 10 minutes

    public function __construct(
        public AppInstallation $installation,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $installation = $this->installation;
        $installation->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $domain = $installation->domain;
            $template = $installation->template;
            $docRoot = $domain->document_root ?: "/var/www/vhosts/{$domain->name}/public_html";

            $this->updateProgress(5, 'Preparing installation directory...');

            // Ensure directory exists
            if (! is_dir($docRoot)) {
                mkdir($docRoot, 0755, true);
            }

            $this->updateProgress(10, 'Checking requirements...');

            // Run installation based on app slug
            match ($template->slug) {
                'wordpress' => $this->installWordPress($docRoot),
                'laravel' => $this->installLaravel($docRoot),
                'joomla' => $this->installJoomla($docRoot),
                'drupal' => $this->installDrupal($docRoot),
                'prestashop' => $this->installPrestaShop($docRoot),
                'express' => $this->installExpress($docRoot),
                default => $this->installCustom($docRoot, $template),
            };

            $this->updateProgress(90, 'Setting file permissions...');

            // Set permissions
            Process::timeout(30)->run("chown -R www-data:www-data " . escapeshellarg($docRoot));
            Process::timeout(30)->run("find " . escapeshellarg($docRoot) . " -type d -exec chmod 755 {} \\;");
            Process::timeout(30)->run("find " . escapeshellarg($docRoot) . " -type f -exec chmod 644 {} \\;");

            $this->updateProgress(100, 'Installation completed!');

            $installation->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("App installation failed: " . $e->getMessage(), [
                'installation_id' => $installation->id,
            ]);

            $installation->appendLog("ERROR: " . $e->getMessage());
            $installation->update([
                'status' => 'failed',
                'current_step' => 'Installation failed: ' . $e->getMessage(),
            ]);
        }
    }

    private function updateProgress(int $progress, string $step): void
    {
        $this->installation->update([
            'progress' => $progress,
            'current_step' => $step,
        ]);
        $this->installation->appendLog($step);
    }

    private function installWordPress(string $docRoot): void
    {
        $this->updateProgress(20, 'Downloading WordPress...');

        $result = Process::timeout(120)->run(
            "cd " . escapeshellarg($docRoot) . " && wp core download --allow-root 2>&1"
        );

        if (! $result->successful()) {
            // Fallback to wget
            $result = Process::timeout(120)->run(
                "cd /tmp && wget -q https://wordpress.org/latest.tar.gz && " .
                "tar -xzf latest.tar.gz && cp -a wordpress/. " . escapeshellarg($docRoot) . "/ && " .
                "rm -rf /tmp/wordpress /tmp/latest.tar.gz"
            );
            if (! $result->successful()) {
                throw new \RuntimeException('Failed to download WordPress: ' . $result->errorOutput());
            }
        }

        $this->updateProgress(50, 'Configuring WordPress...');

        $options = $this->installation->options ?? [];
        $dbName = 'wp_' . substr(md5($this->installation->domain_id), 0, 8);
        $dbUser = $dbName;
        $dbPass = bin2hex(random_bytes(12));

        // Create database
        $this->updateProgress(60, 'Creating database...');
        Process::timeout(30)->run(
            "mysql -u root -e \"CREATE DATABASE IF NOT EXISTS \`{$dbName}\`; " .
            "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}'; " .
            "GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO '{$dbUser}'@'localhost'; FLUSH PRIVILEGES;\""
        );

        // Create wp-config.php
        $this->updateProgress(70, 'Creating configuration...');
        $wpConfig = file_get_contents($docRoot . '/wp-config-sample.php');
        if ($wpConfig) {
            $wpConfig = str_replace('database_name_here', $dbName, $wpConfig);
            $wpConfig = str_replace('username_here', $dbUser, $wpConfig);
            $wpConfig = str_replace('password_here', $dbPass, $wpConfig);
            file_put_contents($docRoot . '/wp-config.php', $wpConfig);
        }

        $this->updateProgress(80, 'Running WordPress installation...');

        $siteTitle = $options['site_title'] ?? 'My WordPress Site';
        $adminUser = $options['admin_username'] ?? 'admin';
        $adminPass = $options['admin_password'] ?? bin2hex(random_bytes(8));
        $adminEmail = $options['admin_email'] ?? 'admin@example.com';
        $domainName = $this->installation->domain->name;

        Process::timeout(60)->run(
            "cd " . escapeshellarg($docRoot) . " && wp core install " .
            "--url=" . escapeshellarg("https://{$domainName}") . " " .
            "--title=" . escapeshellarg($siteTitle) . " " .
            "--admin_user=" . escapeshellarg($adminUser) . " " .
            "--admin_password=" . escapeshellarg($adminPass) . " " .
            "--admin_email=" . escapeshellarg($adminEmail) . " " .
            "--allow-root 2>&1"
        );
    }

    private function installLaravel(string $docRoot): void
    {
        $this->updateProgress(20, 'Downloading Laravel...');
        $parentDir = dirname($docRoot);

        $result = Process::timeout(300)->run(
            "cd " . escapeshellarg($parentDir) . " && " .
            "composer create-project laravel/laravel temp_laravel --prefer-dist --no-interaction 2>&1"
        );

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to install Laravel: ' . $result->errorOutput());
        }

        $this->updateProgress(60, 'Moving files...');
        Process::timeout(30)->run("cp -a " . escapeshellarg($parentDir . "/temp_laravel/.") . " " . escapeshellarg($docRoot . "/..") . "/");
        Process::timeout(10)->run("rm -rf " . escapeshellarg($parentDir . "/temp_laravel"));

        $this->updateProgress(70, 'Configuring Laravel...');
        $laravelRoot = dirname($docRoot);
        Process::timeout(30)->run("cd " . escapeshellarg($laravelRoot) . " && php artisan key:generate 2>&1");

        $this->updateProgress(80, 'Setting up database...');
        $dbName = 'laravel_' . substr(md5($this->installation->domain_id), 0, 8);
        Process::timeout(30)->run(
            "mysql -u root -e \"CREATE DATABASE IF NOT EXISTS \`{$dbName}\`;\""
        );
    }

    private function installJoomla(string $docRoot): void
    {
        $this->updateProgress(20, 'Downloading Joomla...');
        $result = Process::timeout(120)->run(
            "cd /tmp && wget -q https://downloads.joomla.org/cms/joomla5/latest -O joomla.zip && " .
            "unzip -qo joomla.zip -d " . escapeshellarg($docRoot) . " && rm -f /tmp/joomla.zip"
        );
        if (! $result->successful()) {
            throw new \RuntimeException('Failed to download Joomla.');
        }
        $this->updateProgress(70, 'Creating database...');
        $dbName = 'joomla_' . substr(md5($this->installation->domain_id), 0, 8);
        Process::timeout(30)->run(
            "mysql -u root -e \"CREATE DATABASE IF NOT EXISTS \`{$dbName}\`;\""
        );
    }

    private function installDrupal(string $docRoot): void
    {
        $this->updateProgress(20, 'Downloading Drupal...');
        $parentDir = dirname($docRoot);
        $result = Process::timeout(300)->run(
            "cd " . escapeshellarg($parentDir) . " && composer create-project drupal/recommended-project temp_drupal --no-interaction 2>&1"
        );
        if ($result->successful()) {
            Process::timeout(30)->run("cp -a " . escapeshellarg($parentDir . "/temp_drupal/web/.") . " " . escapeshellarg($docRoot) . "/");
            Process::timeout(10)->run("rm -rf " . escapeshellarg($parentDir . "/temp_drupal"));
        }
        $this->updateProgress(70, 'Creating database...');
        $dbName = 'drupal_' . substr(md5($this->installation->domain_id), 0, 8);
        Process::timeout(30)->run(
            "mysql -u root -e \"CREATE DATABASE IF NOT EXISTS \`{$dbName}\`;\""
        );
    }

    private function installPrestaShop(string $docRoot): void
    {
        $this->updateProgress(20, 'Downloading PrestaShop...');
        $result = Process::timeout(120)->run(
            "cd /tmp && wget -q https://github.com/PrestaShop/PrestaShop/releases/latest/download/prestashop.zip -O prestashop.zip && " .
            "unzip -qo prestashop.zip -d " . escapeshellarg($docRoot) . " && rm -f /tmp/prestashop.zip"
        );
        $this->updateProgress(70, 'Creating database...');
        $dbName = 'prestashop_' . substr(md5($this->installation->domain_id), 0, 8);
        Process::timeout(30)->run(
            "mysql -u root -e \"CREATE DATABASE IF NOT EXISTS \`{$dbName}\`;\""
        );
    }

    private function installExpress(string $docRoot): void
    {
        $this->updateProgress(20, 'Setting up Node.js Express...');
        Process::timeout(30)->run("mkdir -p " . escapeshellarg($docRoot));

        $packageJson = json_encode([
            'name' => 'express-app',
            'version' => '1.0.0',
            'main' => 'index.js',
            'scripts' => ['start' => 'node index.js'],
            'dependencies' => ['express' => '^4'],
        ], JSON_PRETTY_PRINT);

        file_put_contents($docRoot . '/package.json', $packageJson);
        file_put_contents($docRoot . '/index.js', "const express = require('express');\nconst app = express();\nconst port = process.env.PORT || 3000;\napp.get('/', (req, res) => res.send('Hello World!'));\napp.listen(port, () => console.log(`Server running on port \${port}`));\n");

        $this->updateProgress(50, 'Installing npm dependencies...');
        Process::timeout(120)->run("cd " . escapeshellarg($docRoot) . " && npm install 2>&1");
    }

    private function installCustom(string $docRoot, $template): void
    {
        if ($template->install_script) {
            $this->updateProgress(30, 'Running custom install script...');
            $scriptPath = "/tmp/install_" . $this->installation->id . ".sh";
            file_put_contents($scriptPath, $template->install_script);
            chmod($scriptPath, 0755);
            Process::timeout(300)->env(['DOC_ROOT' => $docRoot])->run("bash " . escapeshellarg($scriptPath) . " 2>&1");
            @unlink($scriptPath);
        }
    }
}
