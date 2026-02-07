<?php

declare(strict_types=1);

namespace App\Modules\DNS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DnsRecordResource extends JsonResource
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
            'dns_zone_id' => $this->dns_zone_id,
            'name' => $this->name,
            'type' => $this->type,
            'content' => $this->content,
            'ttl' => $this->ttl,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'port' => $this->port,
            'disabled' => $this->disabled,
            'full_name' => $this->full_name,
            'formatted_content' => $this->formatContent(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
