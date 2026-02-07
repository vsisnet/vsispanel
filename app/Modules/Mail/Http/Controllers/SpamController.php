<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mail\Services\RspamdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpamController extends Controller
{
    public function __construct(
        protected RspamdService $rspamdService
    ) {}

    /**
     * Get spam filter settings and statistics.
     */
    public function getSettings(Request $request): JsonResponse
    {
        $status = $this->rspamdService->getStatus();
        $scores = $this->rspamdService->getSpamScore();
        $statistics = $this->rspamdService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $status,
                'scores' => $scores,
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * Update spam filter settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'reject' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'add_header' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'greylist' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rewrite_subject' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $scores = array_filter($request->only(['reject', 'add_header', 'greylist', 'rewrite_subject']), fn($v) => $v !== null);

            $this->rspamdService->setSpamScore($scores);

            return response()->json([
                'success' => true,
                'data' => $this->rspamdService->getSpamScore(),
                'message' => 'Spam settings updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get whitelist entries.
     */
    public function getWhitelist(): JsonResponse
    {
        $whitelist = $this->rspamdService->getWhitelist();

        return response()->json([
            'success' => true,
            'data' => $whitelist,
        ]);
    }

    /**
     * Add to whitelist.
     */
    public function addToWhitelist(Request $request): JsonResponse
    {
        $request->validate([
            'entry' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:from,to,email,domain'],
        ]);

        try {
            $this->rspamdService->addToWhitelist(
                $request->entry,
                $request->type ?? 'from'
            );

            return response()->json([
                'success' => true,
                'data' => $this->rspamdService->getWhitelist(),
                'message' => 'Entry added to whitelist successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Remove from whitelist.
     */
    public function removeFromWhitelist(Request $request, string $entry): JsonResponse
    {
        try {
            $this->rspamdService->removeFromWhitelist(urldecode($entry));

            return response()->json([
                'success' => true,
                'message' => 'Entry removed from whitelist successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REMOVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get blacklist entries.
     */
    public function getBlacklist(): JsonResponse
    {
        $blacklist = $this->rspamdService->getBlacklist();

        return response()->json([
            'success' => true,
            'data' => $blacklist,
        ]);
    }

    /**
     * Add to blacklist.
     */
    public function addToBlacklist(Request $request): JsonResponse
    {
        $request->validate([
            'entry' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:from,to,email,domain'],
        ]);

        try {
            $this->rspamdService->addToBlacklist(
                $request->entry,
                $request->type ?? 'from'
            );

            return response()->json([
                'success' => true,
                'data' => $this->rspamdService->getBlacklist(),
                'message' => 'Entry added to blacklist successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Remove from blacklist.
     */
    public function removeFromBlacklist(Request $request, string $entry): JsonResponse
    {
        try {
            $this->rspamdService->removeFromBlacklist(urldecode($entry));

            return response()->json([
                'success' => true,
                'message' => 'Entry removed from blacklist successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REMOVE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get spam history.
     */
    public function getHistory(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 100);
        $history = $this->rspamdService->getHistory((int) $limit);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Train message as ham.
     */
    public function trainHam(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $success = $this->rspamdService->trainHam($request->message);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Message learned as ham successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TRAIN_FAILED',
                'message' => 'Failed to train message as ham.',
            ],
        ], 500);
    }

    /**
     * Train message as spam.
     */
    public function trainSpam(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $success = $this->rspamdService->trainSpam($request->message);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Message learned as spam successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TRAIN_FAILED',
                'message' => 'Failed to train message as spam.',
            ],
        ], 500);
    }
}
