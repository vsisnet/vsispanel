<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document_root' => $this->document_root_path,
            'php_version' => $this->php_version,
            'status' => $this->status,
            'ssl_enabled' => $this->ssl_enabled,
            'ssl_expires_at' => $this->ssl_expires_at?->toIso8601String(),
            'ssl_expires_in_days' => $this->ssl_expires_in_days,
            'is_expiring_soon' => $this->is_expiring_soon,
            'is_ssl_expired' => $this->is_ssl_expired,
            'is_main' => $this->is_main,
            'web_server_type' => $this->web_server_type,
            'disk_used' => $this->disk_used,
            'disk_used_formatted' => $this->disk_used_formatted,
            'bandwidth_used' => $this->bandwidth_used,
            'bandwidth_used_formatted' => $this->bandwidth_used_formatted,
            'subdomains_count' => $this->whenCounted('subdomains'),
            'subdomains' => SubdomainResource::collection($this->whenLoaded('subdomains')),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
