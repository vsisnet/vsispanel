<?php

declare(strict_types=1);

namespace App\Modules\Mail\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailDomainResource extends JsonResource
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
            'domain_name' => $this->domain?->name,
            'is_active' => $this->is_active,
            'catch_all_address' => $this->catch_all_address,
            'max_accounts' => $this->max_accounts,
            'default_quota_mb' => $this->default_quota_mb,
            'dkim_enabled' => $this->dkim_enabled,
            'dkim_selector' => $this->dkim_selector,
            'dkim_public_key' => $this->dkim_public_key,
            'accounts_count' => $this->whenLoaded('accounts', fn() => $this->accounts->count()),
            'active_accounts_count' => $this->whenLoaded('accounts', fn() => $this->accounts->where('status', 'active')->count()),
            'aliases_count' => $this->whenLoaded('aliases', fn() => $this->aliases->count()),
            'accounts' => MailAccountResource::collection($this->whenLoaded('accounts')),
            'aliases' => MailAliasResource::collection($this->whenLoaded('aliases')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
