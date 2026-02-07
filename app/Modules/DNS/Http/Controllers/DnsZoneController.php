<?php

declare(strict_types=1);

namespace App\Modules\DNS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DNS\Http\Resources\DnsZoneResource;
use App\Modules\DNS\Models\DnsZone;
use App\Modules\DNS\Services\PowerDnsService;
use App\Modules\Domain\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DnsZoneController extends Controller
{
    public function __construct(
        protected PowerDnsService $powerDnsService
    ) {}

    /**
     * List DNS zones.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = DnsZone::with('domain')
            ->withCount('records')
            ->whereHas('domain', function ($q) use ($user) {
                if (!$user->isAdmin()) {
                    $q->where('user_id', $user->id);
                }
            });

        // Filter by domain
        if ($request->has('domain_id')) {
            $query->where('domain_id', $request->domain_id);
        }

        // Search
        if ($request->has('search')) {
            $query->where('zone_name', 'like', '%' . $request->search . '%');
        }

        $zones = $query->latest()->paginate($request->per_page ?? 15);

        return DnsZoneResource::collection($zones);
    }

    /**
     * Create a DNS zone.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'domain_id' => ['required', 'uuid', 'exists:domains,id'],
            'server_ip' => ['nullable', 'ip'],
        ]);

        $domain = Domain::findOrFail($request->domain_id);

        // Check ownership
        if (!$request->user()->isAdmin() && $domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to create DNS zones for this domain.',
                ],
            ], 403);
        }

        // Check if zone already exists
        if (DnsZone::where('domain_id', $domain->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ZONE_EXISTS',
                    'message' => 'A DNS zone already exists for this domain.',
                ],
            ], 422);
        }

        try {
            $serverIp = $request->server_ip ?? $request->ip();
            $zone = $this->powerDnsService->createZone($domain, $serverIp);

            return response()->json([
                'success' => true,
                'data' => new DnsZoneResource($zone->load('domain', 'records')),
                'message' => 'DNS zone created successfully.',
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
     * Get zone details with records.
     */
    public function show(Request $request, DnsZone $zone): DnsZoneResource|JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view this DNS zone.',
                ],
            ], 403);
        }

        $zone->load('domain', 'records');

        return new DnsZoneResource($zone);
    }

    /**
     * Delete a DNS zone.
     */
    public function destroy(Request $request, DnsZone $zone): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to delete this DNS zone.',
                ],
            ], 403);
        }

        try {
            $this->powerDnsService->deleteZone($zone);

            return response()->json([
                'success' => true,
                'message' => 'DNS zone deleted successfully.',
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

    /**
     * Apply a DNS template to the zone.
     */
    public function applyTemplate(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'template' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this DNS zone.',
                ],
            ], 403);
        }

        try {
            $this->powerDnsService->applyTemplate(
                $zone,
                $request->template,
                $request->variables ?? []
            );

            return response()->json([
                'success' => true,
                'data' => new DnsZoneResource($zone->fresh()->load('domain', 'records')),
                'message' => 'DNS template applied successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'APPLY_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Export zone in BIND format.
     */
    public function export(Request $request, DnsZone $zone): JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to export this DNS zone.',
                ],
            ], 403);
        }

        $export = $this->powerDnsService->exportZone($zone);

        return response()->json([
            'success' => true,
            'data' => [
                'content' => $export,
                'filename' => $zone->zone_name . '.zone',
            ],
        ]);
    }

    /**
     * Get available DNS templates.
     */
    public function templates(): JsonResponse
    {
        $templates = $this->powerDnsService->getAvailableTemplates();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Preview a DNS template.
     */
    public function templatePreview(Request $request): JsonResponse
    {
        $request->validate([
            'template' => ['required', 'string'],
            'domain' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        try {
            $preview = $this->powerDnsService->getTemplatePreview(
                $request->template,
                $request->domain,
                $request->variables ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREVIEW_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Bulk add records to a zone.
     */
    public function bulkAddRecords(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*.name' => ['required', 'string'],
            'records.*.type' => ['required', 'string', 'in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA,PTR'],
            'records.*.content' => ['required', 'string'],
            'records.*.ttl' => ['nullable', 'integer', 'min:60'],
            'records.*.priority' => ['nullable', 'integer', 'min:0'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this DNS zone.',
                ],
            ], 403);
        }

        try {
            $result = $this->powerDnsService->bulkAddRecords($zone, $request->records);

            return response()->json([
                'success' => true,
                'data' => [
                    'added_count' => count($result['added']),
                    'errors' => $result['errors'],
                ],
                'message' => count($result['added']) . ' records added successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BULK_ADD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Bulk delete records from a zone.
     */
    public function bulkDeleteRecords(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['required', 'uuid'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this DNS zone.',
                ],
            ], 403);
        }

        try {
            $result = $this->powerDnsService->bulkDeleteRecords($zone, $request->record_ids);

            return response()->json([
                'success' => true,
                'data' => [
                    'deleted_count' => count($result['deleted']),
                    'errors' => $result['errors'],
                ],
                'message' => count($result['deleted']) . ' records deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BULK_DELETE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Bulk update records in a zone.
     */
    public function bulkUpdateRecords(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.id' => ['required', 'uuid'],
            'updates.*.name' => ['nullable', 'string'],
            'updates.*.type' => ['nullable', 'string', 'in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA,PTR,SOA'],
            'updates.*.content' => ['nullable', 'string'],
            'updates.*.ttl' => ['nullable', 'integer', 'min:60'],
            'updates.*.priority' => ['nullable', 'integer', 'min:0'],
            'updates.*.disabled' => ['nullable', 'boolean'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this DNS zone.',
                ],
            ], 403);
        }

        try {
            $result = $this->powerDnsService->bulkUpdateRecords($zone, $request->updates);

            return response()->json([
                'success' => true,
                'data' => [
                    'updated_count' => count($result['updated']),
                    'errors' => $result['errors'],
                ],
                'message' => count($result['updated']) . ' records updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BULK_UPDATE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Import zone from BIND format.
     */
    public function import(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'zone_file' => ['required', 'string'],
            'replace' => ['nullable', 'boolean'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this DNS zone.',
                ],
            ], 403);
        }

        try {
            $result = $this->powerDnsService->importZone(
                $zone,
                $request->zone_file,
                $request->replace ?? false
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'imported_count' => $result['imported'],
                    'errors' => $result['errors'],
                ],
                'message' => $result['imported'] . ' records imported successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IMPORT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Clone zone to another domain.
     */
    public function clone(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'target_zone_id' => ['required', 'uuid', 'exists:dns_zones,id'],
            'replace' => ['nullable', 'boolean'],
        ]);

        // Check ownership of source zone
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to clone this DNS zone.',
                ],
            ], 403);
        }

        $targetZone = DnsZone::findOrFail($request->target_zone_id);

        // Check ownership of target zone
        if (!$request->user()->isAdmin() && $targetZone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify the target DNS zone.',
                ],
            ], 403);
        }

        try {
            $result = $this->powerDnsService->cloneZone(
                $zone,
                $targetZone,
                $request->replace ?? false
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'cloned_count' => $result['cloned'],
                    'errors' => $result['errors'],
                ],
                'message' => $result['cloned'] . ' records cloned successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLONE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Reset zone to default records.
     */
    public function reset(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'server_ip' => ['nullable', 'ip'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to reset this DNS zone.',
                ],
            ], 403);
        }

        try {
            $serverIp = $request->server_ip ?? $request->ip();
            $this->powerDnsService->resetZone($zone, $serverIp);

            return response()->json([
                'success' => true,
                'data' => new DnsZoneResource($zone->fresh()->load('domain', 'records')),
                'message' => 'DNS zone reset to default records.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RESET_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
