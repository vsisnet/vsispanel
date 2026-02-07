<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mail\Http\Resources\MailAliasResource;
use App\Modules\Mail\Models\MailAlias;
use App\Modules\Mail\Models\MailDomain;
use App\Modules\Mail\Services\MailAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MailAliasController extends Controller
{
    public function __construct(
        protected MailAccountService $mailAccountService
    ) {}

    /**
     * List mail aliases.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = MailAlias::with('mailDomain.domain')
            ->whereHas('mailDomain.domain', function ($q) use ($user) {
                if (!$user->isAdmin()) {
                    $q->where('user_id', $user->id);
                }
            });

        // Filter by domain
        if ($request->has('mail_domain_id')) {
            $query->where('mail_domain_id', $request->mail_domain_id);
        }

        // Search
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('source_address', 'like', '%' . $request->search . '%')
                    ->orWhere('destination_address', 'like', '%' . $request->search . '%');
            });
        }

        $aliases = $query->latest()->paginate($request->per_page ?? 15);

        return MailAliasResource::collection($aliases);
    }

    /**
     * Create an alias.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'mail_domain_id' => ['required', 'uuid', 'exists:mail_domains,id'],
            'source' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'email'],
        ]);

        $mailDomain = MailDomain::findOrFail($request->mail_domain_id);

        // Check ownership
        if (!$request->user()->isAdmin() && $mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to create aliases for this domain.',
                ],
            ], 403);
        }

        try {
            $alias = $this->mailAccountService->createAlias(
                $mailDomain,
                $request->source,
                $request->destination
            );

            return response()->json([
                'success' => true,
                'data' => new MailAliasResource($alias),
                'message' => 'Alias created successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get alias details.
     */
    public function show(Request $request, MailAlias $alias): MailAliasResource|JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $alias->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view this alias.',
                ],
            ], 403);
        }

        return new MailAliasResource($alias);
    }

    /**
     * Update an alias.
     */
    public function update(Request $request, MailAlias $alias): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $alias->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to update this alias.',
                ],
            ], 403);
        }

        $request->validate([
            'destination' => ['nullable', 'email'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            // If destination changed, update in Postfix
            if ($request->has('destination') && $request->destination !== $alias->destination_address) {
                $this->mailAccountService->deleteAlias($alias);
                $alias = $this->mailAccountService->createAlias(
                    $alias->mailDomain,
                    $alias->source_address,
                    $request->destination
                );
            }

            if ($request->has('is_active')) {
                $alias->update(['is_active' => $request->is_active]);
            }

            return response()->json([
                'success' => true,
                'data' => new MailAliasResource($alias->fresh()),
                'message' => 'Alias updated successfully.',
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
     * Delete an alias.
     */
    public function destroy(Request $request, MailAlias $alias): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $alias->mailDomain->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to delete this alias.',
                ],
            ], 403);
        }

        try {
            $this->mailAccountService->deleteAlias($alias);

            return response()->json([
                'success' => true,
                'message' => 'Alias deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
