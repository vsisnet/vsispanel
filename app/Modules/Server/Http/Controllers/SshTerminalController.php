<?php

declare(strict_types=1);

namespace App\Modules\Server\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class SshTerminalController extends Controller
{
    /**
     * Create a terminal session token.
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admins or users with terminal permission
        if (! $user->isAdmin() && ! $user->hasPermissionTo('terminal.access')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access the terminal.',
                ],
            ], 403);
        }

        // Generate one-time token
        $token = Str::random(64);

        // Determine username for the shell session
        $username = $user->isAdmin() ? 'root' : ($user->system_username ?? $user->username);

        // Store token in Redis with 60s TTL
        Redis::setex("terminal:token:{$token}", 60, json_encode([
            'userId' => $user->id,
            'username' => $username,
        ]));

        $wsPort = config('terminal.port', 8022);
        $wsHost = $request->getHost();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'ws_url' => "ws://{$wsHost}:{$wsPort}",
            ],
        ]);
    }

    /**
     * List active terminal sessions.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Admin only.'],
            ], 403);
        }

        $sessions = Redis::hgetall('terminal:sessions');
        $result = [];

        foreach ($sessions as $id => $data) {
            $session = json_decode($data, true);
            $session['id'] = $id;
            $result[] = $session;
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Close a terminal session.
     */
    public function closeSession(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Admin only.'],
            ], 403);
        }

        Redis::hdel('terminal:sessions', $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Session marked for closure.',
        ]);
    }
}
