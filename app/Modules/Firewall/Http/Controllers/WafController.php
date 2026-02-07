<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firewall\Services\WafService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WafController extends Controller
{
    public function __construct(
        protected WafService $wafService
    ) {}

    /**
     * Get WAF status
     */
    public function status(): JsonResponse
    {
        $status = $this->wafService->getStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Enable WAF
     */
    public function enable(): JsonResponse
    {
        $result = $this->wafService->enable();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ENABLE_FAILED',
                'message' => $result['error'],
            ],
        ], 500);
    }

    /**
     * Disable WAF
     */
    public function disable(): JsonResponse
    {
        $result = $this->wafService->disable();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'DISABLE_FAILED',
                'message' => $result['error'],
            ],
        ], 500);
    }

    /**
     * Set WAF mode
     */
    public function setMode(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => 'required|in:On,Off,DetectionOnly',
        ]);

        $result = $this->wafService->setMode($request->input('mode'));

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'MODE_CHANGE_FAILED',
                'message' => $result['error'],
            ],
        ], 500);
    }

    /**
     * Get audit log
     */
    public function auditLog(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 100);
        $entries = $this->wafService->getAuditLog((int)$limit);

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    /**
     * Get rulesets
     */
    public function rulesets(): JsonResponse
    {
        $rulesets = $this->wafService->getRulesets();

        return response()->json([
            'success' => true,
            'data' => $rulesets,
        ]);
    }

    /**
     * Enable a ruleset
     */
    public function enableRuleset(string $ruleset): JsonResponse
    {
        $result = $this->wafService->enableRuleset($ruleset);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
        ]);
    }

    /**
     * Disable a ruleset
     */
    public function disableRuleset(string $ruleset): JsonResponse
    {
        $result = $this->wafService->disableRuleset($ruleset);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
        ]);
    }

    /**
     * Get whitelisted rules
     */
    public function whitelist(): JsonResponse
    {
        $rules = $this->wafService->getWhitelistedRules();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Add rule to whitelist
     */
    public function addToWhitelist(Request $request): JsonResponse
    {
        $request->validate([
            'rule_id' => 'required|string',
            'domain' => 'nullable|string',
        ]);

        try {
            $this->wafService->addWhitelistRule(
                $request->input('rule_id'),
                $request->input('domain')
            );

            return response()->json([
                'success' => true,
                'message' => 'Rule added to whitelist',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WHITELIST_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Remove rule from whitelist
     */
    public function removeFromWhitelist(string $ruleId): JsonResponse
    {
        try {
            $this->wafService->removeWhitelistRule($ruleId);

            return response()->json([
                'success' => true,
                'message' => 'Rule removed from whitelist',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WHITELIST_REMOVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
