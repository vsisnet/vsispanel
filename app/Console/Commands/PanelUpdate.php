<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class PanelUpdate extends Command
{
    protected $signature = 'vsispanel:update {--skip-build : Skip npm build step}';

    protected $description = 'Update VSISPanel (pull latest code, install deps, migrate, seed, build)';

    public function handle(): int
    {
        $this->info('Updating VSISPanel...');
        $this->newLine();

        $panelDir = base_path();

        // Step 1: Git pull
        $this->info('Step 1: Pulling latest code...');
        $result = Process::timeout(120)->path($panelDir)->run('git pull origin main 2>&1');
        if (!$result->successful()) {
            $this->error('Git pull failed: ' . $result->output());
            return self::FAILURE;
        }
        $this->line('  ' . trim($result->output()));

        // Step 2: Composer
        $this->info('Step 2: Installing PHP dependencies...');
        $result = Process::timeout(300)->path($panelDir)->run('composer install --no-interaction --optimize-autoloader --no-dev 2>&1');
        if (!$result->successful()) {
            $this->error('Composer install failed');
            return self::FAILURE;
        }
        $this->info('  ✓ PHP dependencies updated');

        // Step 3: Node
        if (!$this->option('skip-build')) {
            $this->info('Step 3: Installing Node dependencies...');
            Process::timeout(120)->path($panelDir)->run('npm install 2>&1');
            $this->info('  ✓ Node dependencies updated');

            $this->info('Step 4: Building frontend...');
            $result = Process::timeout(120)->path($panelDir)->run('npm run build 2>&1');
            if (!$result->successful()) {
                $this->error('Build failed: ' . $result->errorOutput());
                return self::FAILURE;
            }
            $this->info('  ✓ Frontend built');
        } else {
            $this->warn('  Skipping npm build (--skip-build)');
        }

        // Step 5: Migrate
        $this->info('Step 5: Running migrations...');
        $this->call('migrate', ['--force' => true]);

        // Step 6: Run seeders (idempotent - uses updateOrCreate)
        $this->info('Step 6: Running seeders...');
        $this->call('db:seed', ['--force' => true]);
        $this->info('  ✓ Seeders updated');

        // Step 7: Fix permissions
        $this->info('Step 7: Fixing permissions...');
        Process::timeout(10)->path($panelDir)->run('chown -R www-data:www-data storage bootstrap/cache 2>&1');
        Process::timeout(10)->path($panelDir)->run('chmod -R 775 storage bootstrap/cache 2>&1');
        $this->info('  ✓ Permissions fixed');

        // Step 8: Optimize
        $this->info('Step 8: Optimizing...');
        $this->call('vsispanel:optimize');

        // Step 9: Restart services
        $this->info('Step 9: Restarting services...');
        Process::timeout(10)->run('php artisan horizon:terminate 2>/dev/null');
        Process::timeout(10)->run('systemctl restart vsispanel-horizon 2>/dev/null');
        $this->info('  ✓ Queue workers restarted');

        $this->newLine();
        $this->info('Update complete!');

        return self::SUCCESS;
    }
}
