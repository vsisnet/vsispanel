<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubdomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'document_root' => $this->document_root_path,
            'php_version' => $this->php_version,
            'status' => $this->status,
            'ssl_enabled' => $this->ssl_enabled,
            'domain_id' => $this->domain_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
