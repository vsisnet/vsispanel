<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Domain\Models\Domain;
use App\Modules\Mail\Http\Resources\MailDomainResource;
use App\Modules\Mail\Models\MailDomain;
use App\Modules\Mail\Services\MailAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MailDomainController extends Controller
{
    public function __construct(
        protected MailAccountService $mailAccountService
    ) {}

    /**
     * List mail domains for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = MailDomain::with(['domain', 'accounts'])
            ->whereHas('domain', function ($q) use ($user) {
                if ($user->isAdmin()) {
                    // Admin sees all
                } elseif ($user->isReseller()) {
                    $customerIds = \App\Modules\Auth\Models\User::where('parent_id', $user->id)->pluck('id')->push($user->id)->toArray();
                    $q->whereIn('user_id', $customerIds);
                } else {
                    $q->where('user_id', $user->id);
                }
            });

        if ($request->has('search')) {
            $query->whereHas('domain', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        $mailDomains = $query->latest()->paginate($request->per_page ?? 15);

        return MailDomainResource::collection($mailDomains);
    }

    /**
     * Enable mail for a domain.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'domain_id' => ['required', 'uuid', 'exists:domains,id'],
            'max_accounts' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'default_quota_mb' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'enable_dkim' => ['nullable', 'boolean'],
        ]);

        $domain = Domain::findOrFail($request->domain_id);

        // Check ownership
        if (!$request->user()->isAdmin() && $domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to manage this domain.',
                ],
            ], 403);
        }

        try {
            $mailDomain = $this->mailAccountService->enableMailForDomain($domain, [
                'max_accounts' => $request->max_accounts ?? 100,
                'default_quota_mb' => $request->default_quota_mb ?? 1024,
                'enable_dkim' => $request->enable_dkim ?? true,
            ]);

            return response()->json([
                'success' => true,
                'data' => new MailDomainResource($mailDomain->load('domain')),
                'message' => 'Mail enabled for domain successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MAIL_ENABLE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get mail domain details.
     */
    public function show(Request $request, MailDomain $mailDomain): MailDomainResource|JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view this mail domain.',
                ],
            ], 403);
        }

        return new MailDomainResource($mailDomain->load(['domain', 'accounts', 'aliases']));
    }

    /**
     * Update mail domain settings.
     */
    public function update(Request $request, MailDomain $mailDomain): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to update this mail domain.',
                ],
            ], 403);
        }

        $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'catch_all_address' => ['nullable', 'email'],
            'max_accounts' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'default_quota_mb' => ['nullable', 'integer', 'min:1', 'max:102400'],
        ]);

        try {
            // Handle catch-all
            if ($request->has('catch_all_address')) {
                $this->mailAccountService->setCatchAll($mailDomain, $request->catch_all_address);
            }

            // Update other settings
            $mailDomain->update($request->only(['is_active', 'max_accounts', 'default_quota_mb']));

            return response()->json([
                'success' => true,
                'data' => new MailDomainResource($mailDomain->fresh(['domain'])),
                'message' => 'Mail domain updated successfully.',
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
     * Disable mail for a domain.
     */
    public function destroy(Request $request, MailDomain $mailDomain): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to disable mail for this domain.',
                ],
            ], 403);
        }

        try {
            $this->mailAccountService->disableMailForDomain($mailDomain);

            return response()->json([
                'success' => true,
                'message' => 'Mail disabled for domain successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MAIL_DISABLE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
