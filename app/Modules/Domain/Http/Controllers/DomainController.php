<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Controllers;

use App\Modules\Base\Http\Controllers\ApiController;
use App\Modules\Domain\Http\Requests\CreateDomainRequest;
use App\Modules\Domain\Http\Requests\UpdateDomainRequest;
use App\Modules\Domain\Http\Resources\DomainCollection;
use App\Modules\Domain\Http\Resources\DomainResource;
use App\Modules\Domain\Models\Domain;
use App\Modules\Domain\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DomainController extends ApiController
{
    public function __construct(
        protected DomainService $domainService
    ) {}

    /**
     * List all domains for the authenticated user.
     */
    #[OA\Get(
        path: '/api/v1/domains',
        operationId: 'listDomains',
        tags: ['Domains'],
        summary: 'List all domains',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'suspended', 'disabled', 'pending'])),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Domain::class);

        $query = Domain::query()
            ->with('user')
            ->withCount('subdomains');

        // Filter by user if not admin
        if (!$this->isAdmin()) {
            if ($this->isReseller()) {
                // Reseller sees their own domains + customer domains
                $customerIds = $this->user()->customers()->pluck('id');
                $query->whereIn('user_id', $customerIds->push($this->user()->id));
            } else {
                $query->where('user_id', $this->user()->id);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by domain name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Order by created_at desc
        $query->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 15), 100);
        $domains = $query->paginate($perPage);

        return $this->paginatedResponse($domains, DomainResource::class);
    }

    /**
     * Create a new domain.
     */
    #[OA\Post(
        path: '/api/v1/domains',
        operationId: 'createDomain',
        tags: ['Domains'],
        summary: 'Create a new domain',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'example.com'),
                    new OA\Property(property: 'php_version', type: 'string', example: '8.3'),
                    new OA\Property(property: 'web_server_type', type: 'string', enum: ['nginx', 'apache']),
                    new OA\Property(property: 'is_main', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Domain created'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function store(CreateDomainRequest $request): JsonResponse
    {
        $this->authorize('create', Domain::class);

        $domain = $this->domainService->create(
            $this->user(),
            $request->validated()
        );

        return $this->createdResponse(
            new DomainResource($domain->load('user')),
            'Domain created successfully'
        );
    }

    /**
     * Show a specific domain.
     */
    #[OA\Get(
        path: '/api/v1/domains/{domain}',
        operationId: 'showDomain',
        tags: ['Domains'],
        summary: 'Get domain details',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 404, description: 'Domain not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function show(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        $domain->load(['user', 'subdomains']);

        return $this->successResponse(new DomainResource($domain));
    }

    /**
     * Update a domain.
     */
    #[OA\Put(
        path: '/api/v1/domains/{domain}',
        operationId: 'updateDomain',
        tags: ['Domains'],
        summary: 'Update a domain',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'php_version', type: 'string'),
                    new OA\Property(property: 'web_server_type', type: 'string'),
                    new OA\Property(property: 'is_main', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Domain updated'),
            new OA\Response(response: 404, description: 'Domain not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function update(UpdateDomainRequest $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $domain = $this->domainService->update($domain, $request->validated());

        return $this->successResponse(
            new DomainResource($domain->load('user')),
            'Domain updated successfully'
        );
    }

    /**
     * Delete a domain.
     */
    #[OA\Delete(
        path: '/api/v1/domains/{domain}',
        operationId: 'deleteDomain',
        tags: ['Domains'],
        summary: 'Delete a domain',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Domain deleted'),
            new OA\Response(response: 404, description: 'Domain not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function destroy(Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);

        $this->domainService->delete($domain);

        return $this->successResponse(null, 'Domain deleted successfully');
    }

    /**
     * Suspend a domain.
     */
    #[OA\Post(
        path: '/api/v1/domains/{domain}/suspend',
        operationId: 'suspendDomain',
        tags: ['Domains'],
        summary: 'Suspend a domain',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Domain suspended'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Domain not found'),
        ]
    )]
    public function suspend(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('suspend', $domain);

        $reason = $request->get('reason', '');
        $this->domainService->suspend($domain, $reason);

        return $this->successResponse(
            new DomainResource($domain->fresh()->load('user')),
            'Domain suspended successfully'
        );
    }

    /**
     * Unsuspend a domain.
     */
    #[OA\Post(
        path: '/api/v1/domains/{domain}/unsuspend',
        operationId: 'unsuspendDomain',
        tags: ['Domains'],
        summary: 'Unsuspend a domain',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Domain unsuspended'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Domain not found'),
        ]
    )]
    public function unsuspend(Domain $domain): JsonResponse
    {
        $this->authorize('unsuspend', $domain);

        $this->domainService->unsuspend($domain);

        return $this->successResponse(
            new DomainResource($domain->fresh()->load('user')),
            'Domain unsuspended successfully'
        );
    }

    /**
     * Get disk usage for a domain.
     */
    #[OA\Get(
        path: '/api/v1/domains/{domain}/disk-usage',
        operationId: 'getDomainDiskUsage',
        tags: ['Domains'],
        summary: 'Get domain disk usage',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 404, description: 'Domain not found'),
        ]
    )]
    public function diskUsage(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        $bytes = $this->domainService->getDiskUsage($domain);

        return $this->successResponse([
            'bytes' => $bytes,
            'formatted' => $domain->fresh()->disk_used_formatted,
        ]);
    }
}
