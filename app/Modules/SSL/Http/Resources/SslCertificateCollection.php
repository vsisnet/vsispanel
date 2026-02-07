<?php

declare(strict_types=1);

namespace App\Modules\SSL\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SslCertificateCollection extends ResourceCollection
{
    public $collects = SslCertificateResource::class;

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
