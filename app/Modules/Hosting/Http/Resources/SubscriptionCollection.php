<?php

declare(strict_types=1);

namespace App\Modules\Hosting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SubscriptionCollection extends ResourceCollection
{
    public $collects = SubscriptionResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
