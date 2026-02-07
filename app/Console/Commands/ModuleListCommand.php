<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModuleServiceProvider;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'module:list';

    /**
     * The console command description.
     */
    protected $description = 'List all modules and their status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modules = ModuleServiceProvider::getModules();

        if (empty($modules)) {
            $this->warn('No modules found.');
            return self::SUCCESS;
        }

        $this->info('VSISPanel Modules:');
        $this->newLine();

        $tableData = [];

        foreach ($modules as $module) {
            $status = $module['enabled'] ? '<fg=green>✓ Enabled</>' : '<fg=red>✗ Disabled</>';
            $tableData[] = [
                $module['name'],
                $status,
                $module['path'],
            ];
        }

        $this->table(
            ['Module', 'Status', 'Path'],
            $tableData
        );

        $this->newLine();
        $this->line('Total modules: ' . count($modules));
        $enabledCount = count(array_filter($modules, fn($m) => $m['enabled']));
        $disabledCount = count($modules) - $enabledCount;

        $this->line("Enabled: <fg=green>{$enabledCount}</>");
        $this->line("Disabled: <fg=red>{$disabledCount}</>");

        return self::SUCCESS;
    }
}
