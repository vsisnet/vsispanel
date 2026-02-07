<?php

declare(strict_types=1);

namespace App\Modules\DNS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DNS\Http\Resources\DnsRecordResource;
use App\Modules\DNS\Models\DnsRecord;
use App\Modules\DNS\Models\DnsZone;
use App\Modules\DNS\Services\PowerDnsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DnsRecordController extends Controller
{
    public function __construct(
        protected PowerDnsService $powerDnsService
    ) {}

    /**
     * List records for a zone.
     */
    public function index(Request $request, DnsZone $zone): AnonymousResourceCollection|JsonResponse
    {
        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to view records for this zone.',
                ],
            ], 403);
        }

        $query = $zone->records();

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        $records = $query->orderBy('type')->orderBy('name')->get();

        return DnsRecordResource::collection($records);
    }

    /**
     * Add a record to a zone.
     */
    public function store(Request $request, DnsZone $zone): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:A,AAAA,CNAME,MX,TXT,SRV,NS,CAA,PTR'],
            'content' => ['required', 'string', 'max:4096'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'port' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to add records to this zone.',
                ],
            ], 403);
        }

        // Validate priority for MX/SRV
        if (in_array($request->type, ['MX', 'SRV']) && $request->priority === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Priority is required for MX and SRV records.',
                ],
            ], 422);
        }

        // Validate weight and port for SRV
        if ($request->type === 'SRV') {
            if ($request->weight === null || $request->port === null) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Weight and port are required for SRV records.',
                    ],
                ], 422);
            }
        }

        try {
            $record = $this->powerDnsService->addRecord($zone, $request->only([
                'name', 'type', 'content', 'ttl', 'priority', 'weight', 'port'
            ]));

            return response()->json([
                'success' => true,
                'data' => new DnsRecordResource($record),
                'message' => 'DNS record added successfully.',
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
     * Update a record.
     */
    public function update(Request $request, DnsRecord $record): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:4096'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'port' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'disabled' => ['nullable', 'boolean'],
        ]);

        $zone = $record->zone;

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to update this record.',
                ],
            ], 403);
        }

        // Don't allow changing SOA records
        if ($record->type === 'SOA') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'SOA records cannot be modified directly.',
                ],
            ], 422);
        }

        try {
            $updated = $this->powerDnsService->updateRecord(
                $record,
                $request->only(['name', 'content', 'ttl', 'priority', 'weight', 'port', 'disabled'])
            );

            return response()->json([
                'success' => true,
                'data' => new DnsRecordResource($updated),
                'message' => 'DNS record updated successfully.',
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
     * Delete a record.
     */
    public function destroy(Request $request, DnsRecord $record): JsonResponse
    {
        $zone = $record->zone;

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to delete this record.',
                ],
            ], 403);
        }

        // Don't allow deleting SOA or required NS records
        if ($record->type === 'SOA') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'SOA records cannot be deleted.',
                ],
            ], 422);
        }

        try {
            $this->powerDnsService->deleteRecord($record);

            return response()->json([
                'success' => true,
                'message' => 'DNS record deleted successfully.',
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
     * Toggle record enabled/disabled status.
     */
    public function toggle(Request $request, DnsRecord $record): JsonResponse
    {
        $zone = $record->zone;

        // Check ownership
        if (!$request->user()->isAdmin() && $zone->domain->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to modify this record.',
                ],
            ], 403);
        }

        // Don't allow toggling SOA records
        if ($record->type === 'SOA') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_OPERATION',
                    'message' => 'SOA records cannot be disabled.',
                ],
            ], 422);
        }

        try {
            $updated = $this->powerDnsService->toggleRecord($record);

            return response()->json([
                'success' => true,
                'data' => new DnsRecordResource($updated),
                'message' => $updated->disabled ? 'DNS record disabled.' : 'DNS record enabled.',
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
}
