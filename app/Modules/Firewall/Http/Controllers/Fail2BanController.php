<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Firewall\Services\Fail2BanService;
use App\Modules\Firewall\Services\IpManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Fail2BanController extends Controller
{
    public function __construct(
        protected Fail2BanService $fail2BanService,
        protected IpManagementService $ipManagementService
    ) {}

    /**
     * Get Fail2Ban status
     */
    public function status(): JsonResponse
    {
        $status = $this->fail2BanService->getStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Get all jails
     */
    public function jails(): JsonResponse
    {
        $jails = $this->fail2BanService->getJails();

        return response()->json([
            'success' => true,
            'data' => $jails,
        ]);
    }

    /**
     * Get specific jail status
     */
    public function jail(string $jail): JsonResponse
    {
        $status = $this->fail2BanService->getJailStatus($jail);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Update jail configuration
     */
    public function updateJailConfig(Request $request, string $jail): JsonResponse
    {
        $request->validate([
            'bantime' => 'sometimes|integer|min:60|max:2592000',
            'findtime' => 'sometimes|integer|min:60|max:86400',
            'maxretry' => 'sometimes|integer|min:1|max:100',
        ]);

        $config = $request->only(['bantime', 'findtime', 'maxretry']);

        try {
            $this->fail2BanService->setJailConfig($jail, $config);

            return response()->json([
                'success' => true,
                'message' => 'Jail configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONFIG_UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get all banned IPs
     */
    public function banned(): JsonResponse
    {
        $bannedIps = $this->fail2BanService->getBannedIps();

        // Enrich with IP info
        foreach ($bannedIps as &$banned) {
            $banned['info'] = $this->ipManagementService->getIpInfo($banned['ip']);
        }

        return response()->json([
            'success' => true,
            'data' => $bannedIps,
        ]);
    }

    /**
     * Ban an IP address
     */
    public function ban(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'jail' => 'sometimes|string',
        ]);

        $ip = $request->input('ip');
        $jail = $request->input('jail', 'sshd');

        // Check if whitelisted
        if ($this->ipManagementService->isWhitelisted($ip)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IP_WHITELISTED',
                    'message' => 'Cannot ban a whitelisted IP address',
                ],
            ], 400);
        }

        $result = $this->fail2BanService->banIp($ip, $jail);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'IP banned successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'BAN_FAILED',
                'message' => $result['error'] ?? 'Failed to ban IP',
            ],
        ], 500);
    }

    /**
     * Unban an IP address
     */
    public function unban(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'jail' => 'nullable|string',
        ]);

        $result = $this->fail2BanService->unbanIp(
            $request->input('ip'),
            $request->input('jail')
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'IP unbanned successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNBAN_FAILED',
                'message' => $result['error'] ?? 'Failed to unban IP',
            ],
        ], 500);
    }

    /**
     * Get IP whitelist
     */
    public function whitelist(): JsonResponse
    {
        $whitelist = $this->ipManagementService->getWhitelist();

        $data = array_map(function ($ip) {
            return [
                'ip' => $ip,
                'info' => $this->ipManagementService->getIpInfo($ip),
            ];
        }, $whitelist);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
        ]);

        try {
            $this->ipManagementService->addToWhitelist($request->input('ip'));

            return response()->json([
                'success' => true,
                'message' => 'IP added to whitelist successfully',
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
     * Remove IP from whitelist
     */
    public function removeFromWhitelist(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
        ]);

        try {
            $this->ipManagementService->removeFromWhitelist($request->input('ip'));

            return response()->json([
                'success' => true,
                'message' => 'IP removed from whitelist successfully',
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

    /**
     * Get IP blacklist
     */
    public function blacklist(): JsonResponse
    {
        $blacklist = $this->ipManagementService->getBlacklist();

        // Enrich with IP info
        foreach ($blacklist as &$item) {
            $item['info'] = $this->ipManagementService->getIpInfo($item['ip']);
        }

        return response()->json([
            'success' => true,
            'data' => $blacklist,
        ]);
    }

    /**
     * Add IP to blacklist
     */
    public function addToBlacklist(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'reason' => 'nullable|string|max:255',
        ]);

        $ip = $request->input('ip');

        // Check if whitelisted
        if ($this->ipManagementService->isWhitelisted($ip)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IP_WHITELISTED',
                    'message' => 'Cannot blacklist a whitelisted IP. Remove it from whitelist first.',
                ],
            ], 400);
        }

        try {
            $this->ipManagementService->addToBlacklist(
                $ip,
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'IP added to blacklist successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BLACKLIST_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Remove IP from blacklist
     */
    public function removeFromBlacklist(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
        ]);

        try {
            $this->ipManagementService->removeFromBlacklist($request->input('ip'));

            return response()->json([
                'success' => true,
                'message' => 'IP removed from blacklist successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BLACKLIST_REMOVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get IP information
     */
    public function ipInfo(string $ip): JsonResponse
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_IP',
                    'message' => 'Invalid IP address',
                ],
            ], 400);
        }

        $info = $this->ipManagementService->getIpInfo($ip);
        $info['is_whitelisted'] = $this->ipManagementService->isWhitelisted($ip);
        $info['is_blacklisted'] = $this->ipManagementService->isBlacklisted($ip);

        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    /**
     * Restart Fail2Ban service
     */
    public function restart(): JsonResponse
    {
        $result = $this->fail2BanService->restart();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Fail2Ban restarted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RESTART_FAILED',
                'message' => $result['error'] ?? 'Failed to restart Fail2Ban',
            ],
        ], 500);
    }
}
