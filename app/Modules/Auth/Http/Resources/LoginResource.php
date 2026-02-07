<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    protected string $token;

    public function __construct($resource, string $token)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource),
            'token' => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
