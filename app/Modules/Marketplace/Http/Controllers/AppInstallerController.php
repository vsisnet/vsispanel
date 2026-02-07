<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Domain\Models\Domain;
use App\Modules\Marketplace\Models\AppTemplate;
use App\Modules\Marketplace\Services\AppInstallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AppInstallerController extends Controller
{
    public function __construct(
        private readonly AppInstallerService $installer,
    ) {}

    /**
     * List available applications.
     */
    public function index(): JsonResponse
    {
        $apps = $this->installer->getAvailableApps();

        return response()->json([
            'success' => true,
            'data' => $apps,
        ]);
    }

    /**
     * Check requirements for installing an app on a domain.
     */
    public function checkRequirements(Request $request, Domain $domain): JsonResponse
    {
        $request->validate([
            'app_id' => 'required|exists:app_templates,id',
        ]);

        $app = AppTemplate::findOrFail($request->app_id);
        $result = $this->installer->checkRequirements($domain, $app);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Install an application on a domain.
     */
    public function install(Request $request, Domain $domain): JsonResponse
    {
        $request->validate([
            'app_id' => 'required|exists:app_templates,id',
            'options' => 'nullable|array',
        ]);

        $app = AppTemplate::findOrFail($request->app_id);

        // Check requirements first
        $reqCheck = $this->installer->checkRequirements($domain, $app);
        if (! $reqCheck['passed']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REQUIREMENTS_NOT_MET',
                    'message' => 'Server does not meet the app requirements.',
                    'checks' => $reqCheck['checks'],
                ],
            ], 422);
        }

        $installation = $this->installer->install(
            $domain,
            $app,
            $request->input('options', []),
            $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'installation_id' => $installation->id,
                'status' => $installation->status,
            ],
            'message' => 'Installation started.',
        ]);
    }

    /**
     * Get installation status.
     */
    public function installStatus(Request $request, Domain $domain): JsonResponse
    {
        $installationId = $request->query('installation_id');

        if ($installationId) {
            $installation = $this->installer->getInstallationStatus($installationId);

            return response()->json([
                'success' => true,
                'data' => $installation,
            ]);
        }

        $installations = $this->installer->getDomainInstallations($domain);

        return response()->json([
            'success' => true,
            'data' => $installations,
        ]);
    }
}
