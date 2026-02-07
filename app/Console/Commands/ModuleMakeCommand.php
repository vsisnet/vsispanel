<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModuleMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'module:make {name : The name of the module}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new module with complete folder structure and ServiceProvider';

    /**
     * Module structure template.
     */
    protected array $structure = [
        'Models',
        'Http/Controllers',
        'Http/Requests',
        'Http/Resources',
        'Services',
        'Policies',
        'Events',
        'Listeners',
        'Providers',
        'Routes',
        'Database/Migrations',
        'Database/Factories',
        'Database/Seeders',
        'Config',
        'Tests/Unit',
        'Tests/Feature',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $moduleName = $this->argument('name');
        $moduleName = ucfirst($moduleName);

        $modulePath = app_path("Modules/{$moduleName}");

        if (File::isDirectory($modulePath)) {
            $this->error("Module '{$moduleName}' already exists!");
            return self::FAILURE;
        }

        $this->info("Creating module: {$moduleName}");

        // Create module directories
        foreach ($this->structure as $directory) {
            $path = "{$modulePath}/{$directory}";
            File::makeDirectory($path, 0755, true);
            File::put("{$path}/.gitkeep", '');
            $this->line("  ✓ Created: {$directory}");
        }

        // Create ServiceProvider
        $this->createServiceProvider($moduleName, $modulePath);

        // Create sample route files
        $this->createRouteFiles($modulePath);

        $this->newLine();
        $this->info("Module '{$moduleName}' created successfully!");
        $this->line("Location: {$modulePath}");

        return self::SUCCESS;
    }

    /**
     * Create module ServiceProvider.
     */
    protected function createServiceProvider(string $moduleName, string $modulePath): void
    {
        $providerContent = $this->getServiceProviderStub($moduleName);
        $providerPath = "{$modulePath}/Providers/{$moduleName}ServiceProvider.php";

        File::put($providerPath, $providerContent);
        $this->line("  ✓ Created: ServiceProvider");
    }

    /**
     * Create sample route files.
     */
    protected function createRouteFiles(string $modulePath): void
    {
        // API routes
        $apiContent = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Add your API routes here

PHP;

        File::put("{$modulePath}/Routes/api.php", $apiContent);
        File::delete("{$modulePath}/Routes/.gitkeep");
        $this->line("  ✓ Created: Routes/api.php");

        // Web routes
        $webContent = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Add your web routes here

PHP;

        File::put("{$modulePath}/Routes/web.php", $webContent);
        $this->line("  ✓ Created: Routes/web.php");
    }

    /**
     * Get ServiceProvider stub content.
     */
    protected function getServiceProviderStub(string $moduleName): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleName}\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class {$moduleName}ServiceProvider extends ServiceProvider
{
    /**
     * The module namespace.
     */
    protected string \$moduleNamespace = 'App\Modules\\{$moduleName}\Http\Controllers';

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register module services, bindings, or singletons here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        \$this->registerRoutes();
        \$this->registerMigrations();
        \$this->registerConfig();
        \$this->registerViews();
        \$this->registerTranslations();
    }

    /**
     * Register module routes.
     */
    protected function registerRoutes(): void
    {
        \$modulePath = __DIR__ . '/..';

        // API Routes
        if (file_exists(\$modulePath . '/Routes/api.php')) {
            Route::middleware('api')
                ->prefix('api')
                ->namespace(\$this->moduleNamespace)
                ->group(\$modulePath . '/Routes/api.php');
        }

        // Web Routes
        if (file_exists(\$modulePath . '/Routes/web.php')) {
            Route::middleware('web')
                ->namespace(\$this->moduleNamespace)
                ->group(\$modulePath . '/Routes/web.php');
        }
    }

    /**
     * Register module migrations.
     */
    protected function registerMigrations(): void
    {
        \$modulePath = __DIR__ . '/..';
        \$migrationsPath = \$modulePath . '/Database/Migrations';

        if (is_dir(\$migrationsPath)) {
            \$this->loadMigrationsFrom(\$migrationsPath);
        }
    }

    /**
     * Register module config.
     */
    protected function registerConfig(): void
    {
        \$modulePath = __DIR__ . '/..';
        \$configPath = \$modulePath . '/Config';

        if (is_dir(\$configPath)) {
            foreach (glob(\$configPath . '/*.php') as \$configFile) {
                \$configName = strtolower('{$moduleName}') . '.' . basename(\$configFile, '.php');
                \$this->mergeConfigFrom(\$configFile, \$configName);
            }
        }
    }

    /**
     * Register module views.
     */
    protected function registerViews(): void
    {
        \$modulePath = __DIR__ . '/..';
        \$viewsPath = \$modulePath . '/Resources/views';

        if (is_dir(\$viewsPath)) {
            \$this->loadViewsFrom(\$viewsPath, strtolower('{$moduleName}'));
        }
    }

    /**
     * Register module translations.
     */
    protected function registerTranslations(): void
    {
        \$modulePath = __DIR__ . '/..';
        \$translationsPath = \$modulePath . '/Resources/lang';

        if (is_dir(\$translationsPath)) {
            \$this->loadTranslationsFrom(\$translationsPath, strtolower('{$moduleName}'));
        }
    }
}

PHP;
    }
}
