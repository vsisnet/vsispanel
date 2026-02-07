<?php

declare(strict_types=1);

namespace App\Modules\DNS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DnsZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain_id' => $this->domain_id,
            'zone_name' => $this->zone_name,
            'serial' => $this->serial,
            'refresh' => $this->refresh,
            'retry' => $this->retry,
            'expire' => $this->expire,
            'minimum_ttl' => $this->minimum_ttl,
            'primary_ns' => $this->primary_ns,
            'admin_email' => $this->admin_email,
            'status' => $this->status,
            'domain' => [
                'id' => $this->domain?->id,
                'name' => $this->domain?->name,
            ],
            'records_count' => $this->whenCounted('records'),
            'records' => DnsRecordResource::collection($this->whenLoaded('records')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
