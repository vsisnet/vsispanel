<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'limits' => [
                'disk' => $this->disk_limit,
                'bandwidth' => $this->bandwidth_limit,
                'domains' => $this->domains_limit,
                'subdomains' => $this->subdomains_limit,
                'databases' => $this->databases_limit,
                'email_accounts' => $this->email_accounts_limit,
                'ftp_accounts' => $this->ftp_accounts_limit,
            ],
            'php_version_default' => $this->php_version_default,
            'is_active' => $this->is_active,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
