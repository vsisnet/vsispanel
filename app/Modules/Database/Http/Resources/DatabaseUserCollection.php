<?php

declare(strict_types=1);

namespace App\Modules\Database\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DatabaseUserCollection extends ResourceCollection
{
    public $collects = DatabaseUserResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
            ],
        ];
    }
}
