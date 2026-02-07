<?php

declare(strict_types=1);

namespace App\Modules\AppManager\Http\Controllers;

use App\Modules\AppManager\Jobs\InstallAppVersionJob;
use App\Modules\AppManager\Jobs\ManagePhpExtensionJob;
use App\Modules\AppManager\Jobs\UninstallAppVersionJob;
use App\Modules\AppManager\Models\ManagedApp;
use App\Modules\AppManager\Services\AppManagerService;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AppManagerController extends Controller
{
    public function __construct(
        private readonly AppManagerService $service,
        private readonly TaskService $taskService,
    ) {}

    /**
     * GET /api/v1/app-manager - All apps grouped by category.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getAllApps(),
        ]);
    }

    /**
     * POST /api/v1/app-manager/scan - Force rescan system.
     */
    public function scan(): JsonResponse
    {
        $this->service->syncFromConfig();

        return response()->json([
            'success' => true,
            'data' => $this->service->getAllApps(),
            'message' => 'System scan completed',
        ]);
    }

    /**
     * GET /api/v1/app-manager/{slug} - Single app detail.
     */
    public function show(string $slug): JsonResponse
    {
        $app = $this->service->getApp($slug);

        if (! $app) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'App not found'],
            ], 404);
        }

        $config = config("appmanager.apps.{$slug}", []);

        $data = [
            'app' => $app,
            'available_versions' => $config['available_versions'] ?? [],
            'config_files' => array_keys($config['config_files'] ?? $config['config_pattern'] ?? []),
        ];

        // Include per-version details for multi-version apps
        if ($app->type === 'multi_version') {
            $data['versions'] = $this->service->getVersionDetails($app);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/app-manager/{slug}/start
     */
    public function start(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $result = $this->service->startApp($app, $request->input('version'));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/v1/app-manager/{slug}/stop
     */
    public function stop(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $version = $request->input('version');

        if ($app->isCritical()) {
            return response()->json(['success' => false, 'message' => "Cannot stop {$app->name} — it is a critical service required by the panel"], 422);
        }

        if ($app->isActiveVersion($version)) {
            return response()->json(['success' => false, 'message' => "Cannot stop the active {$app->name} version. Switch the default version first."], 422);
        }

        $result = $this->service->stopApp($app, $version);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/v1/app-manager/{slug}/restart
     */
    public function restart(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $result = $this->service->restartApp($app, $request->input('version'));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/v1/app-manager/{slug}/enable
     */
    public function enable(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $result = $this->service->enableApp($app, $request->input('version'));

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/v1/app-manager/{slug}/disable
     */
    public function disable(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $version = $request->input('version');

        if ($app->isCritical()) {
            return response()->json(['success' => false, 'message' => "Cannot disable {$app->name} — it is a critical service required by the panel"], 422);
        }

        if ($app->isActiveVersion($version)) {
            return response()->json(['success' => false, 'message' => "Cannot disable the active {$app->name} version. Switch the default version first."], 422);
        }

        $result = $this->service->disableApp($app, $version);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/v1/app-manager/{slug}/install - Async install with Task tracking.
     */
    public function install(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();

        if ($app->isSystem()) {
            return response()->json(['success' => false, 'message' => 'Cannot install system apps'], 422);
        }

        $version = $request->input('version');
        $config = config("appmanager.apps.{$slug}", []);

        if ($config['type'] === 'multi_version' && ! $version) {
            return response()->json(['success' => false, 'message' => 'Version is required'], 422);
        }

        // Check if already installed (multi-version)
        if ($config['type'] === 'multi_version' && $version) {
            if (in_array($version, $app->installed_versions ?? [])) {
                return response()->json(['success' => false, 'message' => "{$app->name} {$version} is already installed"], 422);
            }
        }

        // Create a Task
        $taskName = "Install {$app->name}" . ($version ? " {$version}" : '');
        $task = $this->taskService->create(
            type: Task::TYPE_SERVICE_INSTALL,
            name: $taskName,
            description: "Installing {$app->name}" . ($version ? " version {$version}" : ''),
            inputData: ['slug' => $slug, 'version' => $version],
            relatedType: ManagedApp::class,
            relatedId: $app->id,
        );

        // Dispatch job
        InstallAppVersionJob::dispatch($app, $version, $task->id);

        return response()->json([
            'success' => true,
            'message' => "Installation of {$taskName} has been queued",
            'data' => [
                'task_id' => $task->id,
            ],
        ]);
    }

    /**
     * POST /api/v1/app-manager/{slug}/uninstall - Async uninstall with Task tracking.
     */
    public function uninstall(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();

        if ($app->isCritical()) {
            return response()->json(['success' => false, 'message' => "Cannot uninstall {$app->name} — it is a critical service required by the panel"], 422);
        }

        $version = $request->input('version');
        $config = config("appmanager.apps.{$slug}", []);

        if ($config['type'] === 'multi_version' && ! $version) {
            return response()->json(['success' => false, 'message' => 'Version is required'], 422);
        }

        // Prevent uninstalling the active version of multi-version apps
        if ($app->isActiveVersion($version)) {
            return response()->json(['success' => false, 'message' => "Cannot uninstall the active {$app->name} version. Switch the default version first."], 422);
        }

        // Create a Task
        $taskName = "Uninstall {$app->name}" . ($version ? " {$version}" : '');
        $task = $this->taskService->create(
            type: Task::TYPE_SERVICE_UNINSTALL,
            name: $taskName,
            description: "Uninstalling {$app->name}" . ($version ? " version {$version}" : ''),
            inputData: ['slug' => $slug, 'version' => $version],
            relatedType: ManagedApp::class,
            relatedId: $app->id,
        );

        // Dispatch job
        UninstallAppVersionJob::dispatch($app, $version, $task->id);

        return response()->json([
            'success' => true,
            'message' => "Uninstall of {$taskName} has been queued",
            'data' => [
                'task_id' => $task->id,
            ],
        ]);
    }

    /**
     * POST /api/v1/app-manager/{slug}/set-default
     */
    public function setDefaultVersion(Request $request, string $slug): JsonResponse
    {
        $request->validate(['version' => 'required|string']);

        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $result = $this->service->setDefaultVersion($app, $request->version);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/v1/app-manager/{slug}/extensions
     */
    public function extensions(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->service->getExtensions($app, $request->query('version')),
        ]);
    }

    /**
     * GET /api/v1/app-manager/{slug}/available-extensions
     */
    public function availableExtensions(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->service->getAvailableExtensions($app, $request->query('version')),
        ]);
    }

    /**
     * POST /api/v1/app-manager/{slug}/extensions/install
     */
    public function installExtension(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'version' => 'required|string',
            'packages' => 'required|array|min:1',
            'packages.*' => 'string',
        ]);

        $app = ManagedApp::where('slug', $slug)->firstOrFail();

        if ($app->slug !== 'php') {
            return response()->json(['success' => false, 'message' => 'Extension management is only available for PHP'], 422);
        }

        $version = $request->input('version');
        if (! in_array($version, $app->installed_versions ?? [])) {
            return response()->json(['success' => false, 'message' => "PHP {$version} is not installed"], 422);
        }

        $packages = $request->input('packages');
        $extNames = array_map(fn ($p) => str_replace("php{$version}-", '', $p), $packages);

        $task = $this->taskService->create(
            type: Task::TYPE_SERVICE_INSTALL,
            name: "Install PHP {$version} extensions: " . implode(', ', $extNames),
            description: 'Installing PHP extensions: ' . implode(', ', $packages),
            inputData: ['slug' => $slug, 'version' => $version, 'packages' => $packages, 'action' => 'install'],
            relatedType: ManagedApp::class,
            relatedId: $app->id,
        );

        ManagePhpExtensionJob::dispatch($app, $version, 'install', $packages, $task->id);

        return response()->json([
            'success' => true,
            'message' => 'Extension installation has been queued',
            'data' => ['task_id' => $task->id],
        ]);
    }

    /**
     * POST /api/v1/app-manager/{slug}/extensions/uninstall
     */
    public function uninstallExtension(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'version' => 'required|string',
            'packages' => 'required|array|min:1',
            'packages.*' => 'string',
        ]);

        $app = ManagedApp::where('slug', $slug)->firstOrFail();

        if ($app->slug !== 'php') {
            return response()->json(['success' => false, 'message' => 'Extension management is only available for PHP'], 422);
        }

        $version = $request->input('version');
        if (! in_array($version, $app->installed_versions ?? [])) {
            return response()->json(['success' => false, 'message' => "PHP {$version} is not installed"], 422);
        }

        // Prevent uninstalling critical extensions
        $criticalExtensions = ["php{$version}-common", "php{$version}-cli", "php{$version}-fpm"];
        $packages = $request->input('packages');
        $blocked = array_intersect($packages, $criticalExtensions);
        if (! empty($blocked)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot uninstall critical extensions: ' . implode(', ', $blocked),
            ], 422);
        }

        $extNames = array_map(fn ($p) => str_replace("php{$version}-", '', $p), $packages);

        $task = $this->taskService->create(
            type: Task::TYPE_SERVICE_UNINSTALL,
            name: "Uninstall PHP {$version} extensions: " . implode(', ', $extNames),
            description: 'Removing PHP extensions: ' . implode(', ', $packages),
            inputData: ['slug' => $slug, 'version' => $version, 'packages' => $packages, 'action' => 'uninstall'],
            relatedType: ManagedApp::class,
            relatedId: $app->id,
        );

        ManagePhpExtensionJob::dispatch($app, $version, 'uninstall', $packages, $task->id);

        return response()->json([
            'success' => true,
            'message' => 'Extension removal has been queued',
            'data' => ['task_id' => $task->id],
        ]);
    }

    /**
     * GET /api/v1/app-manager/{slug}/config/{key}
     */
    public function getConfig(Request $request, string $slug, string $key): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $result = $this->service->getConfigFile($app, $key, $request->query('version'));

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * PUT /api/v1/app-manager/{slug}/config/{key}
     */
    public function saveConfig(Request $request, string $slug, string $key): JsonResponse
    {
        $request->validate(['content' => 'required|string']);

        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $result = $this->service->saveConfigFile($app, $key, $request->content, $request->input('version'));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/v1/app-manager/{slug}/logs
     */
    public function logs(Request $request, string $slug): JsonResponse
    {
        $app = ManagedApp::where('slug', $slug)->firstOrFail();
        $lines = min((int) $request->query('lines', 100), 200);

        return response()->json([
            'success' => true,
            'data' => [
                'service' => $slug,
                'logs' => $this->service->getServiceLogs($app, $lines, $request->query('version')),
            ],
        ]);
    }
}
