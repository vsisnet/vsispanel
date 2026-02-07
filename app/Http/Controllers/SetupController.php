<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class SetupController extends Controller
{
    /**
     * Check system requirements.
     */
    public function checkRequirements(): JsonResponse
    {
        $requirements = [
            'php_version' => [
                'label' => 'PHP >= 8.3',
                'passed' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'current' => PHP_VERSION,
            ],
            'php_extensions' => [
                'label' => 'PHP Extensions',
                'passed' => $this->checkPhpExtensions(),
                'current' => implode(', ', $this->getMissingExtensions() ?: ['All installed']),
            ],
            'mysql' => [
                'label' => 'MySQL',
                'passed' => $this->checkService('mysql'),
                'current' => $this->getServiceVersion('mysql'),
            ],
            'redis' => [
                'label' => 'Redis',
                'passed' => $this->checkService('redis-cli'),
                'current' => $this->getServiceVersion('redis-cli'),
            ],
            'nginx' => [
                'label' => 'Nginx',
                'passed' => $this->checkService('nginx'),
                'current' => $this->getServiceVersion('nginx'),
            ],
            'composer' => [
                'label' => 'Composer',
                'passed' => $this->checkService('composer'),
                'current' => $this->getServiceVersion('composer'),
            ],
            'node' => [
                'label' => 'Node.js >= 18',
                'passed' => $this->checkNodeVersion(),
                'current' => $this->getServiceVersion('node'),
            ],
            'writable_storage' => [
                'label' => 'Storage writable',
                'passed' => is_writable(storage_path()),
                'current' => is_writable(storage_path()) ? 'Writable' : 'Not writable',
            ],
            'writable_env' => [
                'label' => '.env writable',
                'passed' => is_writable(base_path('.env')),
                'current' => is_writable(base_path('.env')) ? 'Writable' : 'Not writable',
            ],
        ];

        $allPassed = collect($requirements)->every(fn ($r) => $r['passed']);

        return response()->json([
            'success' => true,
            'data' => [
                'requirements' => $requirements,
                'all_passed' => $allPassed,
            ],
        ]);
    }

    /**
     * Test database connection.
     */
    public function testDatabase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => ['message' => $validator->errors()->first()]], 422);
        }

        try {
            $data = $validator->validated();
            $pdo = new \PDO(
                "mysql:host={$data['host']};port={$data['port']}",
                $data['username'],
                $data['password'] ?? '',
                [\PDO::ATTR_TIMEOUT => 5]
            );

            // Try to create database if it doesn't exist
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $data['database']);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Database connection successful'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Connection failed: ' . $e->getMessage()],
            ], 400);
        }
    }

    /**
     * Save configuration and run migrations.
     */
    public function configure(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'db_host' => 'required|string',
            'db_port' => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => ['message' => $validator->errors()->first()]], 422);
        }

        try {
            $data = $validator->validated();

            // Update .env file
            $this->updateEnv([
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => $data['db_port'],
                'DB_DATABASE' => $data['db_database'],
                'DB_USERNAME' => $data['db_username'],
                'DB_PASSWORD' => $data['db_password'] ?? '',
            ]);

            // Clear config cache so new DB settings take effect
            Artisan::call('config:clear');

            // Re-configure database connection at runtime
            config([
                'database.connections.mysql.host' => $data['db_host'],
                'database.connections.mysql.port' => $data['db_port'],
                'database.connections.mysql.database' => $data['db_database'],
                'database.connections.mysql.username' => $data['db_username'],
                'database.connections.mysql.password' => $data['db_password'] ?? '',
            ]);
            DB::purge('mysql');
            DB::reconnect('mysql');

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Run seeders
            Artisan::call('db:seed', ['--force' => true]);

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Database configured and migrated successfully'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Configuration failed: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Create admin account.
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'timezone' => 'nullable|string|max:100',
            'locale' => 'nullable|string|in:vi,en',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => ['message' => $validator->errors()->first()]], 422);
        }

        try {
            $data = $validator->validated();

            // Update or create admin user
            $userModel = config('auth.providers.users.model');
            $admin = $userModel::where('email', $data['email'])->first();

            if ($admin) {
                $admin->update([
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                ]);
            } else {
                $admin = $userModel::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                ]);
                $admin->assignRole('admin');
            }

            // Update timezone if provided
            if (!empty($data['timezone'])) {
                $this->updateEnv(['APP_TIMEZONE' => $data['timezone']]);
            }

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Admin account created successfully'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to create admin: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Finalize setup.
     */
    public function finalize(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            // Update server config in .env
            $envUpdates = ['APP_ENV' => 'production', 'APP_DEBUG' => 'false'];

            if (!empty($data['hostname'])) {
                $envUpdates['APP_URL'] = "http://{$data['hostname']}";
            }

            $this->updateEnv($envUpdates);

            // Optimize
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');

            // Storage link
            if (!file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }

            // Mark installation complete
            file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

            return response()->json([
                'success' => true,
                'data' => ['message' => 'Setup complete! Redirecting to login...'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Finalization failed: ' . $e->getMessage()],
            ], 500);
        }
    }

    // --- Helper methods ---

    private function checkPhpExtensions(): bool
    {
        return empty($this->getMissingExtensions());
    }

    private function getMissingExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'curl', 'zip', 'gd', 'bcmath', 'redis', 'intl'];
        return array_filter($required, fn ($ext) => !extension_loaded($ext));
    }

    private function checkService(string $name): bool
    {
        $result = Process::timeout(5)->run("which {$name} 2>/dev/null");
        return $result->successful() && !empty(trim($result->output()));
    }

    private function getServiceVersion(string $name): string
    {
        try {
            $cmd = match ($name) {
                'mysql' => 'mysql --version 2>/dev/null | awk \'{print $3}\'',
                'redis-cli' => 'redis-cli --version 2>/dev/null | awk \'{print $2}\'',
                'nginx' => 'nginx -v 2>&1 | awk -F/ \'{print $2}\'',
                'composer' => 'composer --version 2>/dev/null | awk \'{print $3}\'',
                'node' => 'node -v 2>/dev/null',
                default => "echo 'unknown'",
            };
            $result = Process::timeout(5)->run($cmd);
            return trim($result->output()) ?: 'Not found';
        } catch (\Exception $e) {
            return 'Not found';
        }
    }

    private function checkNodeVersion(): bool
    {
        try {
            $result = Process::timeout(5)->run('node -v 2>/dev/null');
            $version = trim($result->output());
            if (preg_match('/v(\d+)/', $version, $m)) {
                return (int) $m[1] >= 18;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function updateEnv(array $values): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            // Escape value if it contains spaces
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
