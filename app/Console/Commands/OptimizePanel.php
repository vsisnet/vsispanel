<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OptimizePanel extends Command
{
    protected $signature = 'vsispanel:optimize {--clear : Clear all caches instead of building them}';

    protected $description = 'Optimize VSISPanel for production (cache config, routes, views, events)';

    public function handle(): int
    {
        if ($this->option('clear')) {
            return $this->clearCaches();
        }

        return $this->buildCaches();
    }

    private function buildCaches(): int
    {
        $this->info('Optimizing VSISPanel for production...');
        $this->newLine();

        $commands = [
            ['config:cache', 'Configuration'],
            ['route:cache', 'Routes'],
            ['view:cache', 'Views'],
            ['event:cache', 'Events'],
        ];

        $failed = 0;
        foreach ($commands as [$command, $label]) {
            try {
                $this->call($command);
                $this->line("  ✓ {$label} cached");
            } catch (\Exception $e) {
                $this->error("  ✗ {$label} failed: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();

        if ($failed === 0) {
            $this->info('All optimizations applied successfully.');
        } else {
            $this->warn("{$failed} optimization(s) failed. Check the errors above.");
        }

        $this->newLine();
        $this->table(
            ['Setting', 'Value'],
            [
                ['Environment', config('app.env')],
                ['Debug Mode', config('app.debug') ? 'ON (should be OFF in production)' : 'OFF'],
                ['Cache Driver', config('cache.default')],
                ['Session Driver', config('session.driver')],
                ['Queue Driver', config('queue.default')],
            ]
        );

        if (config('app.debug')) {
            $this->warn('Warning: Debug mode is ON. Set APP_DEBUG=false for production.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function clearCaches(): int
    {
        $this->info('Clearing all VSISPanel caches...');
        $this->newLine();

        $commands = [
            'config:clear',
            'route:clear',
            'view:clear',
            'event:clear',
            'cache:clear',
        ];

        foreach ($commands as $command) {
            $this->call($command);
        }

        $this->newLine();
        $this->info('All caches cleared.');

        return self::SUCCESS;
    }
}
