<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Controllers;

use App\Modules\Base\Http\Controllers\ApiController;
use App\Modules\Domain\Http\Requests\CreateSubdomainRequest;
use App\Modules\Domain\Http\Resources\SubdomainResource;
use App\Modules\Domain\Models\Domain;
use App\Modules\Domain\Models\Subdomain;
use App\Modules\Domain\Services\DomainService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SubdomainController extends ApiController
{
    public function __construct(
        protected DomainService $domainService
    ) {}

    /**
     * List all subdomains for a domain.
     */
    #[OA\Get(
        path: '/api/v1/domains/{domain}/subdomains',
        operationId: 'listSubdomains',
        tags: ['Domains'],
        summary: 'List subdomains for a domain',
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
    public function index(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        $subdomains = $domain->subdomains()->orderBy('name')->get();

        return $this->successResponse(
            SubdomainResource::collection($subdomains)
        );
    }

    /**
     * Create a new subdomain.
     */
    #[OA\Post(
        path: '/api/v1/domains/{domain}/subdomains',
        operationId: 'createSubdomain',
        tags: ['Domains'],
        summary: 'Create a subdomain',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'app'),
                    new OA\Property(property: 'php_version', type: 'string', example: '8.3'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Subdomain created'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Domain not found'),
        ]
    )]
    public function store(CreateSubdomainRequest $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $subdomain = $this->domainService->createSubdomain(
            $domain,
            $request->validated()
        );

        return $this->createdResponse(
            new SubdomainResource($subdomain),
            'Subdomain created successfully'
        );
    }

    /**
     * Show a specific subdomain.
     */
    #[OA\Get(
        path: '/api/v1/domains/{domain}/subdomains/{subdomain}',
        operationId: 'showSubdomain',
        tags: ['Domains'],
        summary: 'Get subdomain details',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Domain $domain, Subdomain $subdomain): JsonResponse
    {
        $this->authorize('view', $domain);

        // Ensure subdomain belongs to domain
        if ($subdomain->domain_id !== $domain->id) {
            return $this->notFoundResponse('Subdomain not found');
        }

        return $this->successResponse(new SubdomainResource($subdomain));
    }

    /**
     * Delete a subdomain.
     */
    #[OA\Delete(
        path: '/api/v1/domains/{domain}/subdomains/{subdomain}',
        operationId: 'deleteSubdomain',
        tags: ['Domains'],
        summary: 'Delete a subdomain',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'domain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'subdomain', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subdomain deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Domain $domain, Subdomain $subdomain): JsonResponse
    {
        $this->authorize('update', $domain);

        // Ensure subdomain belongs to domain
        if ($subdomain->domain_id !== $domain->id) {
            return $this->notFoundResponse('Subdomain not found');
        }

        $this->domainService->deleteSubdomain($subdomain);

        return $this->successResponse(null, 'Subdomain deleted successfully');
    }
}
