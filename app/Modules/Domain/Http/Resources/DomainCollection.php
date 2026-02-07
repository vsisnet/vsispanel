<?php

declare(strict_types=1);

namespace App\Modules\Domain\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DomainCollection extends ResourceCollection
{
    public $collects = DomainResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total_active' => $this->collection->where('status', 'active')->count(),
                'total_suspended' => $this->collection->where('status', 'suspended')->count(),
                'total_with_ssl' => $this->collection->where('ssl_enabled', true)->count(),
            ],
        ];
    }
}
