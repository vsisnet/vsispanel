<?php

declare(strict_types=1);

namespace App\Modules\SSL\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SslCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain_id' => $this->domain_id,
            'domain' => $this->whenLoaded('domain', fn () => [
                'id' => $this->domain->id,
                'name' => $this->domain->name,
            ]),
            'type' => $this->type,
            'status' => $this->status,
            'issuer' => $this->issuer,
            'serial_number' => $this->serial_number,
            'san' => $this->san,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'days_until_expiry' => $this->days_until_expiry,
            'auto_renew' => $this->auto_renew,
            'is_active' => $this->is_active,
            'is_expired' => $this->is_expired,
            'is_expiring_soon' => $this->is_expiring_soon,
            'status_color' => $this->status_color,
            'last_renewal_at' => $this->last_renewal_at?->toIso8601String(),
            'renewal_attempts' => $this->renewal_attempts,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
