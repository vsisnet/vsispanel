<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firewall\Http\Requests\StoreFirewallRuleRequest;
use App\Modules\Firewall\Http\Requests\UpdateFirewallRuleRequest;
use App\Modules\Firewall\Http\Resources\FirewallRuleResource;
use App\Modules\Firewall\Models\FirewallRule;
use App\Modules\Firewall\Services\FirewallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirewallController extends Controller
{
    public function __construct(
        protected FirewallService $firewallService
    ) {}

    /**
     * Get firewall status
     */
    public function status(): JsonResponse
    {
        $status = $this->firewallService->getStatus();
        $policies = $this->firewallService->getDefaultPolicies();

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $status['enabled'],
                'default_policies' => $policies,
            ],
        ]);
    }

    /**
     * Get all firewall rules
     */
    public function index(): JsonResponse
    {
        $rules = $this->firewallService->getRulesList();

        return response()->json([
            'success' => true,
            'data' => FirewallRuleResource::collection($rules),
        ]);
    }

    /**
     * Store a new firewall rule
     */
    public function store(StoreFirewallRuleRequest $request): JsonResponse
    {
        try {
            $rule = $this->firewallService->addRule($request->validated());

            return response()->json([
                'success' => true,
                'data' => new FirewallRuleResource($rule),
                'message' => 'Firewall rule created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RULE_CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Show a firewall rule
     */
    public function show(FirewallRule $rule): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new FirewallRuleResource($rule),
        ]);
    }

    /**
     * Update a firewall rule
     */
    public function update(UpdateFirewallRuleRequest $request, FirewallRule $rule): JsonResponse
    {
        if ($rule->is_essential) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_MODIFY_ESSENTIAL',
                    'message' => 'Cannot modify essential firewall rules',
                ],
            ], 403);
        }

        try {
            // If rule was active, remove old rule from UFW
            if ($rule->is_active) {
                $this->firewallService->toggleRule($rule);
            }

            // Update the rule
            $rule->update($request->validated());

            // If rule should be active, apply to UFW
            if ($request->input('is_active', $rule->is_active)) {
                $this->firewallService->toggleRule($rule);
            }

            return response()->json([
                'success' => true,
                'data' => new FirewallRuleResource($rule->fresh()),
                'message' => 'Firewall rule updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RULE_UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Delete a firewall rule
     */
    public function destroy(FirewallRule $rule): JsonResponse
    {
        if ($rule->is_essential) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_ESSENTIAL',
                    'message' => 'Cannot delete essential firewall rules',
                ],
            ], 403);
        }

        try {
            $this->firewallService->deleteRule($rule);

            return response()->json([
                'success' => true,
                'message' => 'Firewall rule deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RULE_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Toggle rule active status
     */
    public function toggle(FirewallRule $rule): JsonResponse
    {
        try {
            $rule = $this->firewallService->toggleRule($rule);

            return response()->json([
                'success' => true,
                'data' => new FirewallRuleResource($rule),
                'message' => $rule->is_active ? 'Rule activated' : 'Rule deactivated',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOGGLE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Enable firewall
     */
    public function enable(): JsonResponse
    {
        $result = $this->firewallService->enable();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Firewall enabled successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ENABLE_FAILED',
                'message' => $result['error'] ?? 'Failed to enable firewall',
            ],
        ], 500);
    }

    /**
     * Disable firewall
     */
    public function disable(): JsonResponse
    {
        $result = $this->firewallService->disable();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Firewall disabled successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'DISABLE_FAILED',
                'message' => $result['error'] ?? 'Failed to disable firewall',
            ],
        ], 500);
    }

    /**
     * Reset firewall to defaults
     */
    public function reset(): JsonResponse
    {
        $result = $this->firewallService->resetToDefaults();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Firewall reset to defaults successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RESET_FAILED',
                'message' => $result['error'] ?? 'Failed to reset firewall',
            ],
        ], 500);
    }

    /**
     * Set default policy
     */
    public function setDefaultPolicy(Request $request): JsonResponse
    {
        $request->validate([
            'direction' => 'required|in:incoming,outgoing,routed',
            'policy' => 'required|in:allow,deny,reject',
        ]);

        try {
            $result = $this->firewallService->setDefaultPolicy(
                $request->input('direction'),
                $request->input('policy')
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Default policy updated successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POLICY_UPDATE_FAILED',
                    'message' => $result['error'] ?? 'Failed to update policy',
                ],
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'POLICY_UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Quick add: Block IP
     */
    public function blockIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'comment' => 'nullable|string|max:255',
        ]);

        try {
            $rule = $this->firewallService->blockIp(
                $request->input('ip'),
                $request->input('comment')
            );

            return response()->json([
                'success' => true,
                'data' => new FirewallRuleResource($rule),
                'message' => 'IP blocked successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BLOCK_IP_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Quick add: Allow IP
     */
    public function allowIp(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'comment' => 'nullable|string|max:255',
        ]);

        try {
            $rule = $this->firewallService->allowIp(
                $request->input('ip'),
                $request->input('comment')
            );

            return response()->json([
                'success' => true,
                'data' => new FirewallRuleResource($rule),
                'message' => 'IP allowed successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALLOW_IP_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Sync rules with UFW
     */
    public function sync(): JsonResponse
    {
        try {
            $this->firewallService->syncRulesWithUfw();

            return response()->json([
                'success' => true,
                'message' => 'Rules synced with UFW successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYNC_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
